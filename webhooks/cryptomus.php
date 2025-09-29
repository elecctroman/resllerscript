<?php
require __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Mailer;
use App\Helpers;
use App\Payments\CryptomusClient;
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

$headers = function_exists('getallheaders') ? getallheaders() : [];
$signature = '';
if (isset($headers['sign'])) {
    $signature = $headers['sign'];
} elseif (isset($headers['Sign'])) {
    $signature = $headers['Sign'];
} elseif (isset($_SERVER['HTTP_SIGN'])) {
    $signature = $_SERVER['HTTP_SIGN'];
}

try {
    $client = new CryptomusClient();
} catch (\RuntimeException $exception) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Ödeme sağlayıcısı yapılandırılmadı']);
    exit;
}

if ($signature === '' || !$client->verifySignature($data, $signature)) {
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

$paidStates = ['paid', 'paid_over', 'paid_partial', 'paid_overpayment', 'confirm_check'];
$isPaid = in_array($status, $paidStates, true) || strpos($status, 'paid') !== false;

if (!$isPaid) {
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
                'provider' => 'cryptomus',
                'reference' => isset($data['uuid']) ? $data['uuid'] : $orderReference,
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
    $stmt = $pdo->prepare('SELECT br.*, u.name, u.email FROM balance_requests br INNER JOIN users u ON br.user_id = u.id WHERE br.id = :id LIMIT 1');
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
                'provider' => 'cryptomus',
                'reference' => isset($data['uuid']) ? $data['uuid'] : $orderReference,
                'id' => $requestId,
            ]);

        $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')
            ->execute([
                'user_id' => $request['user_id'],
                'amount' => $request['amount'],
                'type' => 'credit',
                'description' => 'Cryptomus ödeme onayı',
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

    $message = "Bakiye yükleme talebiniz başarıyla tamamlandı.\nTutar: " . Helpers::formatCurrency((float)$request['amount'], 'USD');
    Mailer::send($request['email'], 'Bakiye Yükleme Onayı', $message);

    Telegram::notify(sprintf(
        "Yeni bakiye yüklemesi tamamlandı!\nBayi: %s\nTutar: %s",
        $request['name'],
        Helpers::formatCurrency((float)$request['amount'], 'USD')
    ));

    echo json_encode(['status' => 'ok']);
    exit;
}

echo json_encode(['status' => 'ok', 'message' => 'Bilinmeyen sipariş referansı']);
