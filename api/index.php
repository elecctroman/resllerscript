<?php declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Database;
use App\Helpers;
use App\Services\ProductOrderService;

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$prefix = '/api';
if (stripos($uri, $prefix) === 0) {
    $uri = substr($uri, strlen($prefix));
}
$path = trim($uri, '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($path === '' || $path === 'index.php') {
    api_success(
        array(
            'documentation' => Helpers::url('api/docs', true),
        ),
        'API servisi çalışıyor.'
    );
}

switch (true) {
    case $path === 'user' && $method === 'GET':
        $user = api_authenticated_user();
        $response = array(
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'credit' => isset($user['balance']) ? (float) $user['balance'] : 0.0,
            'locale' => isset($user['locale']) ? (string) $user['locale'] : null,
            'currency' => isset($user['currency']) ? (string) $user['currency'] : null,
        );
        api_success($response, 'Kullanıcı bilgileri getirildi.');
        break;

    case $path === 'products' && $method === 'GET':
        api_authenticated_user();
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query(
                'SELECT p.id, p.name, p.description, p.price, p.automatic_delivery, c.name AS category_name,
                        (SELECT COUNT(*) FROM product_stock_items WHERE product_id = p.id AND status = "available") AS stock
                 FROM products p
                 INNER JOIN categories c ON c.id = p.category_id
                 WHERE p.status = "active"
                 ORDER BY p.name ASC'
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $exception) {
            error_log('[API] Ürün listesi alınamadı: ' . $exception->getMessage());
            api_error(500, 'Sunucu hatası.', 'INTERNAL_ERROR');
        }

        $products = array();
        foreach ($rows as $row) {
            $stock = isset($row['stock']) ? (int) $row['stock'] : 0;
            $automatic = isset($row['automatic_delivery']) ? (int) $row['automatic_delivery'] === 1 : false;
            $products[] = array(
                'id' => (int) $row['id'],
                'title' => (string) $row['name'],
                'description' => isset($row['description']) ? (string) $row['description'] : null,
                'amount' => isset($row['price']) ? (float) $row['price'] : 0.0,
                'stock' => $stock,
                'available' => $automatic || $stock > 0,
                'automatic_delivery' => $automatic,
                'category' => isset($row['category_name']) ? (string) $row['category_name'] : null,
            );
        }

        api_success($products, 'Ürün listesi başarıyla getirildi.');
        break;

    case $path === 'orders' && $method === 'GET':
        $user = api_authenticated_user();
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT po.id, po.product_id, po.status, po.note, po.price, po.total_amount, po.created_at, po.updated_at,
                        p.name AS product_name
                 FROM product_orders po
                 INNER JOIN products p ON p.id = po.product_id
                 WHERE po.user_id = :user_id
                 ORDER BY po.created_at DESC'
            );
            $stmt->execute(array('user_id' => $user['id']));
            $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $exception) {
            error_log('[API] Sipariş listesi alınamadı: ' . $exception->getMessage());
            api_error(500, 'Sunucu hatası.', 'INTERNAL_ERROR');
        }

        $items = array();
        foreach ($orders as $order) {
            $items[] = array(
                'id' => (int) $order['id'],
                'product_id' => (int) $order['product_id'],
                'product_title' => isset($order['product_name']) ? (string) $order['product_name'] : null,
                'status' => (string) $order['status'],
                'note' => $order['note'] !== null ? (string) $order['note'] : null,
                'price' => isset($order['price']) ? (float) $order['price'] : null,
                'total_amount' => isset($order['total_amount']) ? (float) $order['total_amount'] : null,
                'created_at' => isset($order['created_at']) ? (string) $order['created_at'] : null,
                'updated_at' => isset($order['updated_at']) ? (string) $order['updated_at'] : null,
            );
        }

        api_success($items, 'Sipariş geçmişi listelendi.');
        break;

    case $path === 'orders' && $method === 'POST':
        $user = api_authenticated_user();
        $payload = api_decode_json();
        $productId = isset($payload['product_id']) ? (int) $payload['product_id'] : 0;
        $note = isset($payload['note']) ? (string) $payload['note'] : null;

        if ($productId <= 0) {
            api_error(400, 'İşlem hatalı.', 'INVALID_PRODUCT');
        }

        $result = ProductOrderService::placeOrder($user, $productId, $note, 'api');
        if (empty($result['success'])) {
            $message = isset($result['message']) ? (string) $result['message'] : 'İşlem hatalı.';
            api_error(400, $message, 'ORDER_FAILED');
        }

        $data = array(
            'order_id' => isset($result['order_id']) ? (int) $result['order_id'] : null,
            'status' => isset($result['status']) ? (string) $result['status'] : 'pending',
        );
        $message = isset($result['message']) ? (string) $result['message'] : 'Sipariş oluşturuldu.';

        api_success($data, $message);
        break;

    case preg_match('#^orders/(\\d+)$#', $path, $matches) === 1 && $method === 'GET':
        $user = api_authenticated_user();
        $orderId = (int) $matches[1];

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT po.id, po.product_id, po.status, po.note, po.price, po.total_amount, po.created_at, po.updated_at,
                        p.name AS product_name
                 FROM product_orders po
                 INNER JOIN products p ON p.id = po.product_id
                 WHERE po.id = :order_id AND po.user_id = :user_id
                 LIMIT 1'
            );
            $stmt->execute(array('order_id' => $orderId, 'user_id' => $user['id']));
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $exception) {
            error_log('[API] Sipariş detayı alınamadı: ' . $exception->getMessage());
            api_error(500, 'Sunucu hatası.', 'INTERNAL_ERROR');
        }

        if (!$order) {
            api_error(404, 'Bulunamadı.', 'NOT_FOUND');
        }

        $content = null;
        try {
            $contentStmt = $pdo->prepare('SELECT content FROM product_stock_items WHERE order_id = :order_id AND status = "delivered" ORDER BY id DESC LIMIT 1');
            $contentStmt->execute(array('order_id' => $orderId));
            $contentRow = $contentStmt->fetch(\PDO::FETCH_ASSOC);
            if ($contentRow && isset($contentRow['content'])) {
                $content = (string) $contentRow['content'];
            }
        } catch (\PDOException $exception) {
            error_log('[API] Sipariş içerik sorgusu başarısız: ' . $exception->getMessage());
        }

        $detail = array(
            'id' => (int) $order['id'],
            'product_id' => (int) $order['product_id'],
            'product_title' => isset($order['product_name']) ? (string) $order['product_name'] : null,
            'status' => (string) $order['status'],
            'note' => $order['note'] !== null ? (string) $order['note'] : null,
            'price' => isset($order['price']) ? (float) $order['price'] : null,
            'total_amount' => isset($order['total_amount']) ? (float) $order['total_amount'] : null,
            'created_at' => isset($order['created_at']) ? (string) $order['created_at'] : null,
            'updated_at' => isset($order['updated_at']) ? (string) $order['updated_at'] : null,
            'content' => $content,
        );

        api_success($detail, 'Sipariş detayı getirildi.');
        break;

    default:
        api_error(404, 'Bulunamadı.', 'NOT_FOUND');
}
