<?php
$slides = isset($heroSlides) && is_array($heroSlides) ? $heroSlides : array();
$megaGroups = store_homepage_groups();

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

<?php if ($megaGroups): ?>
    <section class="category-stack" aria-label="Kategoriler">
        <?php foreach ($megaGroups as $group): ?>
            <?php
            $groupName = isset($group['name']) ? (string) $group['name'] : 'Kategori';
            $items = isset($group['items']) && is_array($group['items']) ? $group['items'] : array();
            $primaryUrl = '';
            foreach ($items as $candidate) {
                if (isset($candidate['url']) && $candidate['url'] !== '') {
                    $primaryUrl = (string) $candidate['url'];
                    break;
                }
            }
            ?>
            <article class="category-section">
                <header class="category-section__header">
                    <h3><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></h3>
                </header>
                <div class="category-section__items">
                    <?php if ($items): ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $itemLabel = isset($item['label']) ? (string) $item['label'] : '';
                            $itemUrl   = isset($item['url']) ? (string) $item['url'] : '';
                            if ($itemLabel === '' || $itemUrl === '') {
                                continue;
                            }
                            $iconKey   = isset($item['icon']) ? (string) $item['icon'] : '';
                            $imagePath = isset($item['image']) ? (string) $item['image'] : '';
                            $imageUrl  = $imagePath !== '' ? store_media_url($imagePath, '') : '';
                            $initialRaw = function_exists('mb_substr') ? mb_substr($itemLabel, 0, 1, 'UTF-8') : substr($itemLabel, 0, 1);
                            $initial    = $initialRaw !== null ? (function_exists('mb_strtoupper') ? mb_strtoupper($initialRaw, 'UTF-8') : strtoupper((string) $initialRaw)) : '';
                            ?>
                            <a class="category-section__item" href="<?= htmlspecialchars($itemUrl, ENT_QUOTES, 'UTF-8') ?>">
                                <?php if ($imageUrl !== ''): ?>
                                    <span class="category-section__thumb"><img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') ?>" loading="lazy"></span>
                                <?php else: ?>
                                    <span class="category-section__icon cat-icon cat-icon--<?= htmlspecialchars($iconKey, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <span class="category-section__name"><?= htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">Bu kategori için öğe eklenmemiş.</p>
                    <?php endif; ?>
                </div>
                <?php if ($primaryUrl !== ''): ?>
                    <div class="category-section__footer text-center">
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($primaryUrl, ENT_QUOTES, 'UTF-8') ?>">Tüm ürünleri görüntüle</a>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php else: ?>
    <div class="alert alert-info">Henüz mega menü kategorileri eklenmedi. Yönetim panelinden yeni gruplar oluşturabilirsiniz.</div>
<?php endif; ?>
