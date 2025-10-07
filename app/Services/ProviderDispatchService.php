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

        $provider = ProviderManager::findActiveBySlug($providerCode);
        if (!$provider) {
            $metadata = self::mergeMetadata($order, array(
                'provider' => $providerCode,
                'provider_error' => array('message' => 'Sağlayıcı aktif değil.'),
            ));

            $update = $pdo->prepare('UPDATE product_orders SET external_metadata = :metadata, admin_note = :admin_note, updated_at = NOW() WHERE id = :id');
            $update->execute(array(
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'admin_note' => 'Sağlayıcı aktif değil.',
                'id' => $orderId,
            ));

            return array('success' => false, 'reason' => 'Sağlayıcı etkin değil.');
        }

        if (empty($provider['api_key'])) {
            $metadata = self::mergeMetadata($order, array(
                'provider' => $providerCode,
                'provider_error' => array('message' => 'API anahtarı tanımlanmamış.'),
            ));

            $update = $pdo->prepare('UPDATE product_orders SET external_metadata = :metadata, admin_note = :admin_note, updated_at = NOW() WHERE id = :id');
            $update->execute(array(
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'admin_note' => 'Sağlayıcı API anahtarı eksik.',
                'id' => $orderId,
            ));

            return array('success' => false, 'reason' => 'Sağlayıcı API anahtarı eksik.');
        }

        try {
            $client = new ProviderApiClient((string) $provider['api_url'], (string) $provider['api_key']);
        } catch (\Throwable $exception) {
            $metadata = self::mergeMetadata($order, array(
                'provider' => $providerCode,
                'provider_error' => array('message' => $exception->getMessage()),
            ));

            $update = $pdo->prepare('UPDATE product_orders SET external_metadata = :metadata, admin_note = :admin_note, updated_at = NOW() WHERE id = :id');
            $update->execute(array(
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'admin_note' => 'Sağlayıcı istemci başlatılamadı.',
                'id' => $orderId,
            ));

            return array('success' => false, 'reason' => $exception->getMessage());
        }

        $payload = array('product_id' => (int) $providerProductId);
        if (!empty($order['note'])) {
            $payload['note'] = $order['note'];
        }

        $response = $client->createOrder($payload);

        $metadata = self::mergeMetadata($order, array(
            'provider' => $providerCode,
            'provider_response' => $response,
        ));

        if (!empty($response['success'])) {
            $remote = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();
            $remoteStatus = isset($remote['status']) ? strtolower((string) $remote['status']) : '';
            $localStatus = 'processing';

            if ($remoteStatus === 'completed') {
                $localStatus = 'completed';
            } elseif ($remoteStatus === 'pending' || $remoteStatus === '') {
                $localStatus = 'processing';
            } elseif ($remoteStatus === 'cancelled') {
                $localStatus = 'cancelled';
            } elseif ($remoteStatus === 'failed') {
                $localStatus = 'pending';
            }

            if (!empty($remote['content'])) {
                $metadata['delivery_content'] = (string) $remote['content'];
            }

            $reference = null;
            if (isset($remote['order_id'])) {
                $reference = (string) $remote['order_id'];
            } elseif (isset($remote['id'])) {
                $reference = (string) $remote['id'];
            }

            $update = $pdo->prepare('UPDATE product_orders SET status = :status, external_reference = :reference, external_metadata = :metadata, updated_at = NOW() WHERE id = :id');
            $update->execute(array(
                'status' => $localStatus,
                'reference' => $reference,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => $orderId,
            ));

            $message = 'Sipariş sağlayıcıya iletildi.';
            if ($localStatus === 'completed') {
                $message = 'Ürün sağlayıcıdan teslim edildi.';
            }

            return array('success' => true, 'status' => $localStatus, 'message' => $message);
        }

        $errorMessage = isset($response['message']) ? (string) $response['message'] : 'Sağlayıcı siparişi reddetti.';
        $metadata['provider_error'] = $response;

        $update = $pdo->prepare('UPDATE product_orders SET external_metadata = :metadata, admin_note = :admin_note, updated_at = NOW() WHERE id = :id');
        $update->execute(array(
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'admin_note' => $errorMessage,
            'id' => $orderId,
        ));

        return array('success' => false, 'status' => 'pending', 'message' => $errorMessage);
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

    /**
     * @param array<string,mixed> $order
     * @param array<string,mixed> $append
     * @return array<string,mixed>
     */
    private static function mergeMetadata(array $order, array $append): array
    {
        $metadata = array();
        if (!empty($order['external_metadata'])) {
            $decoded = json_decode((string) $order['external_metadata'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        foreach ($append as $key => $value) {
            $metadata[$key] = $value;
        }

        return $metadata;
    }
}
