<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Services\ProductOrderService;

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Yetkilendirme başarısız.']);
    exit;
}

$user = $_SESSION['user'];
if (Auth::isAdminRole($user['role'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Yalnızca bayi kullanıcıları işlem yapabilir.']);
    exit;
}

$payload = $_POST;
$csrf = isset($payload['csrf_token']) ? $payload['csrf_token'] : '';
if (!Helpers::verifyCsrf($csrf)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Güvenlik doğrulaması başarısız oldu.']);
    exit;
}

$action = isset($payload['action']) ? (string)$payload['action'] : '';
$pdo = Database::connection();

try {
    switch ($action) {
        case 'toggle_favorite':
            $productId = isset($payload['product_id']) ? (int)$payload['product_id'] : 0;
            if ($productId <= 0) {
                throw new RuntimeException('Geçersiz ürün seçimi.');
            }
            $exists = $pdo->prepare('SELECT id FROM reseller_favorites WHERE user_id = :user_id AND product_id = :product_id');
            $exists->execute(['user_id' => $user['id'], 'product_id' => $productId]);
            $row = $exists->fetch();
            if ($row) {
                $pdo->prepare('DELETE FROM reseller_favorites WHERE id = :id')->execute(['id' => $row['id']]);
                echo json_encode(['success' => true, 'favorited' => false]);
            } else {
                $pdo->prepare('INSERT INTO reseller_favorites (user_id, product_id, created_at) VALUES (:user_id, :product_id, NOW())')->execute([
                    'user_id' => $user['id'],
                    'product_id' => $productId,
                ]);
                echo json_encode(['success' => true, 'favorited' => true]);
            }
            break;

        case 'toggle_watch':
            $productId = isset($payload['product_id']) ? (int)$payload['product_id'] : 0;
            if ($productId <= 0) {
                throw new RuntimeException('Geçersiz ürün seçimi.');
            }
            $exists = $pdo->prepare('SELECT id FROM reseller_stock_watchers WHERE user_id = :user_id AND product_id = :product_id');
            $exists->execute(['user_id' => $user['id'], 'product_id' => $productId]);
            $row = $exists->fetch();
            if ($row) {
                $pdo->prepare('DELETE FROM reseller_stock_watchers WHERE id = :id')->execute(['id' => $row['id']]);
                echo json_encode(['success' => true, 'watching' => false]);
            } else {
                $pdo->prepare('INSERT INTO reseller_stock_watchers (user_id, product_id, created_at) VALUES (:user_id, :product_id, NOW())')->execute([
                    'user_id' => $user['id'],
                    'product_id' => $productId,
                ]);
                echo json_encode(['success' => true, 'watching' => true]);
            }
            break;

        case 'repeat_order':
            $orderId = isset($payload['order_id']) ? (int)$payload['order_id'] : 0;
            if ($orderId <= 0) {
                throw new RuntimeException('Geçersiz sipariş numarası.');
            }
            $stmt = $pdo->prepare('SELECT product_id FROM product_orders WHERE id = :id AND user_id = :user_id LIMIT 1');
            $stmt->execute(['id' => $orderId, 'user_id' => $user['id']]);
            $order = $stmt->fetch();
            if (!$order) {
                throw new RuntimeException('Sipariş bulunamadı.');
            }
            $result = ProductOrderService::placePanelOrder($user, (int)$order['product_id'], 'Tekrar sipariş #' . $orderId);
            if (!$result['success']) {
                throw new RuntimeException(isset($result['message']) ? $result['message'] : 'Sipariş oluşturulamadı.');
            }
            echo json_encode(['success' => true, 'order_id' => $result['order_id'], 'status' => $result['status']]);
            break;

        case 'save_auto_topup':
            $threshold = isset($payload['threshold']) ? (float)$payload['threshold'] : 0;
            $amount = isset($payload['amount']) ? (float)$payload['amount'] : 0;
            $method = isset($payload['method']) ? (string)$payload['method'] : '';
            if ($threshold <= 0 || $amount <= 0 || $method === '') {
                throw new RuntimeException('Otomatik bakiye ayarları eksik.');
            }
            $pdo->prepare('DELETE FROM balance_auto_topups WHERE user_id = :user_id')->execute(['user_id' => $user['id']]);
            $pdo->prepare('INSERT INTO balance_auto_topups (user_id, threshold, topup_amount, payment_method, status, created_at) VALUES (:user_id, :threshold, :amount, :method, "active", NOW())')->execute([
                'user_id' => $user['id'],
                'threshold' => $threshold,
                'amount' => $amount,
                'method' => $method,
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'remove_auto_topup':
            $pdo->prepare('DELETE FROM balance_auto_topups WHERE user_id = :user_id')->execute(['user_id' => $user['id']]);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new RuntimeException('Desteklenmeyen işlem.');
    }
} catch (RuntimeException $exception) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);

    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Beklenmeyen bir hata oluştu.']);
}
