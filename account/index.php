<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Lang;
use App\Models\UserProfile;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if (Auth::isAdminRole($user['role'] ?? 'reseller')) {
    Helpers::redirect('/admin/dashboard.php');
}

Lang::boot();

$pdo = Database::connection();

$profileData = UserProfile::get($user['id']);

$defaultNameParts = UserProfile::splitName($user['name'] ?? '');

$profileData = array_merge(
    array(
        'first_name' => $defaultNameParts['first_name'],
        'last_name' => $defaultNameParts['last_name'],
        'phone' => '',
        'country' => 'Türkiye',
        'city' => '',
        'district' => '',
        'address' => '',
    ),
    $profileData
);

$availableLocales = array();
foreach (Lang::availableLocales() as $locale) {
    $availableLocales[] = strtolower((string)$locale);
}

if (!$availableLocales) {
    $availableLocales = array(strtolower((string)Lang::defaultLocale()));
}

$currentLocale = isset($user['locale']) && $user['locale'] !== '' ? strtolower((string)$user['locale']) : strtolower((string)Lang::defaultLocale());
if (!in_array($currentLocale, $availableLocales, true)) {
    $currentLocale = $availableLocales[0];
}

$profileErrors = array();
$profileSuccess = array();
$notificationErrors = array();
$notificationSuccess = '';
$apiKeyErrors = array();
$apiKeySuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : 'profile';
    $csrfToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($csrfToken)) {
        if ($action === 'notifications') {
            $notificationErrors[] = 'Oturum doğrulama anahtarınız geçersiz. Lütfen sayfayı yenileyin.';
        } elseif ($action === 'api-key') {
            $apiKeyErrors[] = 'Oturum doğrulama anahtarınız geçersiz. Lütfen sayfayı yenileyin.';
        } else {
            $profileErrors[] = 'Oturum doğrulama anahtarınız geçersiz. Lütfen sayfayı yenileyin.';
        }
    } else {
        if ($action === 'profile') {
            $firstName = isset($_POST['first_name']) ? trim((string)$_POST['first_name']) : '';
            $lastName = isset($_POST['last_name']) ? trim((string)$_POST['last_name']) : '';
            $phone = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
            $country = isset($_POST['country']) ? trim((string)$_POST['country']) : '';
            $city = isset($_POST['city']) ? trim((string)$_POST['city']) : '';
            $district = isset($_POST['district']) ? trim((string)$_POST['district']) : '';
            $address = isset($_POST['address']) ? trim((string)$_POST['address']) : '';
            $telegramBotToken = isset($_POST['telegram_bot_token']) ? trim((string)$_POST['telegram_bot_token']) : '';
            $telegramChatId = isset($_POST['telegram_chat_id']) ? trim((string)$_POST['telegram_chat_id']) : '';
            $selectedLocale = isset($_POST['locale']) ? strtolower((string)$_POST['locale']) : $currentLocale;

            if (!in_array($selectedLocale, $availableLocales, true)) {
                $selectedLocale = $currentLocale;
            }

            if ($firstName === '' || $lastName === '') {
                $profileErrors[] = 'Ad ve soyad alanları zorunludur.';
            }

            if ($phone !== '' && strlen($phone) < 5) {
                $profileErrors[] = 'Telefon numarası en az 5 karakter olmalıdır.';
            }

            if (!$profileErrors) {
                try {
                    $pdo->beginTransaction();

                    $fullName = UserProfile::buildFullName($firstName, $lastName);
                    $updateUser = $pdo->prepare('UPDATE users SET name = :name, telegram_bot_token = :bot, telegram_chat_id = :chat, locale = :locale, updated_at = NOW() WHERE id = :id');
                    $updateUser->execute(array(
                        'name' => $fullName,
                        'bot' => $telegramBotToken !== '' ? $telegramBotToken : null,
                        'chat' => $telegramChatId !== '' ? $telegramChatId : null,
                        'locale' => $selectedLocale,
                        'id' => $user['id'],
                    ));

                    UserProfile::save($user['id'], array(
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $phone,
                        'country' => $country,
                        'city' => $city,
                        'district' => $district,
                        'address' => $address,
                    ));

                    $pdo->commit();

                    $freshUser = Auth::findUser($user['id']);
                    if ($freshUser) {
                        $_SESSION['user'] = $freshUser;
                        $user = $freshUser;
                    }

                    $profileData = array_merge($profileData, array(
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $phone,
                        'country' => $country,
                        'city' => $city,
                        'district' => $district,
                        'address' => $address,
                    ));
                    $currentLocale = $selectedLocale;
                    $profileSuccess[] = 'Profil bilgileriniz başarıyla güncellendi.';
                } catch (\PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $profileErrors[] = 'Profiliniz güncellenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
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

                $notificationSuccess = 'Bildirim tercihleriniz güncellendi.';
            } catch (\PDOException $exception) {
                $notificationErrors[] = 'Bildirim tercihlerin kaydedilirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
            }
        } elseif ($action === 'api-key') {
            $hadKey = isset($user['api_key']) && $user['api_key'] !== '';
            $newKey = null;

            try {
                for ($attempt = 0; $attempt < 5; $attempt++) {
                    $candidate = bin2hex(random_bytes(32));
                    $check = $pdo->prepare('SELECT id FROM users WHERE api_key = :api_key LIMIT 1');
                    $check->execute(array('api_key' => $candidate));

                    if (!$check->fetch(\PDO::FETCH_ASSOC)) {
                        $newKey = $candidate;
                        break;
                    }
                }

                if ($newKey === null) {
                    throw new \RuntimeException('API anahtarı oluşturulamadı. Lütfen tekrar deneyin.');
                }

                $pdo->prepare('UPDATE users SET api_key = :api_key, updated_at = NOW() WHERE id = :id')
                    ->execute(array('api_key' => $newKey, 'id' => $user['id']));

                $freshUser = Auth::findUser($user['id']);
                if ($freshUser) {
                    $_SESSION['user'] = $freshUser;
                    $user = $freshUser;
                }

                $apiKeySuccess = $hadKey ? 'API anahtarınız yenilendi.' : 'API anahtarınız oluşturuldu.';
            } catch (\Throwable $exception) {
                $apiKeyErrors[] = 'API anahtarınız oluşturulamadı: ' . $exception->getMessage();
            }
        }
    }
}

