<?php

namespace App\Services;

use App\Database;
use App\Logger;
use App\Notifications\ResellerNotifier;
use App\Telegram;
use PDO;
use RuntimeException;

class ProductStockService
{
    /**
     * @param int $productId
     * @param array<int,string> $contents
     * @return array{added:int,skipped:int}
     */
    public static function addStockItems(int $productId, array $contents): array
    {
        $productId = max(0, $productId);
        if ($productId === 0) {
            throw new RuntimeException('Geçersiz ürün numarası.');
        }

        $pdo = Database::connection();
        $insert = $pdo->prepare('INSERT INTO product_stock_items (product_id, content, content_hash, status, created_at) VALUES (:product_id, :content, :hash, \"available\", NOW())');

        $added = 0;
        $skipped = 0;

        foreach ($contents as $rawContent) {
            $content = trim((string) $rawContent);
            if ($content === '') {
                $skipped++;
                continue;
            }

            $hash = hash('sha256', $content);

            try {
                $insert->execute(array(
                    'product_id' => $productId,
                    'content' => $content,
                    'hash' => $hash,
                ));
                if ($insert->rowCount() > 0) {
                    $added++;
                } else {
                    $skipped++;
                }
            } catch (\PDOException $exception) {
                $skipped++;
            }
        }

        if ($added > 0) {
            self::logger()->info(sprintf('Ürün #%d için %d stok satırı eklendi.', $productId, $added));
        }

        return array('added' => $added, 'skipped' => $skipped);
    }

    public static function availableStockCount(int $productId): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_stock_items WHERE product_id = :product_id AND status = \"available\"');
        $stmt->execute(array('product_id' => $productId));
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param int $productId
     * @return array<string,int>
     */
    public static function stockSummary(int $productId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT status, COUNT(*) AS total FROM product_stock_items WHERE product_id = :product_id GROUP BY status');
        $stmt->execute(array('product_id' => $productId));

        $summary = array(
            'available' => 0,
            'reserved' => 0,
            'delivered' => 0,
            'all' => 0,
        );

        $totalAll = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = isset($row['status']) ? (string) $row['status'] : '';
            $total = isset($row['total']) ? (int) $row['total'] : 0;
            if (isset($summary[$status])) {
                $summary[$status] = $total;
            }
            $totalAll += $total;
        }

        $summary['all'] = $totalAll;

