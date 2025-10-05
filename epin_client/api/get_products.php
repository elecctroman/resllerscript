<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
$query = 'SELECT id, name, price, stock, description FROM products WHERE stock > 0 ORDER BY name ASC';
if ($limit > 0) {
    $query .= ' LIMIT :limit';
}

$pdo = db();
$stmt = $pdo->prepare($query);
if ($limit > 0) {
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
}
$stmt->execute();
$products = array_map(static function (array $product): array {
    return [
        'id' => (int)$product['id'],
        'name' => sanitize($product['name']),
        'price' => number_format((float)$product['price'], 2, '.', ''),
        'stock' => (int)$product['stock'],
        'description' => sanitize($product['description'] ?? ''),
    ];
}, $stmt->fetchAll());

json_response(['products' => $products]);
