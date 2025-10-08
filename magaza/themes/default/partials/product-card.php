<?php
if (empty($product) || !is_array($product)) {
    return;
}

$imagePath = '';
if (!empty($product['image'])) {
    $imagePath = (string) $product['image'];
    if ($imagePath && $imagePath[0] !== '/') {
        $imagePath = '/uploads/products/' . $imagePath;
    }
}

if ($imagePath === '' || !is_string($imagePath)) {
    $imagePath = theme_asset('img/placeholder-16x9.svg');
}

$name = isset($product['name']) ? (string) $product['name'] : '';
$duration = isset($product['duration']) ? (string) $product['duration'] : '';
$price = isset($product['price']) ? number_format((float) $product['price'], 2, ',', '.') : '0,00';
$categoryName = isset($product['category_name']) ? (string) $product['category_name'] : '';
$supplierName = isset($product['supplier_name']) ? (string) $product['supplier_name'] : '';
$sku = isset($product['sku']) ? (string) $product['sku'] : '';
$automatic = !empty($product['automatic_delivery']);
$unlimited = !empty($product['unlimited_delivery']);
$productUrl = isset($product['url']) ? (string) $product['url'] : '/magaza/product.php?id=' . (isset($product['id']) ? (int) $product['id'] : 0);
?>
<div class="product-card card h-100 shadow-sm">
    <div class="product-card__media">
        <img src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
    </div>
    <div class="card-body d-flex flex-column gap-2">
        <div>
            <h3 class="h6 mb-1 text-truncate" title="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
            </h3>
            <?php if ($duration !== ''): ?>
                <p class="text-muted small mb-0"><?php echo htmlspecialchars($duration, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
        <div class="fw-semibold text-primary fs-5">₺<?php echo $price; ?></div>
        <ul class="list-unstyled small text-muted mb-0">
            <?php if ($categoryName !== ''): ?>
                <li><span class="text-secondary">Kategori:</span> <?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endif; ?>
            <?php if ($supplierName !== ''): ?>
                <li><span class="text-secondary">Sağlayıcı:</span> <?php echo htmlspecialchars($supplierName, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endif; ?>
            <?php if ($sku !== ''): ?>
                <li><span class="text-secondary">SKU:</span> <?php echo htmlspecialchars($sku, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endif; ?>
        </ul>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($automatic): ?>
                <span class="badge bg-success-subtle text-success">Otomatik Teslimat</span>
            <?php endif; ?>
            <?php if ($unlimited): ?>
                <span class="badge bg-info-subtle text-info">Sınırsız Teslimat</span>
            <?php endif; ?>
        </div>
        <div class="mt-auto d-flex flex-wrap gap-2">
            <a href="<?php echo htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-sm flex-fill">Sipariş Ver</a>
            <button type="button" class="btn btn-outline-primary btn-sm flex-fill" data-action="favorite">Favori</button>
            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" data-action="compare">Karşılaştır</button>
        </div>
    </div>
</div>
