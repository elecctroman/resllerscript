<?php
require __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Helpers;
use App\Notifications\ResellerNotifier;
use App\Payments\HeleketClient;
use App\Services\PackageOrderService;
use App\Telegram;

header('Content-Type: application/json');

$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz JSON']);
    exit;
}

$signatureHeader = '';
if (isset($_SERVER['HTTP_X_SIGNATURE'])) {
    $signatureHeader = $_SERVER['HTTP_X_SIGNATURE'];
} elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['X-Signature'])) {
        $signatureHeader = $headers['X-Signature'];
    } elseif (isset($headers['x-signature'])) {
        $signatureHeader = $headers['x-signature'];
    }
}

try {
    $client = new HeleketClient();
} catch (\RuntimeException $exception) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Ödeme sağlayıcısı yapılandırılmadı']);
    exit;
}

$payloadForVerify = $data;
unset($payloadForVerify['signature']);

if ($signatureHeader === '' || !$client->verifySignature($payloadForVerify, $signatureHeader)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'İmza doğrulanamadı']);
    exit;
}

$status = isset($data['status']) ? strtolower((string)$data['status']) : '';
$orderReference = isset($data['order_id']) ? (string)$data['order_id'] : '';

if ($orderReference === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Sipariş numarası eksik']);
    exit;
}

$paidStates = ['paid', 'completed', 'success'];
if (!in_array($status, $paidStates, true)) {
    echo json_encode(['status' => 'ok', 'message' => 'Ödeme henüz tamamlanmadı']);
    exit;
}

$pdo = Database::connection();

if (strpos($orderReference, 'PKG-') === 0) {
    $orderId = (int)substr($orderReference, 4);
    $order = PackageOrderService::loadOrder($orderId);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Paket siparişi bulunamadı']);
        exit;
    }

    if ($order['status'] === 'completed') {
        echo json_encode(['status' => 'ok', 'message' => 'Sipariş zaten tamamlandı']);
        exit;
    }

    try {
        $pdo->prepare('UPDATE package_orders SET status = :status, payment_provider = :provider, payment_reference = :reference, updated_at = NOW() WHERE id = :id')
            ->execute([
                'status' => 'paid',
                'provider' => 'heleket',
                'reference' => isset($data['payment_id']) ? (string)$data['payment_id'] : $orderReference,
                'id' => $orderId,
            ]);

        PackageOrderService::fulfill($order);
        PackageOrderService::markCompleted($orderId, $order);
    } catch (\Throwable $exception) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Sipariş güncellenirken hata oluştu']);
        exit;
    }

    echo json_encode(['status' => 'ok']);
    exit;
}

if (strpos($orderReference, 'BAL-') === 0) {
    $requestId = (int)substr($orderReference, 4);
    $stmt = $pdo->prepare('SELECT br.*, u.name, u.email, u.telegram_bot_token, u.telegram_chat_id, u.notify_balance_approved FROM balance_requests br INNER JOIN users u ON br.user_id = u.id WHERE br.id = :id LIMIT 1');
    $stmt->execute(['id' => $requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Bakiye talebi bulunamadı']);
        exit;
    }

    if ($request['status'] !== 'pending') {
        echo json_encode(['status' => 'ok', 'message' => 'Talep zaten güncellenmiş']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare('UPDATE balance_requests SET status = :status, payment_provider = :provider, payment_reference = :reference, processed_at = NOW() WHERE id = :id')
            ->execute([
                'status' => 'approved',
                'provider' => 'heleket',
                'reference' => isset($data['payment_id']) ? (string)$data['payment_id'] : $orderReference,
                'id' => $requestId,
            ]);

        $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')
            ->execute([
                'user_id' => $request['user_id'],
                'amount' => $request['amount'],
                'type' => 'credit',
                'description' => 'Heleket ödeme onayı',
            ]);

        $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')
            ->execute([
                'amount' => $request['amount'],
                'id' => $request['user_id'],
            ]);

        $pdo->commit();
    } catch (\Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Bakiye güncellenemedi']);
        exit;
    }

    $requestPayload = array(
        'amount' => (float)$request['amount'],
        'payment_method' => 'Heleket',
        'reference' => isset($data['payment_id']) ? $data['payment_id'] : $orderReference,
    );
    ResellerNotifier::sendBalanceApproved($request, $requestPayload, 'Ödeme sağlayıcısı tarafından onaylandı.');

    Telegram::notify(sprintf(
        "Heleket üzerinden yeni bakiye yüklemesi tamamlandı!\nBayi: %s\nTutar: %s",
        $request['name'],
        Helpers::formatCurrency((float)$request['amount'], 'USD')
    ));

    echo json_encode(['status' => 'ok']);
    exit;
}

echo json_encode(['status' => 'ok', 'message' => 'Bilinmeyen sipariş referansı']);
