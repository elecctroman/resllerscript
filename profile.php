<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

$pdo = Database::connection();
$errors = array();
$successMessages = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'profile';
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($csrfToken)) {
        $errors[] = 'Oturum doğrulama anahtarınız geçersiz. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        if ($action === 'profile') {
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
            $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
            $newPasswordConfirm = isset($_POST['new_password_confirmation']) ? $_POST['new_password_confirmation'] : '';
            $telegramBotToken = isset($_POST['telegram_bot_token']) ? trim($_POST['telegram_bot_token']) : '';
            $telegramChatId = isset($_POST['telegram_chat_id']) ? trim($_POST['telegram_chat_id']) : '';
            $locale = isset($_POST['locale']) ? strtolower((string)$_POST['locale']) : '';
            $currency = isset($_POST['currency']) ? strtoupper((string)$_POST['currency']) : '';

            $availableLocales = App\Lang::availableLocales();
            if (!in_array($locale, $availableLocales, true)) {
                $locale = App\Lang::defaultLocale();
            }

            $currencyOptions = array('TRY', 'USD', 'EUR');
            if (!in_array($currency, $currencyOptions, true)) {
                $currency = Helpers::activeCurrency();
            }

            if ($name === '') {
                $errors[] = 'Ad alanı zorunludur.';
            }

            if ($telegramBotToken === '' || $telegramChatId === '') {
                $errors[] = 'Telegram bot tokenı ve sohbet kimliği zorunludur.';
            }

            $changingPassword = $newPassword !== '' || $newPasswordConfirm !== '';

            if ($changingPassword) {
                if ($currentPassword === '') {
                    $errors[] = 'Şifrenizi değiştirmek için mevcut şifrenizi girmeniz gerekir.';
                }

                if ($newPassword === '' || $newPasswordConfirm === '') {
                    $errors[] = 'Yeni şifre alanları boş bırakılamaz.';
                }

                if ($newPassword !== '' && $newPasswordConfirm !== '' && $newPassword !== $newPasswordConfirm) {
                    $errors[] = 'Yeni şifre alanları birbiriyle eşleşmiyor.';
                }

                if ($newPassword !== '' && strlen($newPassword) < 8) {
                    $errors[] = 'Yeni şifre en az 8 karakter olmalıdır.';
                }
            }

            if (!$errors) {
                try {
                    $pdo->beginTransaction();

                    if ($changingPassword) {
                        $passwordStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
                        $passwordStmt->execute(array('id' => $user['id']));
                        $passwordRow = $passwordStmt->fetch();

                        if (!$passwordRow || !password_verify($currentPassword, $passwordRow['password_hash'])) {
                            $errors[] = 'Mevcut şifreniz doğrulanamadı.';
                        }
                    }

                    if (!$errors) {
                        $pdo->prepare('UPDATE users SET name = :name, telegram_bot_token = :bot, telegram_chat_id = :chat, locale = :locale, currency = :currency, updated_at = NOW() WHERE id = :id')->execute(array(
                            'name' => $name,
                            'bot' => $telegramBotToken,
                            'chat' => $telegramChatId,
                            'locale' => $locale,
                            'currency' => $currency,
                            'id' => $user['id'],
                        ));

                        if ($changingPassword) {
                            $pdo->prepare('UPDATE users SET password_hash = :password WHERE id = :id')->execute(array(
                                'password' => password_hash($newPassword, PASSWORD_BCRYPT),
                                'id' => $user['id'],
                            ));
                        }

                        $pdo->commit();

                        $freshUser = Auth::findUser($user['id']);
                        if ($freshUser) {
                            $_SESSION['user'] = $freshUser;
                            $user = $freshUser;
                        }

                        $successMessages[] = 'Profil bilgileriniz güncellendi.';

                        if ($changingPassword) {
                            $successMessages[] = 'Şifreniz başarıyla değiştirildi.';
                        }
                    }

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                } catch (\PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Profiliniz güncellenirken bir hata oluştu: ' . $exception->getMessage();
                }
            }
        } elseif ($action === 'notifications') {
            $prefOrder = isset($_POST['notify_order_completed']) ? '1' : '0';
            $prefBalance = isset($_POST['notify_balance_approved']) ? '1' : '0';
            $prefSupport = isset($_POST['notify_support_replied']) ? '1' : '0';

            try {
                $pdo->prepare('UPDATE users SET notify_order_completed = :order_pref, notify_balance_approved = :balance_pref, notify_support_replied = :support_pref, updated_at = NOW() WHERE id = :id')
                    ->execute(array(
                        'order_pref' => $prefOrder,
                        'balance_pref' => $prefBalance,
                        'support_pref' => $prefSupport,
                        'id' => $user['id'],
                    ));

                $freshUser = Auth::findUser($user['id']);
                if ($freshUser) {
                    $_SESSION['user'] = $freshUser;
                    $user = $freshUser;
                }

                $successMessages[] = 'Telegram bildirim tercihlerin güncellendi.';
            } catch (\PDOException $exception) {
                $errors[] = 'Bildirim tercihlerin kaydedilirken bir hata oluştu: ' . $exception->getMessage();
            }
        }
    }
}

