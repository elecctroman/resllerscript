<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Currency;
use App\FeatureToggle;
use App\Helpers;
use App\Integrations\ProviderClient;
use App\Settings;

$providerErrors = array();
$providerSuccess = '';

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
    'provider_api_url',
    'provider_api_key',
));

$featureLabels = array(
    'products' => 'Ürün kataloğu ve sipariş verme',
    'orders' => 'Sipariş geçmişi görüntüleme',
    'balance' => 'Bakiye yönetimi',
    'support' => 'Destek talepleri',
    'packages' => 'Bayilik paketleri başvurusu',
    'api' => 'API erişimi',
    'premium_modules' => 'Premium modül pazarı',
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
        } elseif ($action === 'save_provider' || $action === 'test_provider') {
            $apiUrlInput = isset($_POST['provider_api_url']) ? trim($_POST['provider_api_url']) : (isset($current['provider_api_url']) ? $current['provider_api_url'] : '');
            $apiKeyInput = isset($_POST['provider_api_key']) ? trim($_POST['provider_api_key']) : (isset($current['provider_api_key']) ? $current['provider_api_key'] : '');

            if ($apiUrlInput !== '' && !filter_var($apiUrlInput, FILTER_VALIDATE_URL)) {
                $providerErrors[] = 'Geçerli bir API adresi girmeniz gerekiyor.';
            }

            if ($action === 'save_provider' && !$providerErrors) {
                $normalizedUrl = null;
                if ($apiUrlInput !== '') {
                    $normalizedUrl = rtrim($apiUrlInput, '/');
                    if (preg_match('#/api$#i', $normalizedUrl)) {
                        $normalizedUrl = rtrim(substr($normalizedUrl, 0, -4), '/');
                    }
                }
                Settings::set('provider_api_url', $normalizedUrl);
                Settings::set('provider_api_key', $apiKeyInput !== '' ? $apiKeyInput : null);
                $providerSuccess = 'Sağlayıcı API bilgileri kaydedildi.';

                $current['provider_api_url'] = $normalizedUrl;
                $current['provider_api_key'] = $apiKeyInput !== '' ? $apiKeyInput : null;
            } elseif ($action === 'test_provider' && !$providerErrors) {
                if ($apiUrlInput === '' || $apiKeyInput === '') {
                    $providerErrors[] = 'API adresi ve anahtarı zorunludur.';
                } else {
                    try {
                        $client = new ProviderClient($apiUrlInput, $apiKeyInput);
                        $testResponse = $client->testConnection();

                        if (isset($testResponse['success']) && $testResponse['success']) {
                            $providerSuccess = 'API bağlantısı doğrulandı.';
                        } else {
                            $providerErrors[] = 'API yanıtı doğrulanamadı. Lütfen bilgileri kontrol edin.';
                        }
                    } catch (\RuntimeException $exception) {
                        $providerErrors[] = 'API testi başarısız oldu: ' . $exception->getMessage();
                    }
                }
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
                    'provider_api_url',
                    'provider_api_key',
                ));
            }
        }
    }
}

$rate = Currency::getRate('TRY', 'USD');
$tryPerUsd = $rate > 0 ? 1 / $rate : null;
$rateUpdatedAt = Settings::get('currency_rate_TRY_USD_updated');

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

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
                </div>
            </form>
        </div>
    </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Sağlayıcı API</h5>
                <span class="text-muted small">Lotus Lisans entegrasyonu</span>
            </div>
            <div class="card-body">
                <?php if ($providerErrors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($providerErrors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($providerSuccess): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($providerSuccess) ?></div>
                <?php endif; ?>

                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">API Adresi</label>
                            <input type="url" name="provider_api_url" class="form-control" placeholder="https://partner.example.com" value="<?= Helpers::sanitize(isset($current['provider_api_url']) ? $current['provider_api_url'] : '') ?>">
                            <div class="form-text">Adresin sonuna <strong>/api</strong> eklemeyin; sistem uç noktaları otomatik tamamlar.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">API Anahtarı</label>
                            <input type="text" name="provider_api_key" class="form-control" placeholder="API anahtarınızı girin" value="<?= Helpers::sanitize(isset($current['provider_api_key']) ? $current['provider_api_key'] : '') ?>">
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <button type="submit" name="action" value="save_provider" class="btn btn-primary">Ayarları Kaydet</button>
                        <button type="submit" name="action" value="test_provider" class="btn btn-outline-secondary">Bağlantıyı Test Et</button>
                        <a href="/admin/providers.php" class="btn btn-outline-primary">Sağlayıcıyı Görüntüle</a>
                    </div>
                    <p class="text-muted small mb-0">Sağlayıcı API bilgilerini kaydettikten sonra, ürünlerinizde "Lotus" sağlayıcısını seçerek siparişlerin otomatik iletilmesini sağlayabilirsiniz.</p>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
