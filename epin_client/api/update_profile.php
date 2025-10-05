<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_post();
require_auth();

$name = trim((string)($_POST['name'] ?? ''));
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$token = $_POST['csrf_token'] ?? null;

if (!verify_csrf($token)) {
    json_response(['success' => false, 'message' => 'CSRF doğrulaması başarısız.'], 400);
}

if ($name === '' || !$email) {
    json_response(['success' => false, 'message' => 'Geçerli bir ad ve e-posta giriniz.'], 422);
}

$pdo = db();
$userId = (int)session_get('user_id');

$exists = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
$exists->execute(['email' => $email, 'id' => $userId]);
if ($exists->fetch()) {
    json_response(['success' => false, 'message' => 'Bu e-posta adresi başka bir hesap tarafından kullanılıyor.'], 422);
}

$update = $pdo->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id');
$update->execute([
    'name' => $name,
    'email' => $email,
    'id' => $userId,
]);

json_response(['success' => true]);
