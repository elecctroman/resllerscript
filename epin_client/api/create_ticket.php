<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_post();
require_auth();

$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$token = $_POST['csrf_token'] ?? null;

if (!verify_csrf($token)) {
    json_response(['success' => false, 'message' => 'CSRF doğrulaması başarısız.'], 400);
}

if ($subject === '' || $message === '') {
    json_response(['success' => false, 'message' => 'Lütfen konu ve mesaj alanlarını doldurun.'], 422);
}

$pdo = db();
$stmt = $pdo->prepare('INSERT INTO tickets (user_id, subject, message, status, created_at) VALUES (:user_id, :subject, :message, :status, NOW())');
$stmt->execute([
    'user_id' => (int)session_get('user_id'),
    'subject' => $subject,
    'message' => $message,
    'status' => 'open',
]);

json_response(['success' => true]);
