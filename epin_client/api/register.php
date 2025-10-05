<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_post();

$name = trim((string)($_POST['name'] ?? ''));
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = (string)($_POST['password'] ?? '');
$passwordConfirm = (string)($_POST['password_confirm'] ?? '');
$token = $_POST['csrf_token'] ?? null;

if (!verify_csrf($token)) {
    json_response(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 400);
}

if (!$name || !$email || $password === '') {
    json_response(['success' => false, 'message' => 'Tüm alanlar zorunludur.'], 422);
}

if (mb_strlen($password) < 6) {
    json_response(['success' => false, 'message' => 'Şifre en az 6 karakter olmalıdır.'], 422);
}

if ($password !== $passwordConfirm) {
    json_response(['success' => false, 'message' => 'Şifreler eşleşmiyor.'], 422);
}

$result = register_user($name, $email, $password);

json_response($result['success'] ? ['success' => true] : ['success' => false, 'message' => $result['message'] ?? 'Kayıt başarısız.']);
