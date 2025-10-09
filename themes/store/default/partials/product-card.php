<?php
use App\Helpers;

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
$priceValue = isset($product['price']) ? (float) $product['price'] : 0.0;
$currency = isset($product['currency']) ? (string) $product['currency'] : null;
$price = money_format_try($priceValue, $currency);
$categoryName = isset($product['category_name']) ? (string) $product['category_name'] : '';
$productUrl = isset($product['url']) ? (string) $product['url'] : store_url('product/' . (isset($product['id']) ? (int) $product['id'] : 0));
$productId = isset($product['id']) ? (int) $product['id'] : 0;
$csrfToken = Helpers::csrfToken();

$stockAvailable = isset($product['stock_available']) ? (int) $product['stock_available'] : null;
$inStock = $stockAvailable === null ? true : $stockAvailable > 0;

$discountBadge = '';
if (isset($product['discount_percent']) && $product['discount_percent'] !== '') {
    $discountBadge = '%' . (int) $product['discount_percent'] . ' indirim';
}

$automatic = !empty($product['automatic_delivery']);
$showFlags = $automatic || !$inStock;

?>
<article class="product-card" data-product-card>
    <a class="product-card__media" href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>">
        <img src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
        <?php if ($showFlags): ?>
            <div class="product-card__flags">
                <?php if ($automatic): ?>
                    <span class="product-flag">Otomatik teslim</span>
                <?php endif; ?>
                <?php if (!$inStock): ?>
                    <span class="product-flag product-flag--danger">Stokta yok</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($discountBadge !== ''): ?>
            <span class="product-card__badge product-card__badge--discount"><?= htmlspecialchars($discountBadge, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </a>
    <div class="product-card__body">
        <h3 class="product-card__title"><a href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></a></h3>
        <?php if ($categoryName !== ''): ?>
            <p class="product-card__category text-muted mb-2"><?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="product-card__price-row">
            <span class="product-card__price"><?= htmlspecialchars($price, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="product-card__actions">
            <form method="post" action="<?= htmlspecialchars(store_url('cart/add'), ENT_QUOTES, 'UTF-8') ?>" data-cart-add class="product-card__cart-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="product_id" value="<?= $productId ?>">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="btn btn-primary w-100">Sepete Ekle</button>
            </form>
            <a href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary w-100">Detay</a>
        </div>
    </div>
</article>
