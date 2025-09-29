<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;
use App\Settings;

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    Helpers::redirect('/');
}

$errors = [];
$success = '';

$current = Settings::getMany([
    'mail_from_name',
    'mail_from_address',
    'mail_reply_to',
    'mail_footer',
    'smtp_enabled',
    'smtp_host',
    'smtp_port',
    'smtp_username',
    'smtp_password',
    'smtp_encryption',
    'smtp_timeout',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromName = isset($_POST['mail_from_name']) ? trim($_POST['mail_from_name']) : '';
    $fromAddress = isset($_POST['mail_from_address']) ? trim($_POST['mail_from_address']) : '';
    $replyTo = isset($_POST['mail_reply_to']) ? trim($_POST['mail_reply_to']) : '';
    $footer = isset($_POST['mail_footer']) ? trim($_POST['mail_footer']) : '';

    if ($fromName === '') {
        $errors[] = 'Gönderen adı zorunludur.';
    }

    if ($fromAddress === '' || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir gönderen e-posta adresi girin.';
    }

    if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir yanıt adresi girin.';
    }

    $smtpEnabled = isset($_POST['smtp_enabled']) ? '1' : '0';
    $smtpHost = isset($_POST['smtp_host']) ? trim($_POST['smtp_host']) : '';
    $smtpPort = isset($_POST['smtp_port']) ? (int)$_POST['smtp_port'] : 587;
    $smtpUsername = isset($_POST['smtp_username']) ? trim($_POST['smtp_username']) : '';
    $smtpPassword = isset($_POST['smtp_password']) ? trim($_POST['smtp_password']) : '';
    $smtpEncryption = isset($_POST['smtp_encryption']) ? strtolower(trim($_POST['smtp_encryption'])) : 'tls';
    $smtpTimeout = isset($_POST['smtp_timeout']) ? (int)$_POST['smtp_timeout'] : 15;

    if ($smtpEnabled === '1') {
        if ($smtpHost === '') {
            $errors[] = 'SMTP host alanı zorunludur.';
        }

        if ($smtpPort <= 0) {
            $errors[] = 'Geçerli bir SMTP port değeri girin.';
        }

        if ($smtpEncryption !== 'ssl' && $smtpEncryption !== 'tls' && $smtpEncryption !== 'none') {
            $errors[] = 'Geçerli bir SMTP şifreleme türü seçin (ssl, tls veya none).';
        }

        if ($smtpTimeout <= 0) {
            $smtpTimeout = 15;
        }
    }

    if (!$errors) {
        Settings::set('mail_from_name', $fromName);
        Settings::set('mail_from_address', $fromAddress);
        Settings::set('mail_reply_to', $replyTo !== '' ? $replyTo : null);
        Settings::set('mail_footer', $footer !== '' ? $footer : null);

        Settings::set('smtp_enabled', $smtpEnabled);
        Settings::set('smtp_host', $smtpHost !== '' ? $smtpHost : null);
        Settings::set('smtp_port', (string)$smtpPort);
        Settings::set('smtp_username', $smtpUsername !== '' ? $smtpUsername : null);
        Settings::set('smtp_password', $smtpPassword !== '' ? $smtpPassword : null);
        Settings::set('smtp_encryption', $smtpEncryption !== '' ? $smtpEncryption : 'tls');
        Settings::set('smtp_timeout', (string)$smtpTimeout);

        $success = 'Mail ayarları kaydedildi.';
        $current = [
            'mail_from_name' => $fromName,
            'mail_from_address' => $fromAddress,
            'mail_reply_to' => $replyTo,
            'mail_footer' => $footer,
            'smtp_enabled' => $smtpEnabled,
            'smtp_host' => $smtpHost,
            'smtp_port' => (string)$smtpPort,
            'smtp_username' => $smtpUsername,
            'smtp_password' => $smtpPassword,
            'smtp_encryption' => $smtpEncryption,
            'smtp_timeout' => (string)$smtpTimeout,
        ];
    }
}

$pageTitle = 'Mail Ayarları';

include __DIR__ . '/../templates/header.php';
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xxl-8">
        <form method="post" class="vstack gap-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Bildirim E-postaları</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Şablonlar ve gönderen bilgileri buradan yönetilir. Bu bilgiler sistem e-postalarında otomatik kullanılır.</p>

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

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Gönderen Adı</label>
                            <input type="text" name="mail_from_name" class="form-control" value="<?= Helpers::sanitize(isset($current['mail_from_name']) ? $current['mail_from_name'] : '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gönderen E-posta</label>
                            <input type="email" name="mail_from_address" class="form-control" value="<?= Helpers::sanitize(isset($current['mail_from_address']) ? $current['mail_from_address'] : '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Yanıt E-postası</label>
                            <input type="email" name="mail_reply_to" class="form-control" value="<?= Helpers::sanitize(isset($current['mail_reply_to']) ? $current['mail_reply_to'] : '') ?>" placeholder="Opsiyonel">
                        </div>
                        <div class="col-12">
                            <label class="form-label">E-posta Alt Metni</label>
                            <textarea name="mail_footer" class="form-control" rows="4" placeholder="İsteğe bağlı kapanış mesaj."><?= Helpers::sanitize(isset($current['mail_footer']) ? $current['mail_footer'] : '') ?></textarea>
                            <small class="text-muted">Bu alan tüm sistem e-postalarının sonuna eklenir.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">SMTP Sunucusu</h5>
                        <small class="text-muted">Daha yüksek teslimat oranı için kendi SMTP hesabınızı kullanın.</small>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="smtpEnabled" name="smtp_enabled" <?= isset($current['smtp_enabled']) && $current['smtp_enabled'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="smtpEnabled">Aktif</label>
                    </div>
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?= Helpers::sanitize(isset($current['smtp_host']) ? $current['smtp_host'] : '') ?>" placeholder="smtp.example.com">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?= Helpers::sanitize(isset($current['smtp_port']) ? $current['smtp_port'] : '587') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Şifreleme</label>
                        <select name="smtp_encryption" class="form-select">
                            <?php
                            $encryption = isset($current['smtp_encryption']) ? strtolower($current['smtp_encryption']) : 'tls';
                            $options = array('tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'Şifreleme Yok');
                            foreach ($options as $value => $label):
                                $selected = $encryption === $value ? 'selected' : '';
                            ?>
                                <option value="<?= Helpers::sanitize($value) ?>" <?= $selected ?>><?= Helpers::sanitize($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kullanıcı Adı</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?= Helpers::sanitize(isset($current['smtp_username']) ? $current['smtp_username'] : '') ?>" placeholder="SMTP hesabı kullanıcı adı">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Şifre</label>
                        <input type="text" name="smtp_password" class="form-control" value="<?= Helpers::sanitize(isset($current['smtp_password']) ? $current['smtp_password'] : '') ?>" placeholder="SMTP hesabı şifresi">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Zaman Aşımı (sn)</label>
                        <input type="number" name="smtp_timeout" class="form-control" value="<?= Helpers::sanitize(isset($current['smtp_timeout']) ? $current['smtp_timeout'] : '15') ?>">
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info small mb-0">
                            SMTP açıkken sistem e-postaları belirttiğiniz sunucu üzerinden gönderilir. Bilgilerinizin doğruluğunu mutlaka kontrol edin.
                        </div>
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
