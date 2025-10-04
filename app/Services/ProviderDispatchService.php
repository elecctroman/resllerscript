<?php

namespace App\Services;

use App\Database;
use App\Notifications\ResellerNotifier;
use App\Telegram;
use PDO;

// reuse local stock fulfilment service
use App\Services\ProductStockService;

class ProviderDispatchService
{
    /**
     * @param int $orderId
     * @return array<string,mixed>
     */
    public static function dispatchProductOrder($orderId)
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return array('success' => false, 'reason' => 'Geçersiz sipariş numarası.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT po.*, p.name AS product_name, p.provider_code, p.provider_product_id, u.name AS user_name, u.email AS user_email, u.notify_order_completed, u.telegram_bot_token, u.telegram_chat_id FROM product_orders po INNER JOIN products p ON po.product_id = p.id INNER JOIN users u ON po.user_id = u.id WHERE po.id = :id LIMIT 1');
        $stmt->execute(array('id' => $orderId));
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return array('success' => false, 'reason' => 'Sipariş bulunamadı.');
        }

        $providerCode = isset($order['provider_code']) ? strtolower((string) $order['provider_code']) : '';
        $providerProductId = isset($order['provider_product_id']) ? trim((string) $order['provider_product_id']) : '';

        if ($providerCode === '' || $providerCode === 'panel' || $providerCode === 'stock') {
            return ProductStockService::deliverOrderFromStock($orderId);
        }

        if ($providerProductId === '') {
            return array('success' => false, 'reason' => 'Sağlayıcı ürünü eşlenmemiş.');
        }

        // Şu anda harici sağlayıcı bulunmuyor. İleride yeniden yapılandırılabilir.
        $metadata = array();
        if (!empty($order['external_metadata'])) {
            $decoded = json_decode((string) $order['external_metadata'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $metadata['provider'] = $providerCode;
        $metadata['message'] = 'Harici sağlayıcı entegrasyonu devre dışı bırakıldı.';

        $update = $pdo->prepare('UPDATE product_orders SET status = :status, external_reference = :reference, external_metadata = :metadata, admin_note = :admin_note, updated_at = NOW() WHERE id = :id');
        $update->execute(array(
            'status' => 'pending',
            'reference' => $order['external_reference'] ?? null,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'admin_note' => 'Sağlayıcı devre dışı.',
            'id' => $orderId,
        ));

        return array('success' => false, 'reason' => 'Sağlayıcı devre dışı.');
    }

    /**
     * @param array<int,int> $orderIds
     * @return void
     */
    public static function dispatchProductOrders(array $orderIds)
    {
        foreach ($orderIds as $orderId) {
            try {
                $result = self::dispatchProductOrder($orderId);
                if (isset($result['success']) && $result['success']) {
                    self::notifyIfCompleted($orderId);
                }
            } catch (\Throwable $exception) {
                error_log('[ProviderDispatch] Sipariş #' . (int) $orderId . ' gönderilemedi: ' . $exception->getMessage());
            }
        }
    }

    private static function notifyIfCompleted(int $orderId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT po.*, p.name AS product_name, u.name AS user_name, u.email AS user_email, u.notify_order_completed, u.telegram_bot_token, u.telegram_chat_id FROM product_orders po INNER JOIN products p ON po.product_id = p.id INNER JOIN users u ON po.user_id = u.id WHERE po.id = :id LIMIT 1');
        $stmt->execute(array('id' => $orderId));
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order || $order['status'] !== 'completed') {
            return;
        }

        $orderPayload = array(
            'id' => $orderId,
            'product_name' => $order['product_name'],
            'quantity' => isset($order['quantity']) ? (int) $order['quantity'] : 1,
            'price' => isset($order['price']) ? (float) $order['price'] : 0.0,
            'admin_note' => $order['admin_note'] ?? null,
        );

        $userPayload = array(
            'email' => $order['user_email'] ?? null,
            'name' => $order['user_name'] ?? null,
            'notify_order_completed' => isset($order['notify_order_completed']) ? (int) $order['notify_order_completed'] : 0,
            'telegram_bot_token' => $order['telegram_bot_token'] ?? null,
            'telegram_chat_id' => $order['telegram_chat_id'] ?? null,
        );

        ResellerNotifier::sendOrderCompleted($orderPayload, $userPayload);
        Telegram::notify(sprintf(
            "📦 Sipariş tamamlandı!\nBayi: %s\nÜrün: %s\nSipariş No: #%d",
            $order['user_name'],
            $order['product_name'],
            $orderId
        ));
    }
}
