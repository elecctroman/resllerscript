<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\FeatureToggle;
use App\Helpers;
use App\Settings;

Auth::requireRoles(array('super_admin', 'admin'));

$currentUser = $_SESSION['user'];
$errors = array();
$success = '';

$current = Settings::getMany(array(
    'site_name',
    'site_tagline',
    'seo_meta_description',
    'seo_meta_keywords',
    'pricing_commission_rate',
    'reseller_auto_suspend_enabled',
    'reseller_auto_suspend_threshold',
    'reseller_auto_suspend_days',
    'platform_default_locale',
));

$featureLabels = array(
    'products' => 'Ürün kataloğu ve sipariş verme',
    'orders' => 'Sipariş geçmişi görüntüleme',
    'balance' => 'Bakiye yönetimi',
    'support' => 'Destek talepleri',
    'packages' => 'Bayilik paketleri başvurusu',
    'premium_modules' => 'Premium modül pazarı',
);

$featureStates = FeatureToggle::all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum anahtarınız doğrulanamadı. Lütfen sayfayı yenileyin ve tekrar deneyin.';
    } else {
        $siteName = isset($_POST['site_name']) ? trim($_POST['site_name']) : '';
        $siteTagline = isset($_POST['site_tagline']) ? trim($_POST['site_tagline']) : '';
        $metaDescription = isset($_POST['seo_meta_description']) ? trim($_POST['seo_meta_description']) : '';
            $metaKeywords = isset($_POST['seo_meta_keywords']) ? trim($_POST['seo_meta_keywords']) : '';
            $commissionInput = isset($_POST['pricing_commission_rate']) ? str_replace(',', '.', trim($_POST['pricing_commission_rate'])) : '0';
            $commissionRate = (float)$commissionInput;
            if ($commissionRate < 0) {
                $commissionRate = 0.0;
            }

            $defaultLocale = isset($_POST['platform_default_locale']) ? strtolower((string)$_POST['platform_default_locale']) : 'tr';
            $availableLocales = App\Lang::availableLocales();
            if (!in_array($defaultLocale, $availableLocales, true)) {
                $defaultLocale = 'tr';
            }

            $autoSuspendEnabled = isset($_POST['reseller_auto_suspend_enabled']) ? '1' : '0';
            $autoThresholdInput = isset($_POST['reseller_auto_suspend_threshold']) ? str_replace(',', '.', trim($_POST['reseller_auto_suspend_threshold'])) : '0';
            $autoThreshold = (float)$autoThresholdInput;
            $autoDays = isset($_POST['reseller_auto_suspend_days']) ? (int)$_POST['reseller_auto_suspend_days'] : 0;

            if ($siteName === '') {
                $errors[] = 'Site adı zorunludur.';
            }

            if ($autoSuspendEnabled === '1') {
                if ($autoThreshold <= 0) {
                    $errors[] = 'Otomatik pasife alma için minimum bakiye değeri pozitif olmalıdır.';
                }
                if ($autoDays <= 0) {
                    $errors[] = 'Otomatik pasife alma için gün sayısı pozitif olmalıdır.';
                }
            }

            if (!$errors) {
                Settings::set('site_name', $siteName);
                Settings::set('site_tagline', $siteTagline !== '' ? $siteTagline : null);
                Settings::set('seo_meta_description', $metaDescription !== '' ? $metaDescription : null);
                Settings::set('seo_meta_keywords', $metaKeywords !== '' ? $metaKeywords : null);
                Settings::set('pricing_commission_rate', (string)$commissionRate);

                Settings::set('platform_default_locale', $defaultLocale);
                foreach ($featureLabels as $key => $label) {
                    $enabled = isset($_POST['features'][$key]);
                    FeatureToggle::setEnabled($key, $enabled);
                    $featureStates[$key] = $enabled;
                }

                Settings::set('reseller_auto_suspend_enabled', $autoSuspendEnabled);
                if ($autoSuspendEnabled === '1') {
                    Settings::set('reseller_auto_suspend_threshold', number_format($autoThreshold, 2, '.', ''));
                    Settings::set('reseller_auto_suspend_days', (string)$autoDays);
                } else {
                    Settings::set('reseller_auto_suspend_threshold', null);
                    Settings::set('reseller_auto_suspend_days', null);
                }

                $success = 'Genel ayarlar kaydedildi.';
                AuditLog::record(
                    $currentUser['id'],
                    'settings.general.update',
                    'settings',
                    null,
                    'Genel ayarlar güncellendi'
                );

                $current = Settings::getMany(array(
                    'site_name',
                    'site_tagline',
                    'seo_meta_description',
                    'seo_meta_keywords',
                    'pricing_commission_rate',
                    'reseller_auto_suspend_enabled',
                    'reseller_auto_suspend_threshold',
                    'reseller_auto_suspend_days',
                    'platform_default_locale',
                ));
            }
    }
}

