<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Database;

$productId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
$product = null;

if ($productId > 0) {
    try {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name
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
            array('label' => 'Mağaza', 'href' => '/magaza/index.php'),
            array('label' => 'Ürün bulunamadı', 'active' => true),
        ),
    ));
    return;
}

$product['url'] = '/magaza/product.php?id=' . (int) $product['id'];
$product['unlimited_delivery'] = false;

store_render('product', array(
    'pageTitle' => $product['name'],
    'product' => $product,
    'breadcrumb' => array(
        array('label' => 'Mağaza', 'href' => '/magaza/index.php'),
        array('label' => 'Ürünler', 'href' => '/magaza/category.php'),
        array('label' => $product['name'], 'active' => true),
    ),
));
