<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Helpers;
use App\Settings;

Auth::requireRoles(array('super_admin', 'admin', 'finance'));

$currentUser = $_SESSION['user'];
$errors = array();
$success = '';

$settingKeys = [
    'payment_test_mode',
    'pricing_commission_rate',
    'cryptomus_enabled',
    'cryptomus_merchant_uuid',
    'cryptomus_api_key',
    'cryptomus_base_url',
    'cryptomus_success_url',
    'cryptomus_fail_url',
    'cryptomus_network',
    'cryptomus_description',
    'heleket_enabled',
    'heleket_project_id',
    'heleket_api_key',
    'heleket_base_url',
    'heleket_success_url',
    'heleket_fail_url',
    'heleket_description',
];

$currentValues = Settings::getMany($settingKeys);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testMode = isset($_POST['payment_test_mode']) ? '1' : '0';
    $commissionInput = isset($_POST['pricing_commission_rate']) ? trim($_POST['pricing_commission_rate']) : '';
    $commissionSanitized = preg_replace('/[^0-9.,-]/', '', $commissionInput);
    $commissionSanitized = str_replace(',', '.', (string)$commissionSanitized);
    $commissionRate = $commissionSanitized !== '' ? (float)$commissionSanitized : 0.0;
    if ($commissionRate < 0) {
        $commissionRate = 0.0;
    }

    $cryptomusEnabled = isset($_POST['cryptomus_enabled']) ? '1' : '0';
    $cryptomusMerchant = isset($_POST['cryptomus_merchant_uuid']) ? trim($_POST['cryptomus_merchant_uuid']) : '';
    $cryptomusApiKey = isset($_POST['cryptomus_api_key']) ? trim($_POST['cryptomus_api_key']) : '';
    $cryptomusBase = isset($_POST['cryptomus_base_url']) ? trim($_POST['cryptomus_base_url']) : '';
    $cryptomusSuccess = isset($_POST['cryptomus_success_url']) ? trim($_POST['cryptomus_success_url']) : '';
    $cryptomusFail = isset($_POST['cryptomus_fail_url']) ? trim($_POST['cryptomus_fail_url']) : '';
    $cryptomusNetwork = isset($_POST['cryptomus_network']) ? trim($_POST['cryptomus_network']) : '';
    $cryptomusDescription = isset($_POST['cryptomus_description']) ? trim($_POST['cryptomus_description']) : '';

    if ($cryptomusEnabled === '1' && ($cryptomusMerchant === '' || $cryptomusApiKey === '')) {
        $errors[] = 'Cryptomus entegrasyonunu aktifleştirmek için Merchant UUID ve API anahtarı zorunludur.';
    }

    $heleketEnabled = isset($_POST['heleket_enabled']) ? '1' : '0';
    $heleketProject = isset($_POST['heleket_project_id']) ? trim($_POST['heleket_project_id']) : '';
    $heleketApiKey = isset($_POST['heleket_api_key']) ? trim($_POST['heleket_api_key']) : '';
    $heleketBase = isset($_POST['heleket_base_url']) ? trim($_POST['heleket_base_url']) : '';
    $heleketSuccess = isset($_POST['heleket_success_url']) ? trim($_POST['heleket_success_url']) : '';
    $heleketFail = isset($_POST['heleket_fail_url']) ? trim($_POST['heleket_fail_url']) : '';
    $heleketDescription = isset($_POST['heleket_description']) ? trim($_POST['heleket_description']) : '';

    if ($heleketEnabled === '1' && ($heleketProject === '' || $heleketApiKey === '')) {
        $errors[] = 'Heleket entegrasyonunu aktifleştirmek için Proje ID ve API anahtarı zorunludur.';
    }

    if (!$errors) {
        Settings::set('payment_test_mode', $testMode);
        Settings::set('pricing_commission_rate', (string)$commissionRate);

        Settings::set('cryptomus_enabled', $cryptomusEnabled);
        Settings::set('cryptomus_merchant_uuid', $cryptomusMerchant ?: null);
        Settings::set('cryptomus_api_key', $cryptomusApiKey ?: null);
        Settings::set('cryptomus_base_url', $cryptomusBase !== '' ? $cryptomusBase : 'https://api.cryptomus.com/v1');
        Settings::set('cryptomus_success_url', $cryptomusSuccess ?: null);
        Settings::set('cryptomus_fail_url', $cryptomusFail ?: null);
        Settings::set('cryptomus_network', $cryptomusNetwork ?: null);
        Settings::set('cryptomus_description', $cryptomusDescription ?: null);

        Settings::set('heleket_enabled', $heleketEnabled);
        Settings::set('heleket_project_id', $heleketProject ?: null);
        Settings::set('heleket_api_key', $heleketApiKey ?: null);
        Settings::set('heleket_base_url', $heleketBase !== '' ? $heleketBase : 'https://merchant.heleket.com/api');
        Settings::set('heleket_success_url', $heleketSuccess ?: null);
        Settings::set('heleket_fail_url', $heleketFail ?: null);
        Settings::set('heleket_description', $heleketDescription ?: null);

        $success = 'Ödeme ayarları güncellendi.';
        AuditLog::record(
            $currentUser['id'],
            'settings.payments.update',
            'settings',
            null,
            'Ödeme ayarları güncellendi'
        );
        $currentValues = Settings::getMany($settingKeys);
    }
}

