<?php declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Helpers;
use App\Telegram;
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
        return self::placeOrder($user, $productId, $note, 'panel');
    }

    /**
     * API ve panel gibi farklı kaynaklardan sipariş oluşturmayı destekler.
     *
     * @param array<string,mixed> $user
     * @param int $productId
     * @param string|null $note
     * @param string $source
     * @return array<string,mixed>
     */
    public static function placeOrder(array $user, int $productId, ?string $note = null, string $source = 'panel'): array
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

        $source = trim($source) !== '' ? substr($source, 0, 50) : 'panel';

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

            $automaticDelivery = isset($product['automatic_delivery']) ? (int)$product['automatic_delivery'] === 1 : false;

            if (!$automaticDelivery) {
                $stockCheck = $pdo->prepare('SELECT COUNT(*) FROM product_stock_items WHERE product_id = :product_id AND status = :status FOR UPDATE');
                $stockCheck->execute(['product_id' => $productId, 'status' => 'available']);
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
                'source' => $source,
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
                Helpers::formatCurrency($price, 'TRY'),
                $orderId
            ));

            return [
                'success' => true,
                'order_id' => $orderId,
                'status' => $automaticDelivery ? 'processing' : 'pending',
                'message' => $automaticDelivery
                    ? 'Siparişiniz otomatik teslimat için kuyruğa alındı. Sipariş durumunu kısa süre içinde siparişlerim ekranından takip edebilirsiniz.'
                    : 'Sipariş talebiniz alındı ve bakiyenizden düşüldü. Ürün stoktan teslim edilecektir.',
            ];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Sipariş talebiniz kaydedilirken bir veritabanı hatası oluştu.'];
        }
    }
}
