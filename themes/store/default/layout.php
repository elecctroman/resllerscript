<?php
/** @var object $storeViewContext */
$viewData = isset($storeViewContext->data) && is_array($storeViewContext->data)
    ? $storeViewContext->data
    : array();
$pageTitle = isset($storeViewContext->title) ? (string) $storeViewContext->title : 'Mağaza';
$metaDescription = isset($viewData['metaDescription']) ? (string) $viewData['metaDescription'] : '';
$metaMarkup = seo_meta($pageTitle, $metaDescription);
$canonicalLink = isset($viewData['canonical']) ? trim((string) $viewData['canonical']) : '';
$faviconSetting = get_setting('site_favicon');
$faviconHref = '';
if ($faviconSetting) {
    $faviconValue = (string) $faviconSetting;
    if (preg_match('/^https?:/i', $faviconValue)) {
        $faviconHref = $faviconValue;
    } else {
        $faviconHref = store_url(ltrim($faviconValue, '/'));
    }
}
?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo $metaMarkup; ?>
    <?php if ($canonicalLink !== ''): ?>
        <link rel="canonical" href="<?= htmlspecialchars($canonicalLink, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($faviconHref !== ''): ?>
        <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($faviconHref, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php theme_enqueue_assets(); ?>
</head>
<body class="storefront-body">
<?php store_include(theme_partial('header'), array('pageTitle' => $pageTitle)); ?>
<div class="mobile-menu-backdrop" data-mobile-menu-backdrop></div>
<div class="mobile-menu-panel" id="storeMobileMenuPanel" data-mobile-menu-panel aria-hidden="true">
    <div class="mobile-menu-panel__header d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">Menü</h2>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-mobile-menu-close aria-label="Menüyü kapat">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="mobile-menu-panel__body" data-mobile-menu-content></div>
</div>
<div id="menu-portal" class="menu-portal" aria-hidden="true"></div>
<main class="storefront-main py-4">
    <div class="container-xxl">
        <?php
        if (isset($viewData['breadcrumb'])) {
            store_include(theme_partial('breadcrumbs'), array('items' => $viewData['breadcrumb']));
        }
        store_include($storeViewContext->viewPath, $viewData);
        ?>
    </div>
</main>
<?php store_include(theme_partial('footer')); ?>
<div class="store-mini-cart" data-mini-cart hidden>
    <div class="store-mini-cart__backdrop" data-mini-cart-close></div>
    <div class="store-mini-cart__panel">
        <div class="store-mini-cart__header d-flex align-items-center justify-content-between">
            <h2 class="h5 mb-0">Sepete Eklendi</h2>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-mini-cart-close aria-label="Kapat">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="store-mini-cart__body" data-mini-cart-body>
            <p class="text-muted mb-0">Sepetinize eklenen ürünler burada görünecek.</p>
        </div>
        <div class="store-mini-cart__footer">
            <div class="store-mini-cart__summary" data-mini-cart-summary></div>
            <a class="btn btn-primary w-100" href="<?= htmlspecialchars(store_url('cart'), ENT_QUOTES, 'UTF-8') ?>">Sepete git</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
