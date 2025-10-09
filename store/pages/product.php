<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Helpers;

$requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
$productId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;

if (strpos($requestUri, 'product.php') !== false && $productId > 0) {
    try {
        $pdoRedirect = Database::connection();
        $legacyStmt = $pdoRedirect->prepare('SELECT id, name, slug FROM products WHERE id = :id LIMIT 1');
        $legacyStmt->execute(array('id' => $productId));
        $legacyProduct = $legacyStmt->fetch(PDO::FETCH_ASSOC);
        if ($legacyProduct) {
            Helpers::redirect(url_product($legacyProduct), 301);
        }
    } catch (Throwable $redirectError) {
        // ignore redirect errors
    }
}

$product = null;

if ($productId > 0) {
    try {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            WHERE p.id = :id AND p.status = 'active' LIMIT 1");
        $stmt->execute(array('id' => $productId));
        $product = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $exception) {
        error_log('[Storefront] Ürün detayları yüklenemedi: ' . $exception->getMessage());
        $product = null;
    }
}

if (!$product) {
    store_render('product', array(
        'pageTitle' => 'Ürün bulunamadı',
        'product' => array(
            'name' => 'Ürün bulunamadı',
            'description' => 'Aradığınız ürün mevcut değil ya da yayından kaldırıldı.',
            'price' => 0,
            'automatic_delivery' => false,
            'unlimited_delivery' => false,
        ),
        'breadcrumb' => array(
            array('label' => 'Mağaza', 'href' => store_url('')),
            array('label' => 'Ürün bulunamadı', 'active' => true),
        ),
    ));
    return;
}

$productUrl = url_product($product);
$categoryUrl = isset($product['category_slug'])
    ? url_category(array('id' => $product['category_id'], 'name' => $product['category_name'], 'slug' => $product['category_slug']))
    : store_url('kategori');

$product['url'] = $productUrl;
$product['unlimited_delivery'] = false;

store_render('product', array(
    'pageTitle' => $product['name'],
    'product' => $product,
    'breadcrumb' => array(
        array('label' => 'Mağaza', 'href' => store_url('')),
        array('label' => $product['category_name'] ?? 'Ürünler', 'href' => $categoryUrl),
        array('label' => $product['name'], 'active' => true),
    ),
    'metaDescription' => isset($product['description']) && $product['description'] !== null ? (string) $product['description'] : (string) get_setting('seo_description', ''),
    'canonical' => $productUrl,
));
