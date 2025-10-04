<?php
require __DIR__ . '/includes/customer_api.php';

use App\Database;

header('Content-Type: application/json');

customerApiRequireScope('read');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    customerApiLog($customer['id'], '/api/products.php', 405);
    echo json_encode(array('success' => false, 'message' => 'Method Not Allowed'));
    exit;
}

$pdo = Database::connection();
$products = $pdo->query("SELECT id, name, price, description FROM products WHERE status = 'active' ORDER BY name ASC")->fetchAll();

customerApiLog($customer['id'], '/api/products.php', 200);

echo json_encode(array(
    'success' => true,
    'data' => array_map(function ($row) {
        return array(
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => (float)$row['price'],
        );
    }, $products),
));
