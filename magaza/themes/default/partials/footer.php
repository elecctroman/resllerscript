<?php
use App\Settings;

$viewData = array();
if (isset($storeViewContext) && is_object($storeViewContext) && isset($storeViewContext->data) && is_array($storeViewContext->data)) {
    $viewData = $storeViewContext->data;
}

$storeName = isset($viewData['storeName']) ? (string) $viewData['storeName'] : 'OyunHesap.com.tr';
$year = (int) date('Y');

$whatsAppUrl = '#';
if (isset($viewData['whatsapp_url'])) {
    $candidate = (string) $viewData['whatsapp_url'];
    if ($candidate !== '') {
        $whatsAppUrl = $candidate;
    }
}

if (function_exists('envStr')) {
    $envWhatsapp = envStr('STORE_WHATSAPP_URL');
    if ($envWhatsapp) {
        $whatsAppUrl = $envWhatsapp;
    }
}

if ($whatsAppUrl === '#' && class_exists(Settings::class)) {
    $storedUrl = Settings::get('store_whatsapp_url');
    if ($storedUrl) {
        $whatsAppUrl = $storedUrl;
    }
}

$whatsAppUrl = trim((string) $whatsAppUrl);
if ($whatsAppUrl === '') {
    $whatsAppUrl = '#';
}
?>
<footer class="storefront-footer mt-auto">
    <div class="container-xxl d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
        <span class="text-muted small">&copy; <?php echo $year; ?> <?php echo htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?>. Tüm hakları saklıdır.</span>
        <nav class="d-flex gap-3 small flex-wrap">
            <a href="/magaza/index.php" class="text-decoration-none text-muted">Ana Sayfa</a>
            <a href="/support.php" class="text-decoration-none text-muted">Destek</a>
            <a href="/blog" class="text-decoration-none text-muted">Blog</a>
            <a href="/contact" class="text-decoration-none text-muted">İletişim</a>
        </nav>
    </div>
</footer>
<?php if ($whatsAppUrl !== ''): ?>
    <a class="whatsapp-button" href="<?php echo htmlspecialchars($whatsAppUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" aria-label="WhatsApp canlı destek">
        <span class="whatsapp-icon" aria-hidden="true"></span>
        <span>Canlı Destek</span>
    </a>
<?php endif; ?>
