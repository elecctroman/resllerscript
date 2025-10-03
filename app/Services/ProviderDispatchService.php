<?php

namespace App\Services;

use App\Database;
use App\Integrations\ProviderClient;
use App\Notifications\ResellerNotifier;
use App\Settings;
use App\Telegram;
use PDO;
use RuntimeException;

class ProviderDispatchService
{
    private const PROVIDER_CODE = 'lotus';

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

        $providerCode = isset($order['provider_code']) ? trim((string) $order['provider_code']) : '';
        $providerProductId = isset($order['provider_product_id']) ? trim((string) $order['provider_product_id']) : '';

        if ($providerCode === '' || $providerProductId === '') {
            return array('success' => false, 'reason' => 'Sipariş bir sağlayıcıya bağlı değil.');
        }

        if (strtolower($providerCode) !== self::PROVIDER_CODE) {
            return array('success' => false, 'reason' => 'Desteklenmeyen sağlayıcı.');
        }

        $apiUrl = Settings::get('provider_api_url');
        $apiKey = Settings::get('provider_api_key');

        if (!$apiUrl || !$apiKey) {
            return array('success' => false, 'reason' => 'Sağlayıcı API bilgileri yapılandırılmamış.');
        }

        try {
            $client = new ProviderClient($apiUrl, $apiKey);
        } catch (RuntimeException $exception) {
            return array('success' => false, 'reason' => $exception->getMessage());
        }

        $note = isset($order['note']) && $order['note'] !== null ? (string) $order['note'] : '';

        try {
            $response = $client->createOrder((int) $providerProductId, $note);
        } catch (RuntimeException $exception) {
            self::storeProviderFailure($orderId, $order, $exception->getMessage());
            return array('success' => false, 'reason' => $exception->getMessage());
        }

        $remoteData = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();
        $remoteStatus = isset($remoteData['status']) ? strtolower((string) $remoteData['status']) : '';
        $remoteOrderId = isset($remoteData['order_id']) ? (string) $remoteData['order_id'] : null;
        $deliveryContent = isset($remoteData['content']) ? (string) $remoteData['content'] : '';

        $metadata = array();
        if (!empty($order['external_metadata'])) {
            $decoded = json_decode($order['external_metadata'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $metadata['provider'] = self::PROVIDER_CODE;
        $metadata['provider_response'] = $response;
        if ($deliveryContent !== '') {
            $metadata['delivery_content'] = $deliveryContent;
        }

        $newStatus = 'processing';
        $adminNote = isset($order['admin_note']) ? $order['admin_note'] : null;

        if ($deliveryContent !== '') {
            $adminNote = $deliveryContent;
        } elseif (isset($remoteData['message']) && $remoteData['message'] !== '') {
            $adminNote = (string) $remoteData['message'];
        }

        $shouldNotify = false;

        if ($remoteStatus === 'completed') {
            $newStatus = 'completed';
            $shouldNotify = $order['status'] !== 'completed';
        } elseif ($remoteStatus === 'pending') {
            $newStatus = 'processing';
        } elseif ($remoteStatus === 'failed') {
            $newStatus = 'pending';
        }

        $update = $pdo->prepare('UPDATE product_orders SET status = :status, external_reference = :reference, external_metadata = :metadata, admin_note = :admin_note, updated_at = NOW() WHERE id = :id');
        $update->execute(array(
            'status' => $newStatus,
            'reference' => $remoteOrderId ?: $order['external_reference'],
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'admin_note' => $adminNote !== null && $adminNote !== '' ? $adminNote : null,
            'id' => $orderId,
        ));

        if ($shouldNotify && $newStatus === 'completed') {
            $orderPayload = array(
                'id' => $orderId,
                'product_name' => $order['product_name'],
                'quantity' => isset($order['quantity']) ? (int) $order['quantity'] : 1,
                'price' => isset($order['price']) ? (float) $order['price'] : 0.0,
                'admin_note' => $adminNote,
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
                "🤖 Sağlayıcı siparişi tamamlandı!\nBayi: %s\nÜrün: %s\nSipariş No: #%d",
                $order['user_name'],
                $order['product_name'],
                $orderId
            ));
        }

        return array(
            'success' => true,
            'status' => $newStatus,
            'reference' => $remoteOrderId,
        );
    }

    /**
     * @param array<int,int> $orderIds
     * @return void
     */
    public static function dispatchProductOrders(array $orderIds)
    {
        foreach ($orderIds as $orderId) {
            try {
                self::dispatchProductOrder($orderId);
            } catch (\Throwable $exception) {
                error_log('[ProviderDispatch] Sipariş #' . (int) $orderId . ' gönderilemedi: ' . $exception->getMessage());
            }
        }
    }

    private static function storeProviderFailure(int $orderId, array $order, string $message): void
    {
        $pdo = Database::connection();

        $metadata = array();
        if (!empty($order['external_metadata'])) {
            $decoded = json_decode($order['external_metadata'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $metadata['provider_error'] = array(
            'message' => $message,
            'time' => date('c'),
        );

        $pdo->prepare('UPDATE product_orders SET external_metadata = :metadata, admin_note = :admin_note, updated_at = NOW() WHERE id = :id')
            ->execute(array(
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'admin_note' => $message,
                'id' => $orderId,
            ));
    }
}
