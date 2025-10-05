<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_post();
require_auth();

$current = (string)($_POST['current_password'] ?? '');
$new = (string)($_POST['new_password'] ?? '');
$confirm = (string)($_POST['new_password_confirm'] ?? '');
$token = $_POST['csrf_token'] ?? null;

if (!verify_csrf($token)) {
    json_response(['success' => false, 'message' => 'CSRF doğrulaması başarısız.'], 400);
}

if ($current === '' || $new === '') {
    json_response(['success' => false, 'message' => 'Tüm alanları doldurun.'], 422);
}

if (mb_strlen($new) < 6) {
    json_response(['success' => false, 'message' => 'Yeni şifre en az 6 karakter olmalıdır.'], 422);
}

if ($new !== $confirm) {
    json_response(['success' => false, 'message' => 'Yeni şifreler eşleşmiyor.'], 422);
}

$pdo = db();
$userId = (int)session_get('user_id');
$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

if (!$user || !password_verify($current, $user['password_hash'])) {
    json_response(['success' => false, 'message' => 'Mevcut şifre hatalı.'], 422);
}

$update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
$update->execute([
    'hash' => password_hash($new, PASSWORD_BCRYPT),
    'id' => $userId,
]);

json_response(['success' => true]);
