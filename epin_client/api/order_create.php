<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_post();
require_auth();

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$token = $_POST['csrf_token'] ?? null;

if (!verify_csrf($token)) {
    json_response(['success' => false, 'message' => 'CSRF doğrulaması başarısız.'], 400);
}

if ($productId <= 0) {
    json_response(['success' => false, 'message' => 'Geçersiz ürün.'], 422);
}

$pdo = db();
$userId = (int)session_get('user_id');

try {
    $pdo->beginTransaction();

    $userStmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id FOR UPDATE');
    $userStmt->execute(['id' => $userId]);
    $user = $userStmt->fetch();
    if (!$user) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Kullanıcı bulunamadı.'], 404);
    }

    $productStmt = $pdo->prepare('SELECT id, name, price FROM products WHERE id = :id FOR UPDATE');
    $productStmt->execute(['id' => $productId]);
    $product = $productStmt->fetch();
    if (!$product) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Ürün bulunamadı.'], 404);
    }

    if ((float)$user['balance'] < (float)$product['price']) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Yetersiz bakiye.'], 400);
    }

    $pinStmt = $pdo->prepare('SELECT id, pin_code FROM pins WHERE product_id = :product_id AND status = "available" LIMIT 1 FOR UPDATE');
    $pinStmt->execute(['product_id' => $productId]);
    $pin = $pinStmt->fetch();

    if (!$pin) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Stokta PIN bulunamadı.'], 400);
    }

    $updateBalance = $pdo->prepare('UPDATE users SET balance = balance - :amount WHERE id = :id');
    $updateBalance->execute([
        'amount' => $product['price'],
        'id' => $userId,
    ]);

    $updatePin = $pdo->prepare('UPDATE pins SET status = "used" WHERE id = :id');
    $updatePin->execute(['id' => $pin['id']]);

    $updateProduct = $pdo->prepare('UPDATE products SET stock = GREATEST(stock - 1, 0) WHERE id = :id');
    $updateProduct->execute(['id' => $productId]);

    $orderStmt = $pdo->prepare('INSERT INTO orders (user_id, product_id, pin_id, status, created_at) VALUES (:user_id, :product_id, :pin_id, :status, NOW())');
    $orderStmt->execute([
        'user_id' => $userId,
        'product_id' => $productId,
        'pin_id' => $pin['id'],
        'status' => 'completed',
    ]);

    $pdo->commit();

    json_response([
        'success' => true,
        'pin_code' => sanitize($pin['pin_code']),
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['success' => false, 'message' => 'Sipariş oluşturulamadı.'], 500);
}
