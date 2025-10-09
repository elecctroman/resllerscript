<?php
use App\Helpers;

$product = isset($product) && is_array($product) ? $product : array();
$imagePath = isset($product['image']) && $product['image'] ? (string) $product['image'] : '';
if ($imagePath && $imagePath[0] !== '/') {
    $imagePath = '/uploads/products/' . $imagePath;
}
if ($imagePath === '') {
    $imagePath = theme_asset('img/placeholder-16x9.svg');
}

$csrfToken = Helpers::csrfToken();
$productId = isset($product['id']) ? (int) $product['id'] : 0;
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
            <div>
                <div id="productDescription" class="product-description is-clamped">
                    <?php echo nl2br(htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8')); ?>
                </div>
                <button type="button" class="product-description__toggle" data-description-toggle data-target="productDescription" aria-expanded="false">Daha fazla</button>
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
        <form class="product-cart-form d-flex flex-wrap gap-2" method="post" action="<?= htmlspecialchars(store_url('cart/add'), ENT_QUOTES, 'UTF-8') ?>" data-cart-add>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="product_id" value="<?= $productId ?>">
            <div class="input-group input-group-lg flex-grow-1 flex-lg-grow-0" style="max-width: 220px;">
                <button class="btn btn-outline-secondary" type="button" data-cart-decrement aria-label="Adet azalt">−</button>
                <input type="number" class="form-control text-center" name="quantity" value="1" min="1">
                <button class="btn btn-outline-secondary" type="button" data-cart-increment aria-label="Adet artır">+</button>
            </div>
            <button type="submit" class="btn btn-primary btn-lg flex-grow-1 flex-lg-grow-0">Sepete Ekle</button>
            <a class="btn btn-outline-secondary btn-lg" href="<?= htmlspecialchars(store_url(''), ENT_QUOTES, 'UTF-8') ?>">Alışverişe Devam Et</a>
        </form>
    </div>
</div>
