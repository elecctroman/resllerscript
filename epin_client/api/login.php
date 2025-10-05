<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_post();

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = (string)($_POST['password'] ?? '');
$token = $_POST['csrf_token'] ?? null;

if (!verify_csrf($token)) {
    json_response(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 400);
}

if (!$email || $password === '') {
    json_response(['success' => false, 'message' => 'E-posta ve şifre gereklidir.'], 422);
}

if (!attempt_login($email, $password)) {
    json_response(['success' => false, 'message' => 'Bilgiler doğrulanamadı.'], 401);
}

json_response(['success' => true]);
