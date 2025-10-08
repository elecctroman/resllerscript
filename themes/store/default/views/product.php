<?php
$product = isset($product) && is_array($product) ? $product : array();
$imagePath = isset($product['image']) && $product['image'] ? (string) $product['image'] : '';
if ($imagePath && $imagePath[0] !== '/') {
    $imagePath = '/uploads/products/' . $imagePath;
}
if ($imagePath === '') {
    $imagePath = theme_asset('img/placeholder-16x9.svg');
}
?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow-sm bg-light">
            <img src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="w-100 h-100 object-fit-cover" loading="lazy">
        </div>
    </div>
    <div class="col-lg-6 d-flex flex-column gap-3">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
            <?php if (!empty($product['duration'])): ?>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($product['duration'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
        <div class="fs-2 fw-bold text-primary">₺<?php echo isset($product['price']) ? number_format((float) $product['price'], 2, ',', '.') : '0,00'; ?></div>
        <ul class="list-unstyled text-muted small mb-0">
            <?php if (!empty($product['category_name'])): ?>
                <li><span class="text-secondary">Kategori:</span> <?php echo htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endif; ?>
            <?php if (!empty($product['supplier_name'])): ?>
                <li><span class="text-secondary">Sağlayıcı:</span> <?php echo htmlspecialchars($product['supplier_name'], ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endif; ?>
            <?php if (!empty($product['sku'])): ?>
                <li><span class="text-secondary">SKU:</span> <?php echo htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endif; ?>
        </ul>
        <?php if (!empty($product['description'])): ?>
            <div class="product-description">
                <?php echo nl2br(htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8')); ?>
            </div>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2">
            <?php if (!empty($product['automatic_delivery'])): ?>
                <span class="badge bg-success-subtle text-success">Otomatik Teslimat</span>
            <?php endif; ?>
            <?php if (!empty($product['unlimited_delivery'])): ?>
                <span class="badge bg-info-subtle text-info">Sınırsız Teslimat</span>
            <?php endif; ?>
        </div>
        <form class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary btn-lg">Sepete ekle</button>
            <button type="button" class="btn btn-outline-secondary btn-lg">Favorilere ekle</button>
        </form>
    </div>
</div>
