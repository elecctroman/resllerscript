<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_auth();

$pdo = db();
$userId = (int)session_get('user_id');

$balanceStmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id');
$balanceStmt->execute(['id' => $userId]);
$balance = $balanceStmt->fetchColumn();

$orderStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :user_id');
$orderStmt->execute(['user_id' => $userId]);
$orderCount = (int)$orderStmt->fetchColumn();

$ticketStmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE user_id = :user_id AND status != "closed"');
$ticketStmt->execute(['user_id' => $userId]);
$openTickets = (int)$ticketStmt->fetchColumn();

$quickStmt = $pdo->prepare('SELECT id, name, price, stock, description FROM products ORDER BY id DESC LIMIT 6');
try {
    $quickStmt->execute();
    $quickProductsRaw = $quickStmt->fetchAll();
} catch (PDOException) {
    $quickProductsRaw = [];
}

$quickProducts = array_map(static function (array $product): array {
    return [
        'id' => (int)$product['id'],
        'name' => sanitize($product['name']),
        'price' => number_format((float)$product['price'], 2, '.', ''),
        'stock' => (int)$product['stock'],
        'description' => sanitize($product['description'] ?? ''),
    ];
}, $quickProductsRaw);

json_response([
    'balance' => number_format((float)$balance, 2, '.', ''),
    'order_count' => $orderCount,
    'open_tickets' => $openTickets,
    'quick_products' => $quickProducts,
]);
