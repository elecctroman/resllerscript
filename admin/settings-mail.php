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
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromName = isset($_POST['mail_from_name']) ? trim($_POST['mail_from_name']) : '';
    $fromAddress = isset($_POST['mail_from_address']) ? trim($_POST['mail_from_address']) : '';
    $replyTo = isset($_POST['mail_reply_to']) ? trim($_POST['mail_reply_to']) : '';
    $footer = isset($_POST['mail_footer']) ? trim($_POST['mail_footer']) : '';

    if (!$fromName) {
        $errors[] = 'Gönderen adı zorunludur.';
    }

    if (!$fromAddress || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir gönderen e-posta adresi girin.';
    }

    if ($replyTo && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir yanıt adresi girin.';
    }

    if (!$errors) {
        Settings::set('mail_from_name', $fromName);
        Settings::set('mail_from_address', $fromAddress);
        Settings::set('mail_reply_to', $replyTo ?: null);
        Settings::set('mail_footer', $footer ?: null);

        $success = 'Mail ayarları kaydedildi.';
        $current = [
            'mail_from_name' => $fromName,
            'mail_from_address' => $fromAddress,
            'mail_reply_to' => $replyTo,
            'mail_footer' => $footer,
        ];
    }
}

$pageTitle = 'Mail Ayarları';

include __DIR__ . '/../templates/header.php';
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-8 col-xxl-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Bildirim E-postaları</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Şablonlar ve gönderici bilgileri buradan yönetilir. Bu bilgiler sistem e-postalarında otomatik kullanılır.</p>

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

                <form method="post" class="vstack gap-3">
                    <div>
                        <label class="form-label">Gönderen Adı</label>
                        <input type="text" name="mail_from_name" class="form-control" value="<?= Helpers::sanitize(isset($current['mail_from_name']) ? $current['mail_from_name'] : '') ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Gönderen E-posta</label>
                        <input type="email" name="mail_from_address" class="form-control" value="<?= Helpers::sanitize(isset($current['mail_from_address']) ? $current['mail_from_address'] : '') ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Yanıt E-postası</label>
                        <input type="email" name="mail_reply_to" class="form-control" value="<?= Helpers::sanitize(isset($current['mail_reply_to']) ? $current['mail_reply_to'] : '') ?>" placeholder="Opsiyonel">
                    </div>
                    <div>
                        <label class="form-label">E-posta Alt Metni</label>
                        <textarea name="mail_footer" class="form-control" rows="4" placeholder="İsteğe bağlı kapanış mesajı."><?= Helpers::sanitize(isset($current['mail_footer']) ? $current['mail_footer'] : '') ?></textarea>
                        <small class="text-muted">Bu alan tüm sistem e-postalarının sonuna eklenir.</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
