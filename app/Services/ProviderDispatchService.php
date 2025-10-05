<?php

namespace App\Services;

use App\Database;
use App\Notifications\ResellerNotifier;
use App\Telegram;
use App\Services\ProviderManager;
use App\Services\ProviderApiClient;
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
        $stmt = $pdo->prepare('SELECT po.*, p.name AS product_name, p.provider_code, p.provider_product_id, p.automatic_delivery, u.name AS user_name, u.email AS user_email, u.notify_order_completed, u.telegram_bot_token, u.telegram_chat_id FROM product_orders po INNER JOIN products p ON po.product_id = p.id INNER JOIN users u ON po.user_id = u.id WHERE po.id = :id LIMIT 1');
        $stmt->execute(array('id' => $orderId));
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return array('success' => false, 'reason' => 'Sipariş bulunamadı.');
        }

        $providerCode = isset($order['provider_code']) ? strtolower((string) $order['provider_code']) : '';
        $providerProductId = isset($order['provider_product_id']) ? trim((string) $order['provider_product_id']) : '';

        $automaticDelivery = isset($order['automatic_delivery']) ? (int)$order['automatic_delivery'] === 1 : false;

        if ($automaticDelivery && ($providerCode === '' || $providerCode === 'panel' || $providerCode === 'stock')) {
            $complete = $pdo->prepare('UPDATE product_orders SET status = :status, admin_note = :note, updated_at = NOW() WHERE id = :id');
            $complete->execute(array(
                'status' => 'completed',
                'note' => 'Sipariş otomatik teslim edildi.',
                'id' => $orderId,
            ));

            self::notifyIfCompleted($orderId);

            return array('success' => true, 'status' => 'completed', 'message' => 'Sipariş otomatik teslim edildi.');
        }

        if ($providerCode === '' || $providerCode === 'panel' || $providerCode === 'stock') {
            return ProductStockService::deliverOrderFromStock($orderId);
        }

        if ($providerProductId === '') {
            return array('success' => false, 'reason' => 'Sağlayıcı ürünü eşlenmemiş.');
        }

        $provider = ProviderManager::findByCode($providerCode);
        if (!$provider) {
            $metadata = self::mergeMetadata($order, array(
                'provider' => $providerCode,
                'provider_error' => array('message' => 'Sağlayıcı yapılandırması bulunamadı.'),
            ));

            self::updateOrderMetadata($orderId, 'pending', $order['external_reference'] ?? null, $metadata, 'Sağlayıcı tanımlı değil.');

            return array('success' => false, 'reason' => 'Sağlayıcı yapılandırması bulunamadı.');
        }

        if (($provider['status'] ?? '') !== 'active') {
            $metadata = self::mergeMetadata($order, array(
                'provider' => $providerCode,
                'provider_error' => array('message' => 'Sağlayıcı pasif durumda.'),
            ));

            self::updateOrderMetadata($orderId, 'pending', $order['external_reference'] ?? null, $metadata, 'Sağlayıcı pasif.');

            return array('success' => false, 'reason' => 'Sağlayıcı pasif durumda.');
        }

        $payload = array('product_id' => $providerProductId);
        $note = isset($order['note']) ? trim((string) $order['note']) : '';
        if ($note !== '') {
            $payload['note'] = $note;
        }

        $apiResult = ProviderApiClient::createOrder($provider, $payload);

        $metadata = self::mergeMetadata($order, array(
            'provider' => $providerCode,
            'provider_payload' => $payload,
        ));

        if (!empty($apiResult['success'])) {
            $metadata['provider_response'] = $apiResult;

            $responseBody = isset($apiResult['body']) && is_array($apiResult['body']) ? $apiResult['body'] : array();
            $responseData = array();

            if (isset($apiResult['data']) && is_array($apiResult['data'])) {
                $responseData = $apiResult['data'];
            } elseif (isset($responseBody['data']) && is_array($responseBody['data'])) {
                $responseData = $responseBody['data'];
            } elseif (isset($responseBody['order']) && is_array($responseBody['order'])) {
                $responseData = $responseBody['order'];
            } elseif (isset($responseBody['result']) && is_array($responseBody['result'])) {
                $responseData = $responseBody['result'];
            }

            if (isset($responseData[0]) && is_array($responseData[0])) {
                $responseData = $responseData[0];
            }

            $statusSources = array(
                $responseData['status'] ?? null,
                $responseData['state'] ?? null,
                $responseData['order_status'] ?? null,
                $responseBody['status'] ?? null,
                $responseBody['result']['status'] ?? null,
            );

            $remoteStatus = '';
            foreach ($statusSources as $statusSource) {
                if (is_string($statusSource) && $statusSource !== '') {
                    $remoteStatus = strtolower($statusSource);
                    break;
                }
            }

            if ($remoteStatus === '' && isset($responseBody['success']) && $responseBody['success']) {
                $remoteStatus = 'completed';
            }

            switch ($remoteStatus) {
                case 'success':
                case 'ok':
                case 'done':
                case 'completed':
                    $remoteStatus = 'completed';
                    break;
                case 'waiting':
                case 'queued':
                case 'queue':
                case 'processing':
                case 'pending':
                case 'in_progress':
                case 'in-progress':
                    $remoteStatus = 'processing';
                    break;
                case 'cancel':
                case 'cancelled':
                case 'canceled':
                    $remoteStatus = 'cancelled';
                    break;
                case 'error':
                case 'fail':
                case 'failed':
                case 'denied':
                    $remoteStatus = 'failed';
                    break;
            }

            $referenceKeys = array('order_id', 'orderId', 'orderID', 'id', 'reference', 'ref', 'transaction_id', 'transactionId', 'order_no', 'orderNo');
            $remoteReference = null;
            foreach ($referenceKeys as $referenceKey) {
                if (isset($responseData[$referenceKey]) && $responseData[$referenceKey] !== '') {
                    $remoteReference = (string) $responseData[$referenceKey];
                    break;
                }
                if (isset($responseBody[$referenceKey]) && $responseBody[$referenceKey] !== '') {
                    $remoteReference = (string) $responseBody[$referenceKey];
                    break;
                }
            }

            $contentKeys = array('content', 'delivery', 'details', 'detail', 'message', 'note', 'notes', 'response');
            $remoteContent = null;
            foreach ($contentKeys as $contentKey) {
                if (isset($responseData[$contentKey]) && is_string($responseData[$contentKey]) && $responseData[$contentKey] !== '') {
                    $remoteContent = (string) $responseData[$contentKey];
                    break;
                }
            }
            if ($remoteContent === null && isset($responseBody['message']) && is_string($responseBody['message']) && $responseBody['message'] !== '') {
                $remoteContent = (string) $responseBody['message'];
            }

            $localStatus = 'processing';
            $message = 'Sipariş sağlayıcıya iletildi.';
            $adminNote = null;

            if ($remoteStatus === 'completed') {
                $localStatus = 'completed';
                $message = 'Sipariş sağlayıcı tarafından tamamlandı.';
                $adminNote = $remoteContent;
            } elseif ($remoteStatus === 'pending' || $remoteStatus === 'processing' || $remoteStatus === '') {
                $localStatus = 'processing';
                $message = 'Sipariş sağlayıcı kuyruğuna alındı.';
                if ($remoteContent) {
                    $adminNote = $remoteContent;
                }
            } elseif ($remoteStatus === 'cancelled') {
                $localStatus = 'pending';
                $message = 'Sağlayıcı siparişi iptal etti.';
                $adminNote = $remoteContent ?: 'Sağlayıcı siparişi iptal etti.';
            } elseif ($remoteStatus === 'failed') {
                $localStatus = 'pending';
                $message = isset($responseBody['message']) ? (string) $responseBody['message'] : (isset($responseData['message']) ? (string) $responseData['message'] : 'Sağlayıcı siparişi reddetti.');
                $adminNote = $remoteContent ?: $message;
            }

            self::updateOrderMetadata($orderId, $localStatus, $remoteReference, $metadata, $adminNote);

            if ($localStatus === 'completed') {
                self::notifyIfCompleted($orderId);
            }

            return array('success' => true, 'status' => $localStatus, 'message' => $message);
        }

        $errorMessage = isset($apiResult['error']) ? (string) $apiResult['error'] : 'Sağlayıcı siparişi oluşturulamadı.';
        if (isset($apiResult['body']['message'])) {
            $errorMessage = (string) $apiResult['body']['message'];
        }

        $metadata['provider_error'] = array(
            'message' => $errorMessage,
            'status_code' => isset($apiResult['status_code']) ? (int) $apiResult['status_code'] : null,
        );

        self::updateOrderMetadata($orderId, 'pending', $order['external_reference'] ?? null, $metadata, $errorMessage);

        return array('success' => false, 'reason' => $errorMessage);
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

    private static function mergeMetadata(array $order, array $updates): array
    {
        $metadata = array();
        if (!empty($order['external_metadata'])) {
            $decoded = json_decode((string) $order['external_metadata'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        foreach ($updates as $key => $value) {
            $metadata[$key] = $value;
        }

        return $metadata;
    }

    private static function updateOrderMetadata(int $orderId, string $status, ?string $reference, array $metadata, ?string $adminNote): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE product_orders SET status = :status, external_reference = :reference, external_metadata = :metadata, admin_note = :admin_note, updated_at = NOW() WHERE id = :id');
        $stmt->execute(array(
            'status' => $status,
            'reference' => $reference,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'admin_note' => $adminNote,
            'id' => $orderId,
        ));
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
