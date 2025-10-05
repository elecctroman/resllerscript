<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_auth();

$pdo = db();
$stmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id');
$stmt->execute(['id' => (int)session_get('user_id')]);
$balance = $stmt->fetchColumn();

json_response(['balance' => number_format((float)$balance, 2, '.', '')]);
