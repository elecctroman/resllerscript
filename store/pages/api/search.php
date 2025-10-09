<?php
require_once __DIR__ . '/../../bootstrap.php';

use App\Database;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(array('results' => array()));
    return;
}

$term = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if ($term === '' || mb_strlen($term, 'UTF-8') < 2) {
    echo json_encode(array('results' => array()));
    return;
}

$like = '%' . $term . '%';
$results = array();

try {
    $pdo = Database::connection();

    $categoryStmt = $pdo->prepare('SELECT id, name, slug FROM categories WHERE name LIKE :term ORDER BY name ASC LIMIT 5');
    $categoryStmt->execute(array('term' => $like));
    foreach ($categoryStmt->fetchAll(PDO::FETCH_ASSOC) as $category) {
        $results[] = array(
            'type' => 'category',
            'label' => (string) $category['name'],
            'url' => url_category($category),
        );
    }

    $productStmt = $pdo->prepare('SELECT id, name, slug FROM products WHERE status = "active" AND name LIKE :term ORDER BY name ASC LIMIT 5');
    $productStmt->execute(array('term' => $like));
    foreach ($productStmt->fetchAll(PDO::FETCH_ASSOC) as $product) {
        $results[] = array(
            'type' => 'product',
            'label' => (string) $product['name'],
            'url' => url_product($product),
        );
    }
} catch (Throwable $exception) {
    error_log('[Storefront] Search API failed: ' . $exception->getMessage());
}

if (count($results) > 10) {
    $results = array_slice($results, 0, 10);
}

echo json_encode(array('results' => $results));
