<?php
if (empty($product) || !is_array($product)) {
    return;
}

$imagePath = '';
if (!empty($product['image'])) {
    $imagePath = (string) $product['image'];
    if ($imagePath !== '' && !preg_match('/^https?:/i', $imagePath)) {
        $imagePath = store_url(ltrim($imagePath, '/'));
    }
}

if ($imagePath === '' || !is_string($imagePath)) {
    $imagePath = theme_asset('img/placeholder-16x9.svg');
}

$name = isset($product['name']) ? (string) $product['name'] : '';
$duration = isset($product['duration']) ? (string) $product['duration'] : '';
$priceValue = isset($product['price']) ? $product['price'] : 0;
$currency = isset($product['currency']) ? (string) $product['currency'] : null;
$price = money_format_try($priceValue, $currency);
$categoryName = isset($product['category_name']) ? (string) $product['category_name'] : '';
$supplierName = isset($product['supplier_name']) ? (string) $product['supplier_name'] : '';
$sku = isset($product['sku']) ? (string) $product['sku'] : '';
$automatic = !empty($product['automatic_delivery']);
$unlimited = !empty($product['unlimited_delivery']);
$productUrl = isset($product['url']) ? (string) $product['url'] : store_url('product.php?id=' . (isset($product['id']) ? (int) $product['id'] : 0));

$badgeText = '';
if (isset($product['discount_percent']) && $product['discount_percent'] !== '') {
    $badgeText = '%' . (int) $product['discount_percent'] . ' İNDİRİM';
} elseif (isset($product['badge']) && $product['badge'] !== '') {
    $badgeText = (string) $product['badge'];
}
?>
<div class="product-card" role="article">
    <div class="product-card__media">
        <img src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
        <?php if ($badgeText !== ''): ?>
            <span class="badge-sale"><?php echo htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <div class="product-card__body">
        <div>
            <h3 class="product-card__title text-truncate" title="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h3>
            <?php if ($duration !== ''): ?>
                <p class="product-card__subtitle"><?php echo htmlspecialchars($duration, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
        <div class="product-meta">
            <?php if ($categoryName !== ''): ?>
                <span><span class="text-muted">Kategori:</span> <?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
            <?php if ($supplierName !== ''): ?>
                <span><span class="text-muted">Sağlayıcı:</span> <?php echo htmlspecialchars($supplierName, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
            <?php if ($sku !== ''): ?>
                <span><span class="text-muted">SKU:</span> <?php echo htmlspecialchars($sku, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>
        <div class="product-card__tags">
            <?php if ($automatic): ?>
                <span class="product-card__tag product-card__tag--auto">Otomatik Teslimat</span>
            <?php endif; ?>
            <?php if ($unlimited): ?>
                <span class="product-card__tag product-card__tag--unlimited">Sınırsız Teslimat</span>
            <?php endif; ?>
        </div>
        <div class="product-card__footer">
            <span class="product-price"><?php echo htmlspecialchars($price, ENT_QUOTES, 'UTF-8'); ?></span>
            <div class="product-actions">
                <a href="<?php echo htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-sm">Satın Al</a>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-action="favorite">Favori</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-action="compare">Karşılaştır</button>
            </div>
        </div>
    </div>
</div>
