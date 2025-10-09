<?php
$slides = isset($heroSlides) && is_array($heroSlides) ? $heroSlides : array();
$sections = isset($showcaseSections) && is_array($showcaseSections) ? $showcaseSections : array();

if (!$slides) {
    $slides = array(
        array(
            'title' => "PUBG Hesaplarında %30'a Varan İndirim",
            'description' => 'Türkiye sunucusunda hızlı teslimat garantili UC paketleri ve premium hesaplar.',
            'cta' => 'PUBG Ürünleri',
            'url' => url_category(array('id' => 1, 'name' => 'PUBG', 'slug' => 'pubg')),
            'tag' => 'Sıcak Fırsat',
            'image' => theme_asset('img/placeholder-16x9.svg'),
        ),
        array(
            'title' => 'Valorant VP Yüklemelerinde Işık Hızında Teslimat',
            'description' => 'Otomatik teslimat ile saniyeler içinde VP yükleyin, dereceli maçlara ara vermeyin.',
            'cta' => "Valorant'a Git",
            'url' => url_category(array('id' => 2, 'name' => 'Valorant', 'slug' => 'valorant')),
            'tag' => 'Yeni Sezon',
            'image' => theme_asset('img/placeholder-16x9.svg'),
        ),
        array(
            'title' => 'Adobe Creative Cloud Hesaplarında Mega Paket',
            'description' => 'Tasarım ekipleri için uygun fiyatlı aylık ve yıllık Creative Cloud lisansları.',
            'cta' => 'Adobe Paketleri',
            'url' => url_category(array('id' => 3, 'name' => 'Adobe', 'slug' => 'adobe')),
            'tag' => 'Profesyoneller',
            'image' => theme_asset('img/placeholder-16x9.svg'),
        ),
    );
}
?>
<section class="hero-section" aria-label="Vitrin kampanyaları">
    <div class="hero-carousel" data-carousel data-interval="4000">
        <div class="hero-track" data-carousel-track>
            <?php foreach ($slides as $index => $slide): ?>
                <?php
                $title = isset($slide['title']) ? (string) $slide['title'] : '';
                $description = isset($slide['description']) ? (string) $slide['description'] : '';
                $ctaText = isset($slide['cta']) ? (string) $slide['cta'] : 'İncele';
                $ctaUrl = isset($slide['url']) ? (string) $slide['url'] : '#';
                $tag = isset($slide['tag']) ? (string) $slide['tag'] : '';
                $image = isset($slide['image']) ? (string) $slide['image'] : theme_asset('img/placeholder-16x9.svg');
                ?>
                <article class="hero-slide<?= $index === 0 ? ' is-active' : '' ?>" data-carousel-slide>
                    <div class="hero-media">
                        <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                    </div>
                    <div class="hero-content">
                        <?php if ($tag !== ''): ?>
                            <span class="hero-tag"><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <h2 class="hero-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
                        <?php if ($description !== ''): ?>
                            <p class="hero-text"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap gap-3 mt-3">
                            <a class="btn btn-primary" href="<?= htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8') ?></a>
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(store_url('kategori'), ENT_QUOTES, 'UTF-8') ?>">Tüm Kategoriler</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="hero-nav">
            <button type="button" class="hero-prev" aria-label="Önceki" data-carousel-prev>&lsaquo;</button>
            <button type="button" class="hero-next" aria-label="Sonraki" data-carousel-next>&rsaquo;</button>
        </div>
        <div class="hero-indicators" role="tablist">
            <?php foreach ($slides as $index => $slide): ?>
                <button type="button" class="<?= $index === 0 ? 'is-active' : '' ?>" data-carousel-indicator data-carousel-index="<?= (int) $index ?>" aria-label="Slayt <?= (int) ($index + 1) ?>"></button>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($sections): ?>
    <?php $structuredProducts = array(); ?>
    <?php foreach ($sections as $section): ?>
        <?php
        $category = isset($section['category']) && is_array($section['category']) ? $section['category'] : array();
        $products = isset($section['products']) && is_array($section['products']) ? $section['products'] : array();
        if (!$products) {
            continue;
        }
        $categoryName = isset($category['name']) ? (string) $category['name'] : 'Kategori';
        $categoryUrl = isset($category['url']) ? (string) $category['url'] : store_url('kategori');
        $iconKey = isset($category['icon_key']) ? (string) $category['icon_key'] : 'default';
        $initialRaw = function_exists('mb_substr') ? mb_substr($categoryName, 0, 1, 'UTF-8') : substr($categoryName, 0, 1);
        $initial = $initialRaw !== null ? (function_exists('mb_strtoupper') ? mb_strtoupper($initialRaw, 'UTF-8') : strtoupper((string) $initialRaw)) : 'K';
        ?>
        <section class="home-category" data-home-section>
            <div class="section-head">
                <h2 class="section-title">
                    <span class="section-icon cat-icon cat-icon--<?= htmlspecialchars($iconKey, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
                    <?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?>
                </h2>
                <a href="<?= htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn-link">Tümünü Gör →</a>
            </div>
            <div class="product-grid is-loading" data-product-grid>
                <?php foreach ($products as $product): ?>
                    <?php
                    if (!isset($product['url'])) {
                        $product['url'] = url_product($product);
                    }
                    if (!isset($product['category_name'])) {
                        $product['category_name'] = $categoryName;
                    }
                    store_include(theme_partial('product-card'), array('product' => $product));

                    if (count($structuredProducts) < 30) {
                        $imageUrl = store_media_url(isset($product['image']) ? (string) $product['image'] : '', theme_asset('img/placeholder-16x9.svg'));
                        $structuredProducts[] = array(
                            '@type' => 'Product',
                            'name' => isset($product['name']) ? (string) $product['name'] : $categoryName,
                            'image' => $imageUrl,
                            'category' => $categoryName,
                            'url' => isset($product['url']) ? (string) $product['url'] : $categoryUrl,
                            'offers' => array(
                                '@type' => 'Offer',
                                'priceCurrency' => isset($product['currency']) ? (string) $product['currency'] : 'TRY',
                                'price' => number_format(isset($product['price']) ? (float) $product['price'] : 0.0, 2, '.', ''),
                                'availability' => (isset($product['stock_available']) && (int) $product['stock_available'] > 0)
                                    ? 'https://schema.org/InStock'
                                    : 'https://schema.org/OutOfStock',
                            ),
                        );
                    }
                    ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
    <?php if ($structuredProducts): ?>
        <script type="application/ld+json">
            <?= json_encode(array('@context' => 'https://schema.org', '@graph' => $structuredProducts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
        </script>
    <?php endif; ?>
<?php else: ?>
    <div class="alert alert-info">Henüz vitrine kategori eklenmedi. Yönetim panelinden kategorileri seçebilirsiniz.</div>
<?php endif; ?>