        return $summary;
    }

    /**
     * @param int $productId
     * @param string $status
     * @param int $limit
     * @param int $offset
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public static function paginateStock(int $productId, string $status, int $limit = 50, int $offset = 0): array
    {
        $allowed = array('available', 'reserved', 'delivered', 'all');
        $status = in_array($status, $allowed, true) ? $status : 'available';

        $pdo = Database::connection();

        if ($status === 'all') {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM product_stock_items WHERE product_id = :product_id');
            $countStmt->execute(array('product_id' => $productId));
            $total = (int) $countStmt->fetchColumn();

            $listStmt = $pdo->prepare('SELECT * FROM product_stock_items WHERE product_id = :product_id ORDER BY id DESC LIMIT :limit OFFSET :offset');
            $listStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $listStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $listStmt->execute();
        } else {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM product_stock_items WHERE product_id = :product_id AND status = :status');
            $countStmt->execute(array('product_id' => $productId, 'status' => $status));
            $total = (int) $countStmt->fetchColumn();

            $listStmt = $pdo->prepare('SELECT * FROM product_stock_items WHERE product_id = :product_id AND status = :status ORDER BY id DESC LIMIT :limit OFFSET :offset');
            $listStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $listStmt->bindValue(':status', $status);
            $listStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $listStmt->execute();
        }

        return array('items' => $listStmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total);
    }

    public static function deleteStockItem(int $productId, int $stockId): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM product_stock_items WHERE id = :id AND product_id = :product_id AND status = \"available\"');
        $stmt->execute(array('id' => $stockId, 'product_id' => $productId));
        if ($stmt->rowCount() > 0) {
            self::logger()->info(sprintf('Ürün #%d stok kaydı #%d silindi.', $productId, $stockId));
            return true;
        }
        return false;
    }

    /**
     * @param int $orderId
     * @return array<string,mixed>
     */
    public static function deliverOrderFromStock(int $orderId): array
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return array('success' => false, 'reason' => 'Geçersiz sipariş numarası.');
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $orderStmt = $pdo->prepare('SELECT po.*, p.name AS product_name, p.provider_code, u.name AS user_name, u.email AS user_email, u.notify_order_completed, u.telegram_bot_token, u.telegram_chat_id FROM product_orders po INNER JOIN products p ON po.product_id = p.id INNER JOIN users u ON po.user_id = u.id WHERE po.id = :id FOR UPDATE');
            $orderStmt->execute(array('id' => $orderId));
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $pdo->rollBack();
                return array('success' => false, 'reason' => 'Sipariş bulunamadı.');
            }

            $productId = (int) $order['product_id'];
            $quantity = isset($order['quantity']) ? max(1, (int) $order['quantity']) : 1;

            $providerCode = isset($order['provider_code']) ? strtolower((string) $order['provider_code']) : '';
            if ($providerCode !== '' && $providerCode !== 'stock' && $providerCode !== 'panel') {
                $pdo->rollBack();
                return array('success' => false, 'reason' => 'Sipariş stok ile eşlenmemiş.');
            }

            if ($order['status'] === 'completed') {
                $pdo->commit();
                return array('success' => true, 'status' => 'completed', 'message' => 'Sipariş zaten tamamlanmış.');
            }

            $stockStmt = $pdo->prepare('SELECT id, content FROM product_stock_items WHERE product_id = :product_id AND status = \"available\" ORDER BY id ASC LIMIT :limit FOR UPDATE');
            $stockStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $stockStmt->bindValue(':limit', $quantity, PDO::PARAM_INT);
            $stockStmt->execute();
            $stockRows = $stockStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($stockRows) < $quantity) {
                $pdo->rollBack();
                self::logger()->error(sprintf('Sipariş #%d için yeterli stok bulunamadı. İstenen: %d, bulunan: %d', $orderId, $quantity, count($stockRows)));
                return array('success' => false, 'reason' => 'Stokta yeterli ürün bulunmuyor.');
            }

            $stockIds = array();
            $contents = array();
            foreach ($stockRows as $row) {
                $stockIds[] = (int) $row['id'];
                $contents[] = (string) $row['content'];
            }

            $placeholders = implode(',', array_fill(0, count($stockIds), '?'));
            $update = $pdo->prepare('UPDATE product_stock_items SET status = \"delivered\", order_id = ?, reserved_at = COALESCE(reserved_at, NOW()), delivered_at = NOW(), updated_at = NOW() WHERE id IN (' . $placeholders . ')');
            $update->execute(array_merge(array($orderId), $stockIds));

            $deliveryContent = implode(PHP_EOL, $contents);

            $metadata = array();
            if (!empty($order['external_metadata'])) {
                $decoded = json_decode((string) $order['external_metadata'], true);
                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }

            $metadata['provider'] = 'stock';
            $metadata['delivery_content'] = $deliveryContent;
            $metadata['stock_item_ids'] = $stockIds;
            $metadata['provider_response'] = array(
                'source' => 'stock',
                'data' => array(
                    'status' => 'completed',
                    'content' => $deliveryContent,
                    'stock_items' => array_map(static function ($id, $content) {
                        return array('id' => $id, 'content' => $content);
                    }, $stockIds, $contents),
                ),
            );

            $updateOrder = $pdo->prepare('UPDATE product_orders SET status = :status, admin_note = :admin_note, external_metadata = :metadata, updated_at = NOW() WHERE id = :id');
            $updateOrder->execute(array(
                'status' => 'completed',
                'admin_note' => $deliveryContent,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => $orderId,
            ));

            $pdo->commit();

            $shouldNotify = $order['status'] !== 'completed';
            if ($shouldNotify) {
                $orderPayload = array(
                    'id' => $orderId,
                    'product_name' => isset($order['product_name']) ? $order['product_name'] : '',
                    'quantity' => $quantity,
                    'price' => isset($order['price']) ? (float) $order['price'] : 0.0,
                    'admin_note' => $deliveryContent,
                );

                $userPayload = array(
                    'email' => isset($order['user_email']) ? $order['user_email'] : null,
                    'name' => isset($order['user_name']) ? $order['user_name'] : null,
                    'notify_order_completed' => isset($order['notify_order_completed']) ? (int) $order['notify_order_completed'] : 0,
                    'telegram_bot_token' => isset($order['telegram_bot_token']) ? $order['telegram_bot_token'] : null,
                    'telegram_chat_id' => isset($order['telegram_chat_id']) ? $order['telegram_chat_id'] : null,
                );

                ResellerNotifier::sendOrderCompleted($orderPayload, $userPayload);

                Telegram::notify(sprintf(
                    "📦 Stok teslimatı tamamlandı!\nBayi: %s\nÜrün: %s\nSipariş No: #%d",
                    isset($order['user_name']) ? $order['user_name'] : 'Bilinmiyor',
                    isset($order['product_name']) ? $order['product_name'] : 'Ürün',
                    $orderId
                ));
            }

            self::logger()->info(sprintf('Sipariş #%d stoktan otomatik teslim edildi. Kullanılan stok: %s', $orderId, implode(',', $stockIds)));

            return array(
                'success' => true,
                'status' => 'completed',
                'content' => $deliveryContent,
                'message' => 'Sipariş stoktan otomatik teslim edildi.',
            );
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            self::logger()->error('Stok teslimatı başarısız: ' . $exception->getMessage());
            return array('success' => false, 'reason' => 'Stok teslimatı sırasında hata oluştu.');
        }
    }

    private static function logger(): Logger
    {
        return new Logger(dirname(__DIR__, 2) . '/storage/stock.log');
    }
}
