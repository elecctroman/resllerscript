<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Database;

$categoryId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
$query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$category = null;
$categories = array();
$products = array();

try {
    $pdo = Database::connection();

    $categoryStmt = $pdo->query('SELECT id, name, description FROM categories ORDER BY name ASC');
    if ($categoryStmt !== false) {
        $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    if ($categoryId > 0) {
        $stmt = $pdo->prepare('SELECT id, name, description FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $categoryId));
        $category = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $sql = "SELECT p.id, p.name, p.price, p.sku, p.image, p.automatic_delivery, c.name AS category_name
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active'";
    $params = array();

    if ($category) {
        $sql .= ' AND p.category_id = :category_id';
        $params['category_id'] = $category['id'];
    }

    if ($query !== '') {
        $sql .= ' AND (p.name LIKE :search OR p.sku LIKE :search)';
        $params['search'] = '%' . $query . '%';
    }

    $sql .= ' ORDER BY p.created_at DESC';

    $productStmt = $pdo->prepare($sql);
    $productStmt->execute($params);
    $products = $productStmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
} catch (PDOException $exception) {
    error_log('[Storefront] Kategori görüntülenemedi: ' . $exception->getMessage());
    $category = null;
    $categories = array();
    $products = array();
}

foreach ($products as &$product) {
    $product['url'] = store_url('product/' . (int) $product['id']);
    $product['unlimited_delivery'] = false;
}
unset($product);

$headerCategories = array();
foreach ($categories as $cat) {
    if (!isset($cat['id'], $cat['name'])) {
        continue;
    }

    $headerCategories[] = array(
        'name' => (string) $cat['name'],
        'url' => store_url('category/' . (int) $cat['id']),
        'icon' => '',
    );
}

$breadcrumb = array(
    array('label' => 'Mağaza', 'href' => store_url('')),
    array(
        'label' => $category ? $category['name'] : 'Tüm Ürünler',
        'active' => true,
    ),
);

store_render('category', array(
    'pageTitle' => $category ? $category['name'] : 'Ürünler',
    'category' => $category,
    'categories' => $categories,
    'products' => $products,
    'query' => $query,
    'breadcrumb' => $breadcrumb,
    'headerCategories' => array_slice($headerCategories, 0, 9),
    'metaDescription' => $category && !empty($category['description']) ? (string) $category['description'] : (string) get_setting('seo_description', ''),
));
