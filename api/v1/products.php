<?php
require __DIR__ . '/../bootstrap.php';

$token = authenticate_token();
require_scope($token, 'read');

try {
    $pdo = App\Database::connection();

    $productStmt = $pdo->prepare(
        "SELECT pr.id, pr.name, pr.description, pr.price, pr.status, pr.provider_code,
                COALESCE(SUM(CASE WHEN psi.status = 'available' THEN 1 ELSE 0 END), 0) AS stock_count
         FROM products pr
         LEFT JOIN product_stock_items psi ON psi.product_id = pr.id
         WHERE pr.status = :status
         GROUP BY pr.id, pr.name, pr.description, pr.price, pr.status, pr.provider_code
         ORDER BY pr.name ASC"
    );
    $productStmt->execute(array('status' => 'active'));
    $products = $productStmt->fetchAll();

    $response = array();
    foreach ($products as $product) {
        $providerCode = isset($product['provider_code']) ? strtolower((string)$product['provider_code']) : '';
        $stockCount = isset($product['stock_count']) ? (int)$product['stock_count'] : 0;
        $available = ($product['status'] === 'active');
        if ($available) {
            if ($providerCode === '' || $providerCode === 'stock' || $providerCode === 'panel') {
                $available = $stockCount > 0;
            }
        }

        $response[] = array(
            'id' => (int)$product['id'],
            'title' => $product['name'],
            'content' => isset($product['description']) && $product['description'] !== null ? $product['description'] : '',
            'amount' => (float)$product['price'],
            'stock' => $stockCount,
            'available' => (bool)$available,
        );
    }

    json_response(array(
        'success' => true,
        'data' => $response,
    ));
} catch (\PDOException $exception) {
    json_response(array('success' => false, 'error' => 'Ürünler yüklenemedi: ' . $exception->getMessage()), 500);
}
