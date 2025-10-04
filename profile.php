<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\ApiToken;
use App\Database;
use App\Helpers;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

$pdo = Database::connection();
$errors = array();
$successMessages = array();
$displayToken = '';

try {
    $activeToken = ApiToken::getOrCreateForUser($user['id']);
} catch (\Throwable $exception) {
    $activeToken = null;
    $errors[] = 'API anahtarınıza erişilirken bir sorun oluştu: ' . $exception->getMessage();
}

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
        } elseif ($action === 'webhook' && $activeToken) {
            $webhookUrl = isset($_POST['webhook_url']) ? trim($_POST['webhook_url']) : '';

            if ($webhookUrl !== '' && !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'Geçerli bir webhook adresi giriniz.';
            }

            if (!$errors) {
                ApiToken::updateWebhook((int)$activeToken['id'], $webhookUrl !== '' ? $webhookUrl : null);
                $successMessages[] = 'Webhook adresiniz güncellendi.';
                if ($activeToken) {
                    $activeToken['webhook_url'] = $webhookUrl !== '' ? $webhookUrl : null;
                }
            }
        } elseif ($action === 'regenerate_token') {
            try {
                $newToken = ApiToken::regenerateForUser($user['id']);
                $displayToken = $newToken['token'];
                $successMessages[] = 'Yeni API anahtarınız oluşturuldu. Lütfen güvenli bir yerde saklayın.';
                $activeToken = array(
                    'id' => $newToken['id'],
                    'user_id' => $user['id'],
                    'token' => $newToken['token'],
                    'label' => 'Panel API Anahtarı',
                    'webhook_url' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'last_used_at' => null,
                );
            } catch (\Throwable $exception) {
                $errors[] = 'API anahtarınız yenilenirken bir sorun oluştu: ' . $exception->getMessage();
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
<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Profil Bilgileri</h5>
                <small class="text-muted">Bayi iletişim ve şifre ayarlarınızı güncelleyin.</small>
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

                <?php if ($successMessages): ?>
                    <div class="alert alert-success">
                        <ul class="mb-0">
                            <?php foreach ($successMessages as $message): ?>
                                <li><?= Helpers::sanitize($message) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

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
        <div class="card border-0 shadow-sm mt-4">
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
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">API Erişimi</h5>
                    <small class="text-muted">Tüm platformlardan sipariş, bakiye ve bildirim entegrasyonları için REST API bilgileri.</small>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <input type="hidden" name="action" value="regenerate_token">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Yeni bir API anahtarı oluşturmak istediğinize emin misiniz? Mevcut anahtar kullanım dışı kalacaktır.');">Anahtarı Yenile</button>
                </form>
            </div>
            <div class="card-body">
                <?php if ($displayToken !== ''): ?>
                    <div class="alert alert-warning">
                        <strong>Yeni API Anahtarı:</strong>
                        <div class="mt-2"><code><?= Helpers::sanitize($displayToken) ?></code></div>
                        <p class="mb-0 small text-muted">Bu anahtar yalnızca bir kez gösterilir. Lütfen güvenli bir yerde saklayın.</p>
                    </div>
                <?php endif; ?>

                <?php if ($activeToken): ?>
                    <div class="mb-4">
                        <label class="form-label">API Temel URL</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= Helpers::sanitize(Helpers::apiBaseUrl()) ?>" readonly>
                            <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= Helpers::sanitize(Helpers::apiBaseUrl()) ?>'); this.textContent='Kopyalandı'; setTimeout(()=>{this.textContent='Kopyala';},2000);">Kopyala</button>
                        </div>
                        <small class="text-muted d-block mt-2">Tüm uç noktalar bu adres ile başlar (örneğin <code>/orders</code>, <code>/products</code>).</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Aktif API Anahtarı</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= Helpers::sanitize($activeToken['token']) ?>" readonly>
                            <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= Helpers::sanitize($activeToken['token']) ?>'); this.textContent='Kopyalandı'; setTimeout(()=>{this.textContent='Kopyala';},2000);">Kopyala</button>
                        </div>
                        <?php if (!empty($activeToken['last_used_at'])): ?>
                            <small class="text-muted d-block mt-2">Son kullanım: <?= date('d.m.Y H:i', strtotime($activeToken['last_used_at'])) ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Webhook Adresi</label>
                        <form method="post" class="d-flex gap-2 flex-column flex-lg-row">
                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                            <input type="hidden" name="action" value="webhook">
                            <input type="url" name="webhook_url" class="form-control" placeholder="https://ornek.com/webhooks/reseller-status" value="<?= Helpers::sanitize(isset($activeToken['webhook_url']) ? $activeToken['webhook_url'] : '') ?>">
                            <button type="submit" class="btn btn-outline-primary">Kaydet</button>
                        </form>
                        <small class="text-muted">Sipariş durumu, bakiye ve stok bildirimleri bu adrese JSON formatında iletilir.</small>
                    </div>

                    <div class="border rounded p-3 bg-light">
                        <h6>Entegrasyon İpuçları</h6>
                        <ul class="small mb-3">
                            <li>Yetkilendirme: <code>Authorization: Bearer <?= Helpers::sanitize($activeToken['token']) ?></code> veya <code>X-API-Key</code> başlığını kullanın.</li>
                            <li>API Dökümanı: <a href="/api/v1/" target="_blank" rel="noopener">JSON uç noktalarını görüntüleyin</a> veya Postman koleksiyonunu indirin.</li>
                            <li>Sandbox: Gerçek bakiye harcamadan test etmek için <strong>"test"</strong> parametresini kullanabilirsiniz.</li>
                        </ul>
                        <pre class="bg-dark text-white p-3 rounded small mb-0"><code>curl -X POST "<?= Helpers::sanitize(Helpers::apiBaseUrl()) ?>/orders"
  -H "Authorization: Bearer <?= Helpers::sanitize($activeToken['token']) ?>"
  -H "Content-Type: application/json"
  -d '{"order_id":"EX-1001","items":[{"sku":"SKU-001","quantity":1}]}'</code></pre>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">API anahtarınız oluşturulamadı. Lütfen daha sonra tekrar deneyin veya destek ekibine ulaşın.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';
