<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;
use App\Settings;

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    Helpers::redirect('/');
}

$errors = [];
$success = '';

$currentValues = Settings::getMany([
    'cryptomus_enabled',
    'cryptomus_merchant_uuid',
    'cryptomus_api_key',
    'cryptomus_base_url',
    'cryptomus_success_url',
    'cryptomus_fail_url',
    'cryptomus_network',
    'cryptomus_description',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enabled = isset($_POST['enabled']) ? '1' : '0';
    $merchant = isset($_POST['merchant_uuid']) ? trim($_POST['merchant_uuid']) : '';
    $apiKey = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
    $baseUrl = isset($_POST['base_url']) ? trim($_POST['base_url']) : '';
    $successUrl = isset($_POST['success_url']) ? trim($_POST['success_url']) : '';
    $failUrl = isset($_POST['fail_url']) ? trim($_POST['fail_url']) : '';
    $network = isset($_POST['network']) ? trim($_POST['network']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    if ($enabled === '1' && ($merchant === '' || $apiKey === '')) {
        $errors[] = 'Cryptomus entegrasyonunu aktifleştirmek için Merchant UUID ve API anahtarı zorunludur.';
    }

    if (!$errors) {
        Settings::set('cryptomus_enabled', $enabled);
        Settings::set('cryptomus_merchant_uuid', $merchant);
        Settings::set('cryptomus_api_key', $apiKey);
        Settings::set('cryptomus_base_url', $baseUrl !== '' ? $baseUrl : 'https://api.cryptomus.com/v1');
        Settings::set('cryptomus_success_url', $successUrl);
        Settings::set('cryptomus_fail_url', $failUrl);
        Settings::set('cryptomus_network', $network);
        Settings::set('cryptomus_description', $description);

        $success = 'Ödeme yöntemi ayarları güncellendi.';
        $currentValues = Settings::getMany(array_keys($currentValues));
    }
}

$pageTitle = 'Ödeme Methodları';

include __DIR__ . '/../templates/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Cryptomus Ayarları</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">Bakiye yükleme ve paket satışlarında Cryptomus kullanarak otomatik ödeme alın.</p>

        <div class="alert alert-info small">
            <strong>Callback URL:</strong> <code><?= Helpers::sanitize((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'example.com') . '/webhooks/cryptomus.php') ?></code>
            <br>Bu adresi Cryptomus panelinizdeki bildirim adresi olarak ekleyin.
        </div>

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

        <form method="post" class="row g-3">
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="cryptomusEnabled" name="enabled" <?= isset($currentValues['cryptomus_enabled']) && $currentValues['cryptomus_enabled'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="cryptomusEnabled">Cryptomus ödemelerini aktifleştir</label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Merchant UUID</label>
                <input type="text" name="merchant_uuid" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['cryptomus_merchant_uuid']) ? $currentValues['cryptomus_merchant_uuid'] : '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Payment API Key</label>
                <input type="text" name="api_key" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['cryptomus_api_key']) ? $currentValues['cryptomus_api_key'] : '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">API Adresi</label>
                <input type="text" name="base_url" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['cryptomus_base_url']) ? $currentValues['cryptomus_base_url'] : 'https://api.cryptomus.com/v1') ?>" placeholder="https://api.cryptomus.com/v1">
            </div>
            <div class="col-md-6">
                <label class="form-label">Ağ (Opsiyonel)</label>
                <input type="text" name="network" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['cryptomus_network']) ? $currentValues['cryptomus_network'] : '') ?>" placeholder="TRC20, ERC20 vb.">
            </div>
            <div class="col-md-6">
                <label class="form-label">Başarılı Ödeme Yönlendirmesi</label>
                <input type="text" name="success_url" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['cryptomus_success_url']) ? $currentValues['cryptomus_success_url'] : '') ?>" placeholder="https://alanadi.com/register.php?success=1">
            </div>
            <div class="col-md-6">
                <label class="form-label">Başarısız Ödeme Yönlendirmesi</label>
                <input type="text" name="fail_url" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['cryptomus_fail_url']) ? $currentValues['cryptomus_fail_url'] : '') ?>" placeholder="https://alanadi.com/register.php?failed=1">
            </div>
            <div class="col-12">
                <label class="form-label">Ödeme Açıklaması</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Fatura açıklaması olarak görüntülenecek metin."><?= Helpers::sanitize(isset($currentValues['cryptomus_description']) ? $currentValues['cryptomus_description'] : '') ?></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
