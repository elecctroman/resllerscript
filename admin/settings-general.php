<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Currency;
use App\DemoMode;
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
    'demo_mode_enabled',
    'lotus_api_enabled',
    'lotus_api_key',
    'lotus_base_url',
    'lotus_use_query_api_key',
    'lotus_timeout',
));

$defaultLotusBaseUrl = 'https://partner.lotuslisans.com.tr';
if (!isset($current['lotus_base_url']) || $current['lotus_base_url'] === null || $current['lotus_base_url'] === '') {
    $current['lotus_base_url'] = $defaultLotusBaseUrl;
}

$featureLabels = array(
    'products' => 'Ürün kataloğu ve sipariş verme',
    'orders' => 'Sipariş geçmişi görüntüleme',
    'balance' => 'Bakiye yönetimi',
    'support' => 'Destek talepleri',
    'packages' => 'Bayilik paketleri başvurusu',
    'api' => 'API erişimi',
);

$featureStates = FeatureToggle::all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'save_general';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum anahtarınız doğrulanamadı. Lütfen sayfayı yenileyin ve tekrar deneyin.';
    } else {
        if ($action === 'refresh_rate') {
            $rate = Currency::refreshRate('TRY', 'USD');
            if ($rate > 0) {
                $success = 'Kur bilgisi başarıyla güncellendi.';
            } else {
                $errors[] = 'Kur servisine ulaşılamadığı için güncelleme yapılamadı.';
            }
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

            $autoSuspendEnabled = isset($_POST['reseller_auto_suspend_enabled']) ? '1' : '0';
            $autoThresholdInput = isset($_POST['reseller_auto_suspend_threshold']) ? str_replace(',', '.', trim($_POST['reseller_auto_suspend_threshold'])) : '0';
            $autoThreshold = (float)$autoThresholdInput;
            $lotusEnabled = isset($_POST['lotus_api_enabled']) ? '1' : '0';
            $lotusApiKey = isset($_POST['lotus_api_key']) ? trim($_POST['lotus_api_key']) : '';
            $lotusBaseUrlInput = isset($_POST['lotus_base_url']) ? trim($_POST['lotus_base_url']) : '';
            $lotusUseQuery = isset($_POST['lotus_use_query_api_key']) ? '1' : '0';
            $lotusTimeoutRaw = isset($_POST['lotus_timeout']) ? trim($_POST['lotus_timeout']) : '';
            $lotusBaseUrl = $lotusBaseUrlInput !== '' ? $lotusBaseUrlInput : $defaultLotusBaseUrl;
            $lotusTimeout = null;
            if ($lotusTimeoutRaw !== '') {
                $lotusTimeout = (float) str_replace(',', '.', $lotusTimeoutRaw);
                if ($lotusTimeout <= 0) {
                    $errors[] = 'Lotus API zaman aşımı pozitif olmalıdır.';
                }
            }

            $autoDays = isset($_POST['reseller_auto_suspend_days']) ? (int)$_POST['reseller_auto_suspend_days'] : 0;

            if ($siteName === '') {
                $errors[] = 'Site adı zorunludur.';
            }

            if ($lotusEnabled === '1' && $lotusApiKey === '') {
                $errors[] = 'Lotus API anahtarı zorunludur.';
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

                foreach ($featureLabels as $key => $label) {
                    $enabled = isset($_POST['features'][$key]);
                    FeatureToggle::setEnabled($key, $enabled);
                    $featureStates[$key] = $enabled;
                }

                Settings::set('lotus_api_enabled', $lotusEnabled);
                Settings::set('lotus_api_key', $lotusEnabled === '1' ? $lotusApiKey : null);
                Settings::set('lotus_base_url', $lotusEnabled === '1' ? $lotusBaseUrl : null);
                Settings::set('lotus_use_query_api_key', $lotusEnabled === '1' ? $lotusUseQuery : '0');
                if ($lotusEnabled === '1' && $lotusTimeout !== null && $lotusTimeout > 0) {
                    Settings::set('lotus_timeout', number_format($lotusTimeout, 2, '.', ''));
                } else {
                    Settings::set('lotus_timeout', null);
                }

                Settings::set('reseller_auto_suspend_enabled', $autoSuspendEnabled);
                if ($autoSuspendEnabled === '1') {
                    Settings::set('reseller_auto_suspend_threshold', number_format($autoThreshold, 2, '.', ''));
                    Settings::set('reseller_auto_suspend_days', (string)$autoDays);
                } else {
                    Settings::set('reseller_auto_suspend_threshold', null);
                    Settings::set('reseller_auto_suspend_days', null);
                }

                $demoModeEnabled = isset($_POST['demo_mode_enabled']) ? '1' : '0';
                Settings::set('demo_mode_enabled', $demoModeEnabled);
                if ($demoModeEnabled === '1') {
                    DemoMode::ensureUser();
                } else {
                    DemoMode::disableUser();
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
                    'demo_mode_enabled',
                    'lotus_api_enabled',
                    'lotus_api_key',
                    'lotus_base_url',
                    'lotus_use_query_api_key',
                    'lotus_timeout',
                ));

                if (!isset($current['lotus_base_url']) || $current['lotus_base_url'] === null || $current['lotus_base_url'] === '') {
                    $current['lotus_base_url'] = $defaultLotusBaseUrl;
                }
            }
        }
    }
}

