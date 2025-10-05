<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_auth();

$pdo = db();
$userId = (int)session_get('user_id');
$stmt = $pdo->prepare('SELECT o.id, o.status, o.created_at, p.name AS product_name, pn.pin_code FROM orders o INNER JOIN products p ON p.id = o.product_id LEFT JOIN pins pn ON pn.id = o.pin_id WHERE o.user_id = :user_id ORDER BY o.created_at DESC');
$stmt->execute(['user_id' => $userId]);

$statusLabels = [
    'completed' => 'Tamamlandı',
    'pending' => 'Beklemede',
    'cancelled' => 'İptal',
];

$orders = [];
foreach ($stmt->fetchAll() as $order) {
    $status = $order['status'] ?? 'completed';
    $orders[] = [
        'id' => (int)$order['id'],
        'product_name' => sanitize($order['product_name'] ?? ''),
        'created_at' => $order['created_at'],
        'status' => $status,
        'status_label' => $statusLabels[$status] ?? ucfirst($status),
        'pin_code' => $order['pin_code'] ? sanitize($order['pin_code']) : null,
    ];
}

json_response(['orders' => $orders]);
