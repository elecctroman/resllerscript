<?php
$viewData = array();
if (isset($storeViewContext) && is_object($storeViewContext) && isset($storeViewContext->data) && is_array($storeViewContext->data)) {
    $viewData = $storeViewContext->data;
}

$siteName = trim((string) get_setting('site_name', 'OyunHesap.com.tr'));
if ($siteName === '') {
    $siteName = 'OyunHesap.com.tr';
}

$year = (int) date('Y');
$whatsAppUrl = trim((string) get_setting('whatsapp_url', ''));
$contactEmail = trim((string) get_setting('contact_email', ''));
$contactPhone = trim((string) get_setting('contact_phone', ''));
$facebook = trim((string) get_setting('social_facebook', ''));
$instagram = trim((string) get_setting('social_instagram', ''));
$twitter = trim((string) get_setting('social_twitter', ''));

?>
<footer class="storefront-footer mt-auto">
    <div class="container-xxl d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
        <div class="text-muted small">
            &copy; <?php echo $year; ?> <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>. Tüm hakları saklıdır.
        </div>
        <div class="d-flex align-items-center gap-3 small flex-wrap">
            <?php if ($contactEmail !== ''): ?>
                <a class="text-decoration-none text-muted" href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>">E-posta</a>
            <?php endif; ?>
            <?php if ($contactPhone !== ''): ?>
                <a class="text-decoration-none text-muted" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $contactPhone), ENT_QUOTES, 'UTF-8'); ?>">Telefon</a>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars(store_url(''), ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none text-muted">Ana Sayfa</a>
            <a href="<?php echo htmlspecialchars(store_url('support.php'), ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none text-muted">Destek</a>
        </div>
        <div class="d-flex align-items-center gap-2 social-links">
            <?php if ($facebook !== ''): ?>
                <a href="<?php echo htmlspecialchars($facebook, ENT_QUOTES, 'UTF-8'); ?>" class="text-muted" aria-label="Facebook" target="_blank" rel="noopener">
                    <span class="icon icon-facebook" aria-hidden="true"></span>
                </a>
            <?php endif; ?>
            <?php if ($instagram !== ''): ?>
                <a href="<?php echo htmlspecialchars($instagram, ENT_QUOTES, 'UTF-8'); ?>" class="text-muted" aria-label="Instagram" target="_blank" rel="noopener">
                    <span class="icon icon-instagram" aria-hidden="true"></span>
                </a>
            <?php endif; ?>
            <?php if ($twitter !== ''): ?>
                <a href="<?php echo htmlspecialchars($twitter, ENT_QUOTES, 'UTF-8'); ?>" class="text-muted" aria-label="Twitter" target="_blank" rel="noopener">
                    <span class="icon icon-twitter" aria-hidden="true"></span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</footer>
<?php if ($whatsAppUrl !== ''): ?>
    <a class="whatsapp-button" href="<?php echo htmlspecialchars($whatsAppUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" aria-label="WhatsApp canlı destek">
        <span class="whatsapp-icon" aria-hidden="true"></span>
        <span>Canlı Destek</span>
    </a>
<?php endif; ?>
