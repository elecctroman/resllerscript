<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_post();
require_auth();

$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
$method = trim((string)($_POST['method'] ?? ''));
$token = $_POST['csrf_token'] ?? null;

if (!verify_csrf($token)) {
    json_response(['success' => false, 'message' => 'CSRF doğrulaması başarısız.'], 400);
}

if ($amount < 10) {
    json_response(['success' => false, 'message' => 'Minimum yükleme tutarı 10 TL olmalıdır.'], 422);
}

if ($method === '') {
    json_response(['success' => false, 'message' => 'Ödeme yöntemi seçiniz.'], 422);
}

$pdo = db();
$insert = $pdo->prepare('INSERT INTO payments (user_id, amount, method, status, created_at) VALUES (:user_id, :amount, :method, :status, NOW())');
$insert->execute([
    'user_id' => (int)session_get('user_id'),
    'amount' => $amount,
    'method' => $method,
    'status' => 'pending',
]);

json_response(['success' => true, 'message' => 'Talebiniz alındı. Ödeme onaylandığında bakiyenize yansıtılacaktır.']);
