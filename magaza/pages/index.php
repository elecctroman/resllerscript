<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Database;

$products = array();

try {
    $pdo = Database::connection();
    $stmt = $pdo->query("SELECT p.id, p.name, p.price, p.sku, p.image, p.automatic_delivery, c.name AS category_name
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 12");
    if ($stmt !== false) {
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }
} catch (PDOException $exception) {
    error_log('[Storefront] Ürünler getirilemedi: ' . $exception->getMessage());
    $products = array();
}

foreach ($products as &$product) {
    $product['url'] = '/magaza/product.php?id=' . (int) $product['id'];
    $product['unlimited_delivery'] = false;
}
unset($product);

store_render('home', array(
    'pageTitle' => 'Mağaza',
    'products' => $products,
));
