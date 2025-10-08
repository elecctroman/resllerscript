<?php
$products = isset($products) && is_array($products) ? $products : array();
$slides = isset($heroSlides) && is_array($heroSlides) ? $heroSlides : array();
$megaGroups = store_homepage_groups();

if (!$slides) {
    $slides = array(
        array(
            'title' => "PUBG Hesaplarında %30'a Varan İndirim",
            'description' => 'Türkiye sunucusunda en hızlı teslimat garantisiyle UC paketleri ve premium hesaplar elinizin altında.',
            'cta' => 'PUBG Ürünleri',
            'url' => url_category(array('id' => 1, 'name' => 'PUBG', 'slug' => 'pubg')),
            'tag' => 'Sıcak Fırsat',
            'image' => theme_asset('img/placeholder-16x9.svg'),
        ),
        array(
            'title' => 'Valorant VP Yüklemelerinde Işık Hızında Teslimat',
            'description' => 'Otomatik teslimat sistemi ile saniyeler içinde hesabınıza VP yükleyin, dereceli maçlara ara vermeyin.',
            'cta' => "Valorant'a Git",
            'url' => url_category(array('id' => 2, 'name' => 'Valorant', 'slug' => 'valorant')),
            'tag' => 'Yeni Sezon',
            'image' => theme_asset('img/placeholder-16x9.svg'),
        ),
        array(
            'title' => 'Adobe Creative Cloud Hesaplarında Mega Paket',
            'description' => 'Tasarım ekipleri için uygun fiyatlı aylık ve yıllık Creative Cloud lisansları, anında teslim.',
            'cta' => 'Adobe Paketleri',
            'url' => url_category(array('id' => 3, 'name' => 'Adobe', 'slug' => 'adobe')),
            'tag' => 'Profesyoneller',
            'image' => theme_asset('img/placeholder-16x9.svg'),
        ),
    );
}

$productCount = count($products);
$primaryLabel = 'Öne Çıkanlar';
if ($productCount > 0) {
    $firstCategory = isset($products[0]['category_name']) ? (string) $products[0]['category_name'] : '';
    if ($firstCategory !== '') {
        $primaryLabel = $firstCategory;
    }
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
                <article class="hero-slide<?php echo $index === 0 ? ' is-active' : ''; ?>" data-carousel-slide>
                    <div class="hero-media">
                        <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                    </div>
                    <div class="hero-content">
                        <?php if ($tag !== ''): ?>
                            <span class="hero-tag"><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <h2 class="hero-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <?php if ($description !== ''): ?>
                            <p class="hero-text"><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap gap-3 mt-3">
                            <a class="btn btn-primary" href="<?php echo htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8'); ?></a>
                            <a class="btn btn-outline" href="<?php echo htmlspecialchars(store_url('kategori'), ENT_QUOTES, 'UTF-8'); ?>">Tüm Kategoriler</a>
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
                <button type="button" class="<?php echo $index === 0 ? 'is-active' : ''; ?>" data-carousel-indicator data-carousel-index="<?php echo (int) $index; ?>" aria-label="Slayt <?php echo (int) ($index + 1); ?>"></button>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($megaGroups): ?>
    <section class="mega-grid" aria-label="Kategori vitrinleri">
        <?php foreach ($megaGroups as $group): ?>
            <article class="mega-card">
                <h3 class="mega-card__title"><?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <ul class="mega-card__list">
                    <?php $items = array_slice($group['items'], 0, 6); ?>
                    <?php foreach ($items as $item): ?>
                        <li>
                            <a class="mega-card__link" href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="mega-card__icon cat-icon cat-icon--<?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></span>
                                <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a class="mega-card__cta" href="<?php echo htmlspecialchars(url_category(array('id' => $group['id'], 'name' => $group['name'], 'slug' => $group['slug'])), ENT_QUOTES, 'UTF-8'); ?>">Tümünü Gör</a>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<div class="section-heading">
    <h2><?php echo htmlspecialchars($primaryLabel, ENT_QUOTES, 'UTF-8'); ?> &rarr;</h2>
    <span class="meta"><?php echo (int) $productCount; ?> ürün</span>
</div>

<?php if ($products): ?>
    <div class="product-grid">
        <?php foreach ($products as $product): ?>
            <?php store_include(theme_partial('product-card'), array('product' => $product)); ?>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info bg-opacity-10 border-0 text-white">Şu anda listelenecek ürün bulunamadı. Yeni stoklar eklendiğinde ilk siz haber alacaksınız.</div>
<?php endif; ?>
