<?php
require __DIR__ . '/includes/customer_api.php';

use App\Customers\OrderService;
use App\Database;

header('Content-Type: application/json');

$pdo = Database::connection();

customerApiRequireScope('orders');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT co.*, p.name AS product_name FROM customer_orders co INNER JOIN products p ON p.id = co.product_id WHERE co.customer_id = :customer ORDER BY co.created_at DESC');
    $stmt->execute(array(':customer' => $customer['id']));
    $orders = $stmt->fetchAll();
    customerApiLog($customer['id'], '/api/orders.php', 200);
    echo json_encode(array('success' => true, 'data' => $orders));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
    $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
    $paymentMethod = isset($input['payment_method']) ? $input['payment_method'] : 'Cuzdan';

    if ($productId <= 0) {
        customerApiLog($customer['id'], '/api/orders.php', 422);
        http_response_code(422);
        echo json_encode(array('success' => false, 'message' => 'product_id alanı zorunludur.'));
        exit;
    }

    $productStmt = $pdo->prepare("SELECT id, price FROM products WHERE id = :id AND status = 'active' LIMIT 1");
    $productStmt->execute(array(':id' => $productId));
    $product = $productStmt->fetch();
    if (!$product) {
        customerApiLog($customer['id'], '/api/orders.php', 404);
        http_response_code(404);
        echo json_encode(array('success' => false, 'message' => 'Ürün bulunamadı.'));
        exit;
    }

    $quantity = max(1, $quantity);
    $total = (float)$product['price'] * $quantity;

    try {
        $orderId = OrderService::placeOrder((int)$customer['id'], (int)$product['id'], $quantity, $total, $paymentMethod, array('source' => 'api'));
        customerApiLog($customer['id'], '/api/orders.php', 201);
        http_response_code(201);
        echo json_encode(array('success' => true, 'order_id' => $orderId, 'status' => $paymentMethod === 'Cuzdan' ? 'onaylandi' : 'bekliyor'));
        exit;
    } catch (\Throwable $exception) {
        customerApiLog($customer['id'], '/api/orders.php', 400);
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => $exception->getMessage()));
        exit;
    }
}

customerApiLog($customer['id'], '/api/orders.php', 405);
http_response_code(405);
echo json_encode(array('success' => false, 'message' => 'Method Not Allowed'));
