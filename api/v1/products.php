<?php
require __DIR__ . '/../bootstrap.php';

$token = authenticate_token();

try {
    $pdo = App\Database::connection();

    $categoryStmt = $pdo->query('SELECT id, parent_id, name, description FROM categories ORDER BY name ASC');
    $categories = $categoryStmt->fetchAll();

    $productStmt = $pdo->prepare('SELECT pr.id, pr.name, pr.sku, pr.description, pr.price, pr.status, pr.category_id, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id WHERE pr.status = :status ORDER BY cat.name ASC, pr.name ASC');
    $productStmt->execute(array('status' => 'active'));
    $products = $productStmt->fetchAll();

    json_response(array(
        'success' => true,
        'data' => array(
            'reseller' => array(
                'id' => (int)$token['user_id'],
                'name' => $token['name'],
                'email' => $token['email'],
                'balance' => isset($token['balance']) ? (float)$token['balance'] : 0,
            ),
            'categories' => array_map(function ($category) {
                return array(
                    'id' => (int)$category['id'],
                    'parent_id' => isset($category['parent_id']) ? (int)$category['parent_id'] : null,
                    'name' => $category['name'],
                    'description' => $category['description'],
                );
            }, $categories),
            'products' => array_map(function ($product) {
                return array(
                    'id' => (int)$product['id'],
                    'name' => $product['name'],
                    'sku' => isset($product['sku']) ? $product['sku'] : null,
                    'description' => isset($product['description']) ? $product['description'] : null,
                    'price' => (float)$product['price'],
                    'category_id' => (int)$product['category_id'],
                    'category_name' => $product['category_name'],
                );
            }, $products),
        ),
    ));
} catch (\PDOException $exception) {
    json_response(array('success' => false, 'error' => 'Ürünler yüklenemedi: ' . $exception->getMessage()), 500);
}
