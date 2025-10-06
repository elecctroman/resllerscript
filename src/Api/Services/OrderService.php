<?php declare(strict_types=1);

namespace App\Api\Services;

use App\Api\Exceptions\BadRequestException;
use App\Api\Exceptions\ValidationException;
use App\Api\Repositories\OrderRepository;
use App\Api\Repositories\ProductRepository;
use App\Api\Repositories\UserRepository;
use App\Database;
use App\Services\ProviderDispatchService;
use PDO;
use RuntimeException;

/**
 * API üzerinden sipariş oluşturma ve sorgulama süreçlerini yönetir.
 */
final class OrderService
{
    private ProductRepository $productRepository;
    private OrderRepository $orderRepository;
    private UserRepository $userRepository;

    public function __construct(
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        UserRepository $userRepository
    ) {
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @param array<string,mixed> $tokenRow
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createOrder(array $tokenRow, array $payload): array
    {
        $userId = isset($tokenRow['user_id']) ? (int) $tokenRow['user_id'] : 0;
        if ($userId <= 0) {
            throw new BadRequestException('API anahtarı geçersiz kullanıcıya ait.');
        }

        $productId = isset($payload['product_id']) ? (int) $payload['product_id'] : 0;
        $sku = isset($payload['sku']) ? trim((string) $payload['sku']) : '';
        $quantity = isset($payload['quantity']) ? (int) $payload['quantity'] : 1;
        $note = isset($payload['note']) ? trim((string) $payload['note']) : null;
        $externalReference = isset($payload['external_reference']) ? trim((string) $payload['external_reference']) : null;
        $metadata = isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : array();

        $errors = array();
        if ($productId <= 0 && $sku === '') {
            $errors['product'] = 'Lütfen ürün numarası veya SKU değeri gönderin.';
        }

        if ($quantity <= 0) {
            $errors['quantity'] = 'Adet değeri pozitif olmalıdır.';
        }

        if (mb_strlen((string) $externalReference) > 191) {
            $errors['external_reference'] = 'external_reference alanı 191 karakterden uzun olamaz.';
        }

        if ($errors !== array()) {
            throw new ValidationException('Sipariş isteği doğrulanamadı.', $errors);
        }

        if ($quantity > 100) {
            throw new ValidationException('Sipariş isteği doğrulanamadı.', array(
                'quantity' => 'En fazla 100 adet sipariş edilebilir.',
            ));
        }

        $product = null;
        if ($productId > 0) {
            $product = $this->productRepository->findActiveById($productId);
        }

        if ($product === null && $sku !== '') {
            $product = $this->productRepository->findActiveBySku($sku);
        }

        if ($product === null) {
            throw new BadRequestException('Ürün bulunamadı veya pasif durumda.');
        }

        $user = $this->userRepository->findById($userId);
        if ($user === null || ($user['status'] ?? '') !== 'active') {
            throw new BadRequestException('Kullanıcı hesabı aktif değil.');
        }

        $pdo = Database::connection();
        $currentBalance = isset($user['balance']) ? (float) $user['balance'] : 0.0;
        $price = 0.0;
        $total = 0.0;
        $orderId = 0;

        try {
            $pdo->beginTransaction();

            if ($externalReference !== null && $externalReference !== '') {
                $dupStmt = $pdo->prepare('SELECT id FROM product_orders WHERE user_id = :user_id AND external_reference = :reference LIMIT 1 FOR UPDATE');
                $dupStmt->execute(array(
                    'user_id' => $userId,
                    'reference' => $externalReference,
                ));

                if ($dupStmt->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->rollBack();
                    throw new ValidationException('Sipariş isteği doğrulanamadı.', array(
                        'external_reference' => 'Bu external_reference daha önce kullanılmış.',
                    ));
                }
            }

            $productStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id AND status = :status FOR UPDATE');
            $productStmt->execute(array(
                'id' => $product['id'],
                'status' => 'active',
            ));
            $productRow = $productStmt->fetch(PDO::FETCH_ASSOC);

            if (!$productRow) {
                $pdo->rollBack();
                throw new BadRequestException('Ürün bulunamadı veya pasif durumda.');
            }

            $userStmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $userStmt->execute(array('id' => $userId));
            $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!$userRow) {
                $pdo->rollBack();
                throw new BadRequestException('Kullanıcı bulunamadı.');
            }

            $price = isset($productRow['price']) ? (float) $productRow['price'] : 0.0;
            $total = $price * $quantity;
            $currentBalance = isset($userRow['balance']) ? (float) $userRow['balance'] : 0.0;

            if ($total > $currentBalance) {
                $pdo->rollBack();
                throw new ValidationException('Sipariş isteği doğrulanamadı.', array(
                    'balance' => 'Bakiyeniz siparişi oluşturmak için yetersiz.',
                ));
            }

            $providerCode = isset($productRow['provider_code']) ? strtolower((string) $productRow['provider_code']) : '';
            $automaticDelivery = isset($productRow['automatic_delivery']) ? (int) $productRow['automatic_delivery'] === 1 : false;
            $useLocalStock = ($providerCode === '' || $providerCode === 'stock' || $providerCode === 'panel') && !$automaticDelivery;

            if ($useLocalStock) {
                $stockStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM product_stock_items WHERE product_id = :product_id AND status = "available" FOR UPDATE');
                $stockStmt->execute(array('product_id' => $product['id']));
                $available = (int) $stockStmt->fetchColumn();
                if ($available < $quantity) {
                    $pdo->rollBack();
                    throw new ValidationException('Sipariş isteği doğrulanamadı.', array(
                        'quantity' => 'İstenen adet için stok bulunmuyor.',
                    ));
                }
            }

            $metadataJson = null;
            if ($metadata !== array()) {
                $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($encoded !== false) {
                    $metadataJson = $encoded;
                }
            }

            $orderStmt = $pdo->prepare(
                'INSERT INTO product_orders (product_id, user_id, api_token_id, quantity, note, price, total_amount, status, source, external_reference, external_metadata, created_at) ' .
                'VALUES (:product_id, :user_id, :api_token_id, :quantity, :note, :price, :total_amount, :status, :source, :external_reference, :metadata, NOW())'
            );
            $orderStmt->execute(array(
                'product_id' => $product['id'],
                'user_id' => $userId,
                'api_token_id' => isset($tokenRow['token_id']) ? (int) $tokenRow['token_id'] : null,
                'quantity' => $quantity,
                'note' => $note,
                'price' => $price,
                'total_amount' => $total,
                'status' => 'pending',
                'source' => 'api',
                'external_reference' => $externalReference,
                'metadata' => $metadataJson,
            ));

            $orderId = (int) $pdo->lastInsertId();

            $balanceUpdate = $pdo->prepare('UPDATE users SET balance = balance - :amount WHERE id = :id');
            $balanceUpdate->execute(array(
                'amount' => $total,
                'id' => $userId,
            ));

            $transactionStmt = $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())');
            $transactionStmt->execute(array(
                'user_id' => $userId,
                'amount' => $total,
                'type' => 'debit',
                'description' => 'API siparişi: ' . $productRow['name'],
            ));

            $pdo->commit();
        } catch (ValidationException $validationException) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $validationException;
        } catch (BadRequestException $badRequestException) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $badRequestException;
        } catch (RuntimeException $runtimeException) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $runtimeException;
        } catch (\Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Sipariş oluşturulurken beklenmeyen bir hata oluştu: ' . $throwable->getMessage(), 0, $throwable);
        }

        $dispatchResult = ProviderDispatchService::dispatchProductOrder($orderId ?? 0);

        $response = array(
            'order_id' => $orderId,
            'status' => isset($dispatchResult['status']) ? (string) $dispatchResult['status'] : 'pending',
            'message' => isset($dispatchResult['message']) ? (string) $dispatchResult['message'] : 'Sipariş başarıyla oluşturuldu.',
            'product' => array(
                'id' => $product['id'],
                'name' => $product['name'],
                'sku' => $product['sku'],
            ),
            'quantity' => $quantity,
            'total_amount' => $total,
            'remaining_balance' => $currentBalance - $total,
        );

        if (!empty($dispatchResult['content'])) {
            $response['delivery'] = $dispatchResult['content'];
        }

        if (!empty($dispatchResult['reason'])) {
            $response['provider_note'] = (string) $dispatchResult['reason'];
        }

        return $response;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findOrderById(int $orderId, int $userId): ?array
    {
        return $this->orderRepository->findForUserById($orderId, $userId);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findOrderByReference(string $reference, int $userId): ?array
    {
        return $this->orderRepository->findForUserByReference($reference, $userId);
    }
}