$pageTitle = 'Profilim';

include __DIR__ . '/templates/header.php';
?>
<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= Helpers::sanitize($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($successMessages): ?>
    <div class="alert alert-success">
        <ul class="mb-0">
            <?php foreach ($successMessages as $message): ?>
                <li><?= Helpers::sanitize($message) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Profil Bilgileri</h5>
                <small class="text-muted">Bayi iletişim ve şifre ayarlarınızı güncelleyin.</small>
            </div>
            <div class="card-body">
                <form method="post" class="mb-4">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <input type="hidden" name="action" value="profile">
                    <div class="mb-3">
                        <label class="form-label">Ad Soyad</label>
                        <input type="text" name="name" class="form-control" value="<?= Helpers::sanitize($user['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-posta</label>
                        <input type="email" class="form-control" value="<?= Helpers::sanitize($user['email']) ?>" readonly>
                        <small class="text-muted">Kayıtlı e-posta adresiniz güvenlik nedeniyle değiştirilemez.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telegram Bot Tokenı</label>
                        <input type="text" name="telegram_bot_token" class="form-control" value="<?= Helpers::sanitize(isset($user['telegram_bot_token']) ? $user['telegram_bot_token'] : '') ?>" required>
                        <small class="text-muted">BotFather üzerinden oluşturduğunuz botun tokenını girin.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telegram Chat ID</label>
                        <input type="text" name="telegram_chat_id" class="form-control" value="<?= Helpers::sanitize(isset($user['telegram_chat_id']) ? $user['telegram_chat_id'] : '') ?>" required>
                        <small class="text-muted">Bildirimlerin gönderileceği kullanıcı veya kanal kimliği.</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Panel Dili</label>
                            <select name="locale" class="form-select">
                                <?php foreach (App\Lang::availableLocales() as $localeOption): ?>
                                    <option value="<?= Helpers::sanitize($localeOption) ?>" <?= (isset($user['locale']) && $user['locale'] === $localeOption) ? 'selected' : '' ?>><?= strtoupper(Helpers::sanitize($localeOption)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Bayi panelinde kullanılacak varsayılan dil.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tercih Edilen Para Birimi</label>
                            <select name="currency" class="form-select">
                                <?php foreach (array('TRY' => 'Türk Lirası', 'USD' => 'ABD Doları', 'EUR' => 'Euro') as $code => $label): ?>
                                    <option value="<?= Helpers::sanitize($code) ?>" <?= (isset($user['currency']) && strtoupper((string)$user['currency']) === $code) ? 'selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Grafik ve fiyatlar bu para birimine göre gösterilecektir.</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mevcut Şifre</label>
                        <input type="password" name="current_password" class="form-control" placeholder="Şifrenizi değiştirmek için doldurun">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Yeni Şifre</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Yeni şifreniz">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Yeni Şifre (Tekrar)</label>
                            <input type="password" name="new_password_confirmation" class="form-control" placeholder="Yeni şifre tekrarı">
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Profili Kaydet</button>
                    </div>
                </form>

                <dl class="row mb-0">
                    <dt class="col-sm-4">Üyelik Başlangıcı</dt>
                    <dd class="col-sm-8"><?= isset($user['created_at']) ? date('d.m.Y H:i', strtotime($user['created_at'])) : '-' ?></dd>
                    <dt class="col-sm-4">Durum</dt>
                    <dd class="col-sm-8"><span class="badge bg-success">Aktif</span></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Telegram Bildirimleri</h5>
                <small class="text-muted">Telegram üzerinden hangi bildirimleri almak istediğinizi seçin.</small>
            </div>
            <div class="card-body">
                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <input type="hidden" name="action" value="notifications">

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="notifyOrderCompleted" name="notify_order_completed" <?= (!isset($user['notify_order_completed']) || $user['notify_order_completed'] !== '0') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notifyOrderCompleted">Ürün siparişlerim tamamlandığında bilgilendir</label>
                        <small class="text-muted d-block">Tamamlanan siparişler için teslimat detayları Telegram üzerinden iletilsin.</small>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="notifyBalanceApproved" name="notify_balance_approved" <?= (!isset($user['notify_balance_approved']) || $user['notify_balance_approved'] !== '0') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notifyBalanceApproved">Bakiye yüklemeleri onaylandığında bilgilendir</label>
                        <small class="text-muted d-block">Onaylanan bakiye taleplerinde tutar ve yöntem özeti Telegram üzerinden gelsin.</small>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="notifySupportReplied" name="notify_support_replied" <?= (!isset($user['notify_support_replied']) || $user['notify_support_replied'] !== '0') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notifySupportReplied">Destek yanıtlarında bilgilendir</label>
                        <small class="text-muted d-block">Destek ekibi talebinize yanıt verdiğinde Telegram bildirimi gönderilsin.</small>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-outline-primary">Tercihlerimi Kaydet</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';
