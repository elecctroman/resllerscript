<?php declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Helpers;
use App\Telegram;
use App\Services\ProviderDispatchService;
use PDO;
use RuntimeException;

final class ProductOrderService
{
    /**
     * Panel üzerinden yeni bir ürün siparişi oluşturur.
     *
     * @param array<string,mixed> $user
     * @param int $productId
     * @param string|null $note
     * @return array<string,mixed>
     */
    public static function placePanelOrder(array $user, int $productId, ?string $note = null): array
    {
        $productId = max(0, $productId);
        if ($productId === 0) {
            throw new RuntimeException('Geçersiz ürün numarası.');
        }

        $pdo = Database::connection();
        $note = $note !== null ? trim($note) : null;
        if ($note === '') {
            $note = null;
        }

        try {
            $pdo->beginTransaction();

            $productStmt = $pdo->prepare('SELECT pr.*, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id WHERE pr.id = :id AND pr.status = :status FOR UPDATE');
            $productStmt->execute([
                'id' => $productId,
                'status' => 'active',
            ]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Ürün bulunamadı veya pasif durumda.'];
            }

            $userStmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $userStmt->execute(['id' => $user['id']]);
            $freshUser = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!$freshUser) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Kullanıcı kaydı bulunamadı. Lütfen oturumu kapatıp tekrar deneyin.'];
            }

            $price = (float)$product['price'];
            $currentBalance = (float)$freshUser['balance'];
            if ($price > $currentBalance) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Bakiyeniz bu ürünü sipariş etmek için yetersiz görünüyor.'];
            }

            $providerCode = isset($product['provider_code']) ? strtolower((string)$product['provider_code']) : '';
            $useLocalStock = ($providerCode === '' || $providerCode === 'stock' || $providerCode === 'panel');

            if ($useLocalStock) {
                $stockCheck = $pdo->prepare('SELECT COUNT(*) FROM product_stock_items WHERE product_id = :product_id AND status = "available" FOR UPDATE');
                $stockCheck->execute(['product_id' => $productId]);
                $availableStock = (int)$stockCheck->fetchColumn();
                if ($availableStock < 1) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Bu ürün şu anda stokta bulunmuyor.'];
                }
            }

            $orderStmt = $pdo->prepare('INSERT INTO product_orders (product_id, user_id, quantity, note, price, total_amount, status, source, created_at) VALUES (:product_id, :user_id, 1, :note, :price, :total_amount, :status, :source, NOW())');
            $orderStmt->execute([
                'product_id' => $productId,
                'user_id' => $user['id'],
                'note' => $note,
                'price' => $price,
                'total_amount' => $price,
                'status' => 'pending',
                'source' => 'panel',
            ]);

            $orderId = (int)$pdo->lastInsertId();

            $pdo->prepare('UPDATE users SET balance = balance - :amount WHERE id = :id')->execute([
                'amount' => $price,
                'id' => $user['id'],
            ]);

            $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')->execute([
                'user_id' => $user['id'],
                'amount' => $price,
                'type' => 'debit',
                'description' => 'Ürün siparişi: ' . $product['name'],
            ]);

            $pdo->commit();

            Telegram::notify(sprintf(
                "🛒 Yeni ürün siparişi alındı!\nBayi: %s\nÜrün: %s\nTutar: %s\nSipariş No: #%d",
                $user['name'],
                $product['name'],
                Helpers::formatCurrency($price, 'USD'),
                $orderId
            ));

            $dispatchResult = ProviderDispatchService::dispatchProductOrder($orderId);
            $message = 'Sipariş talebiniz alındı ve bakiyenizden düşüldü. ';
            if (is_array($dispatchResult)) {
                if (!empty($dispatchResult['message'])) {
                    $message .= (string)$dispatchResult['message'];
                }
                if (!empty($dispatchResult['success']) && !empty($dispatchResult['status']) && $dispatchResult['status'] === 'completed') {
                    $message .= ' Teslimat tamamlandı, detayları siparişlerim bölümünde görüntüleyebilirsiniz.';
                }
            }

            return [
                'success' => true,
                'order_id' => $orderId,
                'status' => isset($dispatchResult['status']) ? $dispatchResult['status'] : 'pending',
                'message' => trim($message),
            ];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Sipariş talebiniz kaydedilirken bir veritabanı hatası oluştu.'];
        }
    }
}