$pageTitle = 'Kullanıcı Bilgilerim';
$pageDescription = 'Profil bilgilerinizi güncelleyin, bildirim tercihlerinizi yönetin ve API anahtarınızı kontrol edin.';
$activeMenu = 'profile';

ob_start();
?>
<div class="account-section">
    <div class="account-section__header">
        <h5 class="account-section__title">Kişisel Bilgiler</h5>
        <span class="text-muted small">E-posta adresiniz güvenlik için değiştirilemez.</span>
    </div>
    <?php if ($profileErrors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($profileErrors as $error): ?>
                    <li><?= Helpers::sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($profileSuccess): ?>
        <div class="alert alert-success">
            <ul class="mb-0">
                <?php foreach ($profileSuccess as $message): ?>
                    <li><?= Helpers::sanitize($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="post" class="row g-3">
        <input type="hidden" name="action" value="profile">
        <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
        <div class="col-md-6">
            <label class="form-label">Ad</label>
            <input type="text" name="first_name" class="form-control" value="<?= Helpers::sanitize($profileData['first_name']) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Soyad</label>
            <input type="text" name="last_name" class="form-control" value="<?= Helpers::sanitize($profileData['last_name']) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">E-Posta</label>
            <input type="email" class="form-control" value="<?= Helpers::sanitize($user['email']) ?>" disabled>
        </div>
        <div class="col-md-6">
            <label class="form-label">Telefon</label>
            <input type="text" name="phone" class="form-control" value="<?= Helpers::sanitize($profileData['phone']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Ülke</label>
            <input type="text" name="country" class="form-control" value="<?= Helpers::sanitize($profileData['country']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Şehir</label>
            <input type="text" name="city" class="form-control" value="<?= Helpers::sanitize($profileData['city']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">İlçe</label>
            <input type="text" name="district" class="form-control" value="<?= Helpers::sanitize($profileData['district']) ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Adres</label>
            <textarea name="address" rows="3" class="form-control" placeholder="Teslimat ve fatura adresinizi yazın."><?= Helpers::sanitize($profileData['address']) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Telegram Bot Token</label>
            <input type="text" name="telegram_bot_token" class="form-control" value="<?= Helpers::sanitize($user['telegram_bot_token'] ?? '') ?>" placeholder="BotFather ile oluşturduğunuz token">
        </div>
        <div class="col-md-6">
            <label class="form-label">Telegram Sohbet ID</label>
            <input type="text" name="telegram_chat_id" class="form-control" value="<?= Helpers::sanitize($user['telegram_chat_id'] ?? '') ?>" placeholder="Telegram chat ID">
        </div>
        <div class="col-md-6">
            <label class="form-label">Panel Dili</label>
            <select name="locale" class="form-select">
                <?php foreach ($availableLocales as $localeCode): ?>
                    <option value="<?= Helpers::sanitize($localeCode) ?>" <?= $localeCode === $currentLocale ? 'selected' : '' ?>><?= strtoupper(Helpers::sanitize($localeCode)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-success px-4">Değişiklikleri Kaydet</button>
        </div>
    </form>
</div>

<div class="account-section">
    <div class="account-section__header">
        <h5 class="account-section__title">Bildirim Tercihleri</h5>
        <span class="text-muted small">Telegram botu üzerinden bilgilendirme almak için tercihlerinizi seçin.</span>
    </div>
    <?php if ($notificationErrors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($notificationErrors as $error): ?>
                    <li><?= Helpers::sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($notificationSuccess): ?>
        <div class="alert alert-success mb-3"><?= Helpers::sanitize($notificationSuccess) ?></div>
    <?php endif; ?>
    <form method="post" class="row g-3 align-items-center">
        <input type="hidden" name="action" value="notifications">
        <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
        <div class="col-md-4">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="notifyOrder" name="notify_order_completed" <?= !empty($user['notify_order_completed']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="notifyOrder">Sipariş tamamlandığında</label>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="notifyBalance" name="notify_balance_approved" <?= !empty($user['notify_balance_approved']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="notifyBalance">Bakiye onaylandığında</label>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="notifySupport" name="notify_support_replied" <?= !empty($user['notify_support_replied']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="notifySupport">Destek yanıtı geldiğinde</label>
            </div>
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-outline-primary">Tercihleri Güncelle</button>
        </div>
    </form>
</div>

<div class="account-section">
    <div class="account-section__header">
        <h5 class="account-section__title">API Anahtarı</h5>
        <span class="text-muted small">API entegrasyonlarınız için güvenli anahtarınızı yönetin.</span>
    </div>
    <?php if ($apiKeyErrors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($apiKeyErrors as $error): ?>
                    <li><?= Helpers::sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($apiKeySuccess): ?>
        <div class="alert alert-success mb-3"><?= Helpers::sanitize($apiKeySuccess) ?></div>
    <?php endif; ?>
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div>
            <p class="mb-1 text-muted">Mevcut API anahtarınız:</p>
            <code class="d-block py-2 px-3 bg-light rounded border"><?= Helpers::sanitize($user['api_key'] ?? 'Henüz bir API anahtarınız yok.') ?></code>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="api-key">
            <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
            <button type="submit" class="btn btn-outline-secondary">API Anahtarı Oluştur / Yenile</button>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();

require __DIR__ . '/../themes/store/default/account/layout.php';