$pageTitle = 'Ödeme Methodları';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Genel Ayarlar</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Test modu aktifken panelden başlatılan tüm ödeme işlemleri otomatik olarak onaylanır ve bakiye/paket teslimatı anında gerçekleştirilir.</p>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="paymentTestMode" name="payment_test_mode" form="paymentSettingsForm" <?= isset($currentValues['payment_test_mode']) && $currentValues['payment_test_mode'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="paymentTestMode">Test modunu aktifleştir</label>
                </div>
                <div class="alert alert-warning mt-3 mb-0 small">
                    Test modunu sadece geliştirme veya entegrasyon sırasında kullanın.
                </div>
                <div class="mt-4">
                    <label class="form-label">Komisyon Oranı (%)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="pricing_commission_rate" form="paymentSettingsForm" value="<?= Helpers::sanitize(isset($currentValues['pricing_commission_rate']) ? $currentValues['pricing_commission_rate'] : '0') ?>">
                    <small class="text-muted">Ürün satış fiyatı, girilen komisyon oranı eklenerek hesaplanır.</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <form method="post" id="paymentSettingsForm" class="vstack gap-4">
            <?php if ($errors): ?>
                <div class="alert alert-danger mb-0">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= Helpers::sanitize($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success mb-0"><?= Helpers::sanitize($success) ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Cryptomus</h5>
                        <small class="text-muted">Kripto ile bakiye ve paket ödemeleri alın.</small>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="cryptomusEnabled" name="cryptomus_enabled" <?= isset($currentValues['cryptomus_enabled']) && $currentValues['cryptomus_enabled'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cryptomusEnabled">Aktif</label>
                    </div>
                </div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <div class="alert alert-info small mb-0">
                            <strong>Callback URL:</strong>
                            <code><?= Helpers::sanitize((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'example.com') . '/webhooks/cryptomus.php') ?></code>
                            <br>Bu adresi Cryptomus panelinizde webhook olarak tanımlayın.
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Merchant UUID</label>
                        <input type="text" name="cryptomus_merchant_uuid" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['cryptomus_merchant_uuid']) ? $currentValues['cryptomus_merchant_uuid'] : '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment API Key</label>
                        <input type="text" name="cryptomus_api_key" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['cryptomus_api_key']) ? $currentValues['cryptomus_api_key'] : '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">API Adresi</label>
                        <input type="text" name="cryptomus_base_url" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['cryptomus_base_url']) ? $currentValues['cryptomus_base_url'] : 'https://api.cryptomus.com/v1') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ağ (Opsiyonel)</label>
                        <input type="text" name="cryptomus_network" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['cryptomus_network']) ? $currentValues['cryptomus_network'] : '') ?>" placeholder="TRC20, ERC20 vb.">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Başarılı Ödeme Yönlendirmesi</label>
                        <input type="text" name="cryptomus_success_url" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['cryptomus_success_url']) ? $currentValues['cryptomus_success_url'] : '') ?>" placeholder="https://alanadi.com/balance.php">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Başarısız Ödeme Yönlendirmesi</label>
                        <input type="text" name="cryptomus_fail_url" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['cryptomus_fail_url']) ? $currentValues['cryptomus_fail_url'] : '') ?>" placeholder="https://alanadi.com/balance.php?failed=1">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Ödeme Açıklaması</label>
                        <textarea name="cryptomus_description" class="form-control" rows="3" placeholder="Fatura açıklaması olarak görüntülenecek metin."><?= Helpers::sanitize(isset($currentValues['cryptomus_description']) ? $currentValues['cryptomus_description'] : '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Heleket</h5>
                        <small class="text-muted">Sanal POS ve alternatif ödeme yöntemleriyle tahsilat yapın.</small>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="heleketEnabled" name="heleket_enabled" <?= isset($currentValues['heleket_enabled']) && $currentValues['heleket_enabled'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="heleketEnabled">Aktif</label>
                    </div>
                </div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <div class="alert alert-info small mb-0">
                            <strong>Callback URL:</strong>
                            <code><?= Helpers::sanitize((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'example.com') . '/webhooks/heleket.php') ?></code>
                            <br>Heleket panelinde ödeme bildirimi adresi olarak bu URL'yi tanımlayın.
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Proje ID</label>
                        <input type="text" name="heleket_project_id" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['heleket_project_id']) ? $currentValues['heleket_project_id'] : '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">API Anahtarı</label>
                        <input type="text" name="heleket_api_key" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['heleket_api_key']) ? $currentValues['heleket_api_key'] : '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">API Adresi</label>
                        <input type="text" name="heleket_base_url" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['heleket_base_url']) ? $currentValues['heleket_base_url'] : 'https://merchant.heleket.com/api') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Başarılı Ödeme Yönlendirmesi</label>
                        <input type="text" name="heleket_success_url" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['heleket_success_url']) ? $currentValues['heleket_success_url'] : '') ?>" placeholder="https://alanadi.com/balance.php?success=1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Başarısız Ödeme Yönlendirmesi</label>
                        <input type="text" name="heleket_fail_url" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['heleket_fail_url']) ? $currentValues['heleket_fail_url'] : '') ?>" placeholder="https://alanadi.com/balance.php?failed=1">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Ödeme Açıklaması</label>
                        <textarea name="heleket_description" class="form-control" rows="3" placeholder="Heleket faturasında gösterilecek açıklama."><?= Helpers::sanitize(isset($currentValues['heleket_description']) ? $currentValues['heleket_description'] : '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