$pageTitle = 'Genel Ayarlar';

include __DIR__ . '/../templates/header.php';
?>
<div class="row justify-content-center g-4">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Site Bilgileri</h5>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
                <?php endif; ?>

                <form method="post" class="vstack gap-4">
                    <input type="hidden" name="action" value="save_general">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Site Adı</label>
                            <input type="text" name="site_name" class="form-control" value="<?= Helpers::sanitize(isset($current['site_name']) ? $current['site_name'] : Helpers::siteName()) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Site Sloganı</label>
                            <input type="text" name="site_tagline" class="form-control" value="<?= Helpers::sanitize(isset($current['site_tagline']) ? $current['site_tagline'] : '') ?>" placeholder="Opsiyonel">
                        </div>
                        <div class="col-12">
                            <label class="form-label">SEO Açıklaması</label>
                            <textarea name="seo_meta_description" class="form-control" rows="3" placeholder="Arama motorları için kısa açıklama"><?= Helpers::sanitize(isset($current['seo_meta_description']) ? $current['seo_meta_description'] : '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">SEO Anahtar Kelimeler</label>
                            <input type="text" name="seo_meta_keywords" class="form-control" value="<?= Helpers::sanitize(isset($current['seo_meta_keywords']) ? $current['seo_meta_keywords'] : '') ?>" placeholder="Virgülle ayırın">
                        </div>
                    </div>

                    <hr>

                    <div class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <label class="form-label">Ürün Satış Komisyonu (%)</label>
                            <input type="number" name="pricing_commission_rate" step="0.01" min="0" class="form-control" value="<?= Helpers::sanitize(isset($current['pricing_commission_rate']) ? $current['pricing_commission_rate'] : '0') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Varsayılan Platform Dili</label>
                            <select name="platform_default_locale" class="form-select">
                                <?php foreach (App\Lang::availableLocales() as $locale): ?>
                                    <option value="<?= Helpers::sanitize($locale) ?>" <?= isset($current['platform_default_locale']) && $current['platform_default_locale'] === $locale ? 'selected' : '' ?>><?= strtoupper(Helpers::sanitize($locale)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Bayi profili aksi seçmedikçe bu dil kullanılır.</small>
                        </div>
                    </div>

                    <hr>

                    <div>
                        <h6>Özellik Yönetimi</h6>
                        <div class="row g-3">
                            <?php foreach ($featureLabels as $key => $label): ?>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="feature<?= Helpers::sanitize($key) ?>" name="features[<?= Helpers::sanitize($key) ?>]" <?= !empty($featureStates[$key]) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="feature<?= Helpers::sanitize($key) ?>"><?= Helpers::sanitize($label) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <hr>

                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="autoSuspend" name="reseller_auto_suspend_enabled" <?= isset($current['reseller_auto_suspend_enabled']) && $current['reseller_auto_suspend_enabled'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="autoSuspend">Düşük bakiyede bayiliği pasife al</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Minimum Bakiye (₺)</label>
                            <input type="number" step="0.01" min="0" name="reseller_auto_suspend_threshold" class="form-control" value="<?= Helpers::sanitize(isset($current['reseller_auto_suspend_threshold']) ? $current['reseller_auto_suspend_threshold'] : '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pasife Alma Süresi (gün)</label>
                            <input type="number" min="0" name="reseller_auto_suspend_days" class="form-control" value="<?= Helpers::sanitize(isset($current['reseller_auto_suspend_days']) ? $current['reseller_auto_suspend_days'] : '') ?>">
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Belirlenen tutarın altına düşen bayiler bu süre sonunda otomatik olarak pasif duruma geçirilir.</small>
                        </div>
                    </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
