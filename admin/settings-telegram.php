<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Helpers;
use App\Settings;
use App\Telegram;

Auth::requireRoles(array('super_admin', 'admin'));

$currentUser = $_SESSION['user'];
$errors = array();
$success = '';

$currentValues = Settings::getMany([
    'telegram_bot_token',
    'telegram_chat_id',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $botToken = isset($_POST['bot_token']) ? trim($_POST['bot_token']) : '';
    $chatId = isset($_POST['chat_id']) ? trim($_POST['chat_id']) : '';
    $action = isset($_POST['action']) ? $_POST['action'] : 'save';

    if ($action === 'test') {
        if ($botToken === '' || $chatId === '') {
            $errors[] = 'Test mesajı gönderebilmek için bot token ve sohbet kimliği gereklidir.';
        } else {
            Settings::set('telegram_bot_token', $botToken);
            Settings::set('telegram_chat_id', $chatId);

            Telegram::notify('🔔 Telegram entegrasyon testi başarıyla çalıştı.');
            $success = 'Test mesajı gönderildi.';

            AuditLog::record(
                $currentUser['id'],
                'settings.telegram.test',
                'settings',
                null,
                'Telegram test mesajı gönderildi'
            );
        }
    } else {
        Settings::set('telegram_bot_token', $botToken);
        Settings::set('telegram_chat_id', $chatId);

        $success = 'Telegram entegrasyon ayarları güncellendi.';
        $currentValues['telegram_bot_token'] = $botToken;
        $currentValues['telegram_chat_id'] = $chatId;

        AuditLog::record(
            $currentUser['id'],
            'settings.telegram.update',
            'settings',
            null,
            'Telegram ayarları güncellendi'
        );
    }

    $currentValues['telegram_bot_token'] = Settings::get('telegram_bot_token');
    $currentValues['telegram_chat_id'] = Settings::get('telegram_chat_id');
}

$pageTitle = 'Telegram Entegrasyonu';

include __DIR__ . '/../templates/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Telegram Bildirimleri</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">Sipariş ve bakiye bildirimlerini Telegram üzerinden almak için bot bilgilerinizi girin.</p>

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
                <label class="form-label">Bot Token</label>
                <input type="text" name="bot_token" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['telegram_bot_token']) ? $currentValues['telegram_bot_token'] : '') ?>" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" required>
            </div>
            <div>
                <label class="form-label">Sohbet Kimliği</label>
                <input type="text" name="chat_id" class="form-control" value="<?= Helpers::sanitize(isset($currentValues['telegram_chat_id']) ? $currentValues['telegram_chat_id'] : '') ?>" placeholder="@kanal_adı veya kullanıcı ID" required>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="action" value="save" class="btn btn-primary">Ayarları Kaydet</button>
                <button type="submit" name="action" value="test" class="btn btn-outline-secondary">Test Mesajı Gönder</button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
