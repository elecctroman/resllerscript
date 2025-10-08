<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Database;

$products = array();
$categories = array();
$defaultCollections = array();

try {
    $pdo = Database::connection();

    $productStmt = $pdo->query("SELECT p.id, p.name, p.price, p.sku, p.image, p.automatic_delivery, c.name AS category_name
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 12");

    if ($productStmt !== false) {
        $products = $productStmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    $categoryStmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY name ASC LIMIT 12");
    if ($categoryStmt !== false) {
        $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }
} catch (PDOException $exception) {
    error_log('[Storefront] Ürünler getirilemedi: ' . $exception->getMessage());
    $products = array();
    $categories = array();
}

foreach ($products as &$product) {
    $product['url'] = store_url('product/' . (int) $product['id']);
    $product['unlimited_delivery'] = false;
}
unset($product);

$featuredCollections = array();
foreach ($categories as $category) {
    if (!isset($category['id'], $category['name'])) {
        continue;
    }

    $collection = array(
        'name' => (string) $category['name'],
        'description' => isset($category['description']) ? (string) $category['description'] : '',
        'url' => store_url('category/' . (int) $category['id']),
        'icon' => '',
    );

    $defaultCollections[] = $collection;
    $featuredCollections[] = $collection;
}

$customCollections = store_setting_array('store_featured_collections');
if ($customCollections) {
    $featuredCollections = array();
    foreach ($customCollections as $collection) {
        if (!is_array($collection)) {
            continue;
        }

        $name = isset($collection['name']) ? trim((string) $collection['name']) : '';
        if ($name === '') {
            continue;
        }

        $url = isset($collection['url']) ? (string) $collection['url'] : '#';
        if ($url !== '' && !preg_match('/^https?:/i', $url)) {
            $url = store_url(ltrim($url, '/'));
        }

        $featuredCollections[] = array(
            'name' => $name,
            'description' => isset($collection['description']) ? (string) $collection['description'] : '',
            'url' => $url !== '' ? $url : store_url('category'),
            'icon' => isset($collection['icon']) ? (string) $collection['icon'] : '',
        );
    }
}

$headerSource = $featuredCollections ?: $defaultCollections;
$headerCategories = array();
foreach (array_slice($headerSource, 0, 9) as $category) {
    if (!is_array($category) || empty($category['name'])) {
        continue;
    }

    $headerCategories[] = array(
        'name' => (string) $category['name'],
        'url' => isset($category['url']) ? (string) $category['url'] : store_url('category'),
        'icon' => isset($category['icon']) ? (string) $category['icon'] : '',
    );
}

$heroSlides = array();
$customSlides = store_setting_array('store_home_hero_slides');
if ($customSlides) {
    foreach ($customSlides as $slide) {
        if (!is_array($slide)) {
            continue;
        }

        $title = isset($slide['title']) ? trim((string) $slide['title']) : '';
        $description = isset($slide['description']) ? trim((string) $slide['description']) : '';
        $cta = isset($slide['cta']) ? trim((string) $slide['cta']) : '';
        $url = isset($slide['url']) ? (string) $slide['url'] : store_url('category');
        if ($url !== '' && !preg_match('/^https?:/i', $url)) {
            $url = store_url(ltrim($url, '/'));
        }
        $tag = isset($slide['tag']) ? trim((string) $slide['tag']) : '';
        $image = isset($slide['image']) ? store_media_url($slide['image'], theme_asset('img/placeholder-16x9.svg')) : theme_asset('img/placeholder-16x9.svg');

        $heroSlides[] = array(
            'title' => $title !== '' ? $title : 'Mağaza Fırsatı',
            'description' => $description,
            'cta' => $cta !== '' ? $cta : 'İncele',
            'url' => $url,
            'tag' => $tag,
            'image' => $image,
        );
    }
}

if (!$heroSlides) {
    $fallbackSource = $headerSource ?: array();
    foreach (array_slice($fallbackSource, 0, 3) as $item) {
        if (!is_array($item) || empty($item['name'])) {
            continue;
        }

        $heroSlides[] = array(
            'title' => (string) $item['name'],
            'description' => isset($item['description']) ? (string) $item['description'] : 'Popüler ürünlerimizi keşfedin.',
            'cta' => 'İncele',
            'url' => isset($item['url']) ? (string) $item['url'] : store_url('category'),
            'tag' => 'Öne Çıkan',
            'image' => theme_asset('img/placeholder-16x9.svg'),
        );
    }
}

if (!$heroSlides) {
    $heroSlides = array(
        array(
            'title' => "PUBG Hesaplarında %30'a Varan İndirim",
            'description' => 'Türkiye sunucusunda en hızlı teslimat garantisiyle UC paketleri ve premium hesaplar elinizin altında.',
            'cta' => 'PUBG Ürünleri',
            'url' => store_url('category?q=' . rawurlencode('PUBG')),
            'tag' => 'Sıcak Fırsat',
            'image' => theme_asset('img/placeholder-16x9.svg'),
        ),
        array(
            'title' => 'Valorant VP Yüklemelerinde Işık Hızında Teslimat',
            'description' => 'Otomatik teslimat sistemi ile saniyeler içinde hesabınıza VP yükleyin, dereceli maçlara ara vermeyin.',
            'cta' => "Valorant'a Git",
            'url' => store_url('category?q=' . rawurlencode('Valorant')),
            'tag' => 'Yeni Sezon',
            'image' => theme_asset('img/placeholder-16x9.svg'),
        ),
        array(
            'title' => 'Adobe Creative Cloud Hesaplarında Mega Paket',
            'description' => 'Tasarım ekipleri için uygun fiyatlı aylık ve yıllık Creative Cloud lisansları, anında teslim.',
            'cta' => 'Adobe Paketleri',
            'url' => store_url('category?q=' . rawurlencode('Adobe')),
            'tag' => 'Profesyoneller',
            'image' => theme_asset('img/placeholder-16x9.svg'),
        ),
    );
}

$pageTitle = (string) get_setting('seo_title', '');
if ($pageTitle === '') {
    $pageTitle = 'Mağaza';
}

store_render('home', array(
    'pageTitle' => $pageTitle,
    'products' => $products,
    'headerCategories' => $headerCategories,
    'featuredCollections' => $featuredCollections,
    'heroSlides' => $heroSlides,
    'metaDescription' => (string) get_setting('seo_description', ''),
));
