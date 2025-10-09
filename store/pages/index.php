<?php
require_once __DIR__ . '/../bootstrap.php';

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

$sections = store_homepage_sections();

store_render('home', array(
    'pageTitle' => $pageTitle,
    'heroSlides' => $heroSlides,
    'showcaseSections' => $sections,
    'metaDescription' => (string) get_setting('seo_description', ''),
));
