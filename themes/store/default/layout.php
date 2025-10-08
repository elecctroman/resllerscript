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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