$rate = Currency::getRate('TRY', 'USD');
$tryPerUsd = $rate > 0 ? 1 / $rate : null;
$rateUpdatedAt = Settings::get('currency_rate_TRY_USD_updated');

$lotusEnabledCurrent = isset($current['lotus_api_enabled']) && $current['lotus_api_enabled'] === '1';
$lotusUseQueryCurrent = isset($current['lotus_use_query_api_key']) && $current['lotus_use_query_api_key'] === '1';
$lotusTimeoutValue = isset($current['lotus_timeout']) ? $current['lotus_timeout'] : '';

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
                        <div class="col-md-8">
                            <div class="currency-card p-3 bg-light rounded">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Güncel Kur</strong>
                                        <div class="text-muted small">
                                            1 USD ≈ <?= $tryPerUsd ? Helpers::sanitize(number_format($tryPerUsd, 4, ',', '.')) : '-' ?> ₺
                                        </div>
                                        <div class="text-muted small">Son güncelleme: <?= $rateUpdatedAt ? Helpers::sanitize(date('d.m.Y H:i', (int)$rateUpdatedAt)) : '-' ?></div>
                                    </div>
                                    <button type="submit" name="action" value="refresh_rate" class="btn btn-outline-primary btn-sm">Kuru Yenile</button>
                                </div>
                            </div>
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
                            <label class="form-label">Minimum Bakiye (USD)</label>
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

                    <hr>

                    <div class="p-3 border rounded bg-light-subtle">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="lotusApiEnabled" name="lotus_api_enabled"<?= $lotusEnabledCurrent ? ' checked' : '' ?>>
                            <label class="form-check-label" for="lotusApiEnabled">Lotus Lisans Partner API entegrasyonunu etkinleştir</label>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">API Anahtarı</label>
                                <input type="text" name="lotus_api_key" class="form-control" value="<?= Helpers::sanitize(isset($current['lotus_api_key']) ? $current['lotus_api_key'] : '') ?>">
                                <div class="form-text">Anahtar, tüm sipariş isteklerinde X-API-Key olarak kullanılacak.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Temel API URL'si</label>
                                <input type="text" name="lotus_base_url" class="form-control" value="<?= Helpers::sanitize(isset($current['lotus_base_url']) ? $current['lotus_base_url'] : $defaultLotusBaseUrl) ?>">
                                <div class="form-text">Varsayılan: <?= Helpers::sanitize($defaultLotusBaseUrl) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">İstek Zaman Aşımı (sn)</label>
                                <input type="number" step="0.1" min="1" name="lotus_timeout" class="form-control" value="<?= Helpers::sanitize($lotusTimeoutValue !== '' ? $lotusTimeoutValue : '') ?>" placeholder="20">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-4 pt-1">
                                    <input class="form-check-input" type="checkbox" id="lotusUseQuery" name="lotus_use_query_api_key"<?= $lotusUseQueryCurrent ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="lotusUseQuery">API anahtarını query parametresiyle gönder</label>
                                </div>
                                <div class="form-text">Güvenlik için mümkünse header kullanımını tercih edin.</div>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0 small">
                            Siparişler otomatik olarak Lotus API'ye iletilir. Ürün eşlemesi için SKU alanına Lotus ürün ID'sini (yalnızca rakam) girmeniz gerekir.
                        </div>
                    </div>

                    <div class="p-3 border rounded bg-light-subtle">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="demoMode" name="demo_mode_enabled" <?= isset($current['demo_mode_enabled']) && $current['demo_mode_enabled'] === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="demoMode">Demo kullanıcı oturumunu etkinleştir</label>
                        </div>
                        <p class="mb-2 small text-muted">Demo hesabı ile ziyaretçiler yönetici arayüzünü görüntüleyebilir ancak hiçbir değişiklik kaydedemez.</p>
                        <ul class="small mb-0">
                            <li>Kullanıcı adı: <code>demo</code></li>
                            <li>E-posta: <code>demo@demo.com</code></li>
                            <li>Şifre: <code>demo123!</code></li>
                        </ul>
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
