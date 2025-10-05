<?php
require __DIR__ . '/../bootstrap.php';

$token = authenticate_token();
require_scope($token, 'read');

try {
    $pdo = App\Database::connection();

    $includeInactive = false;
    if (isset($_GET['include_inactive'])) {
        $flag = strtolower((string)$_GET['include_inactive']);
        $includeInactive = in_array($flag, array('1', 'true', 'yes', 'on'), true);
    }

    $providerCodeFilter = isset($_GET['provider_code']) ? strtolower(trim((string)$_GET['provider_code'])) : '';
    $sinceFilter = isset($_GET['since']) ? trim((string)$_GET['since']) : '';

    $categoryStmt = $pdo->query('SELECT id, parent_id, name, description FROM categories ORDER BY name ASC');
    $categories = $categoryStmt->fetchAll();

    $productQuery = 'SELECT pr.id, pr.name, pr.sku, pr.description, pr.price, pr.status, pr.category_id, pr.provider_code, pr.updated_at, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id';
    $conditions = array();
    $params = array();

    if (!$includeInactive) {
        $conditions[] = 'pr.status = :status';
        $params['status'] = 'active';
    }

    if ($providerCodeFilter !== '') {
        $conditions[] = 'LOWER(pr.provider_code) = :provider_code';
        $params['provider_code'] = $providerCodeFilter;
    }

    if ($sinceFilter !== '') {
        $conditions[] = '(pr.updated_at IS NOT NULL AND pr.updated_at >= :since)';
        $params['since'] = $sinceFilter;
    }

    if ($conditions) {
        $productQuery .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $productQuery .= ' ORDER BY cat.name ASC, pr.name ASC';

    $productStmt = $pdo->prepare($productQuery);
    foreach ($params as $key => $value) {
        $productStmt->bindValue(':' . $key, $value, \PDO::PARAM_STR);
    }
    $productStmt->execute();
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
                    'status' => isset($product['status']) ? $product['status'] : null,
                    'provider_code' => isset($product['provider_code']) && $product['provider_code'] !== null ? strtolower((string)$product['provider_code']) : null,
                    'updated_at' => isset($product['updated_at']) ? $product['updated_at'] : null,
                );
            }, $products),
        ),
    ));
} catch (\PDOException $exception) {
    json_response(array('success' => false, 'error' => 'Ürünler yüklenemedi: ' . $exception->getMessage()), 500);
}
