<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Helpers;

$requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
$categoryId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
$query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

if (strpos($requestUri, 'category.php') !== false && $categoryId > 0) {
    try {
        $pdoRedirect = Database::connection();
        $slugStmt = $pdoRedirect->prepare('SELECT id, name, slug FROM categories WHERE id = :id LIMIT 1');
        $slugStmt->execute(array('id' => $categoryId));
        $legacyCategory = $slugStmt->fetch(PDO::FETCH_ASSOC);
        if ($legacyCategory) {
            Helpers::redirect(url_category($legacyCategory), 301);
        }
    } catch (Throwable $redirectException) {
        // ignore legacy redirect failures
    }
}

$category = null;
$categories = array();
$products = array();

try {
    $pdo = Database::connection();

    $categoryStmt = $pdo->query('SELECT id, name, description, slug, icon_key FROM categories ORDER BY name ASC');
    if ($categoryStmt !== false) {
        $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    if ($categoryId > 0) {
        $stmt = $pdo->prepare('SELECT id, name, description, slug FROM categories WHERE id = :id LIMIT 1');
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
    $product['url'] = url_product($product);
    $product['unlimited_delivery'] = false;
}
unset($product);

$breadcrumb = array(
    array('label' => 'Mağaza', 'href' => store_url('')),
    array(
        'label' => $category ? $category['name'] : 'Tüm Ürünler',
        'active' => true,
    ),
);

$canonicalUrl = '';
if ($category) {
    $canonicalUrl = url_category($category);
} elseif ($query !== '') {
    $canonicalUrl = url_search($query);
}

store_render('category', array(
    'pageTitle' => $category ? $category['name'] : 'Ürünler',
    'category' => $category,
    'categories' => $categories,
    'products' => $products,
    'query' => $query,
    'breadcrumb' => $breadcrumb,
    'metaDescription' => $category && !empty($category['description']) ? (string) $category['description'] : (string) get_setting('seo_description', ''),
    'canonical' => $canonicalUrl,
));
