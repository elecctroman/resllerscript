<?php
require __DIR__ . '/../bootstrap.php';

use App\Customers\CustomerAuth;
use App\Customers\CustomerRepository;
use App\Helpers;

$customer = CustomerAuth::ensureCustomer();
$errors = array();
$success = null;
$errorAction = null;
$successAction = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'profile';
    if (!Helpers::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Güvenlik doğrulaması başarısız oldu.';
        $errorAction = $action;
    } else {
        if ($action === 'profile') {
            $name = trim((string)($_POST['name'] ?? ''));
            $surname = trim((string)($_POST['surname'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $locale = $_POST['locale'] ?? ($customer['locale'] ?? 'tr');
            $currency = $_POST['currency'] ?? ($customer['currency'] ?? 'TRY');

            if ($name === '' || $surname === '') {
                $errors[] = 'Ad ve soyad alanları zorunludur.';
                $errorAction = 'profile';
            } else {
                CustomerRepository::updateProfile((int)$customer['id'], array(
                    'name' => $name,
                    'surname' => $surname,
                    'phone' => $phone,
                    'locale' => $locale,
                    'currency' => $currency,
                ));
                $_SESSION['customer'] = CustomerRepository::findById((int)$customer['id']);
                $customer = $_SESSION['customer'];
                $success = 'Profil bilgileriniz güncellendi.';
                $successAction = 'profile';
            }
        } elseif ($action === 'password') {
            $password = (string)($_POST['password'] ?? '');
            $confirm = (string)($_POST['password_confirm'] ?? '');
            if (strlen($password) < 6) {
                $errors[] = 'Şifre en az 6 karakter olmalıdır.';
                $errorAction = 'password';
            }
            if ($password !== $confirm) {
                $errors[] = 'Şifreler eşleşmiyor.';
                $errorAction = 'password';
            }
            if (!$errors) {
                CustomerRepository::updatePassword((int)$customer['id'], $password);
                $success = 'Şifreniz başarıyla güncellendi.';
                $successAction = 'password';
            }
        } elseif ($action === 'token') {
            $token = CustomerRepository::regenerateToken((int)$customer['id']);
            $_SESSION['customer']['api_token'] = $token;
            $customer['api_token'] = $token;
            $customer['api_token_created_at'] = date('Y-m-d H:i:s');
            $success = 'API anahtarınız yenilendi.';
            $successAction = 'token';
        } elseif ($action === 'api_settings') {
            $status = ($_POST['api_status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';
            $selectedScopes = isset($_POST['api_scopes']) ? (array)$_POST['api_scopes'] : array();
            $normalizedScopes = array();
            foreach ($selectedScopes as $scope) {
                $scope = strtolower(trim((string)$scope));
                if ($scope === 'full') {
                    $normalizedScopes = array('full');
                    break;
                }
                if (in_array($scope, array('read', 'orders', 'wallet'), true)) {
                    $normalizedScopes[] = $scope;
                }
            }
            $normalizedScopes = array_unique($normalizedScopes);
            $scopeValue = 'full';
            if ($normalizedScopes && !in_array('full', $normalizedScopes, true)) {
                $scopeValue = implode(',', $normalizedScopes);
            }
            if (!$normalizedScopes) {
                $scopeValue = 'read';
            }

            $whitelist = trim((string)($_POST['api_ip_whitelist'] ?? ''));

            CustomerRepository::updateApiSettings((int)$customer['id'], array(
                'status' => $status,
                'scopes' => $scopeValue,
                'ip_whitelist' => $whitelist !== '' ? $whitelist : null,
            ));
            $_SESSION['customer'] = CustomerRepository::findById((int)$customer['id']);
            $customer = $_SESSION['customer'];
            $success = 'API ayarlarınız güncellendi.';
            $successAction = 'api_settings';
        } elseif ($action === 'otp') {
            $otpAction = $_POST['otp_action'] ?? 'enable';
            if ($otpAction === 'disable') {
                CustomerRepository::updateOtpSecret((int)$customer['id'], null);
                $_SESSION['customer']['api_otp_secret'] = null;
                $customer['api_otp_secret'] = null;
                $success = 'OTP koruması devre dışı bırakıldı.';
                $successAction = 'otp';
            } else {
                $secret = '';
                $bytes = random_bytes(10);
                $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
                $bits = '';
                foreach (str_split($bytes) as $char) {
                    $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
                }
                foreach (str_split($bits, 5) as $chunk) {
                    if (strlen($chunk) < 5) {
                        $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
                    }
                    $secret .= $alphabet[bindec($chunk)];
                }
                $secret = substr($secret, 0, 16);
                CustomerRepository::updateOtpSecret((int)$customer['id'], $secret);
                $_SESSION['customer']['api_otp_secret'] = $secret;
                $customer['api_otp_secret'] = $secret;
                $success = 'OTP anahtarınız oluşturuldu. Lütfen doğrulama uygulamanıza ekleyin.';
                $successAction = 'otp';
            }
        }
    }
}

$pageTitle = 'Profil';
$currentScopesRaw = strtolower((string)($customer['api_scopes'] ?? 'full'));
$currentScopes = $currentScopesRaw === 'full' ? array('full') : array_filter(array_map('trim', explode(',', $currentScopesRaw)));
require __DIR__ . '/../templates/customer-header.php';
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card customer-card mb-4">
            <div class="card-header"><h5 class="card-title mb-0">Profil Bilgileri</h5></div>
            <div class="card-body">
                <?php if ($success && $successAction === 'profile'): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
                <?php endif; ?>
                <?php if ($errors && $errorAction === 'profile'): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                    <input type="hidden" name="action" value="profile">
                    <div class="col-md-6">
                        <label class="form-label">Ad</label>
                        <input type="text" class="form-control" name="name" value="<?= Helpers::sanitize($customer['name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Soyad</label>
                        <input type="text" class="form-control" name="surname" value="<?= Helpers::sanitize($customer['surname']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telefon</label>
                        <input type="text" class="form-control" name="phone" value="<?= Helpers::sanitize($customer['phone']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Dil</label>
                        <select class="form-select" name="locale">
                            <option value="tr"<?= (($customer['locale'] ?? 'tr') === 'tr') ? ' selected' : '' ?>>Türkçe</option>
                            <option value="en"<?= (($customer['locale'] ?? 'tr') === 'en') ? ' selected' : '' ?>>English</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Para Birimi</label>
                        <select class="form-select" name="currency">
                            <option value="TRY"<?= (($customer['currency'] ?? 'TRY') === 'TRY') ? ' selected' : '' ?>>TRY</option>
                            <option value="USD"<?= (($customer['currency'] ?? 'TRY') === 'USD') ? ' selected' : '' ?>>USD</option>
                            <option value="EUR"<?= (($customer['currency'] ?? 'TRY') === 'EUR') ? ' selected' : '' ?>>EUR</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Bilgileri Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card customer-card">
            <div class="card-header"><h5 class="card-title mb-0">Şifre Güncelle</h5></div>
            <div class="card-body">
                <?php if ($success && $successAction === 'password'): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
                <?php endif; ?>
                <?php if ($errors && $errorAction === 'password'): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                    <input type="hidden" name="action" value="password">
                    <div class="col-md-6">
                        <label class="form-label">Yeni Şifre</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Şifre Tekrar</label>
                        <input type="password" class="form-control" name="password_confirm" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-primary">Şifreyi Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card customer-card">
            <div class="card-header"><h5 class="card-title mb-0">API Bilgileri</h5></div>
            <div class="card-body">
                <?php if ($success && in_array($successAction, array('token', 'api_settings', 'otp'), true)): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label">API URL</label>
                    <input type="text" class="form-control" readonly value="<?= Helpers::sanitize(Helpers::apiBaseUrl()) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">API Anahtarı</label>
                    <div class="input-group">
                        <input type="text" class="form-control" readonly value="<?= Helpers::sanitize($customer['api_token'] ?? 'Henüz oluşturulmadı') ?>">
                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('<?= Helpers::sanitize($customer['api_token'] ?? '') ?>')"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <small class="text-muted">API anahtarınızı güvenle saklayın. Yeni anahtar oluşturmak mevcut anahtarı geçersiz kılar.</small>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                    <input type="hidden" name="action" value="token">
                    <button type="submit" class="btn btn-warning">Yeni API Anahtarı Oluştur</button>
                </form>
                <hr class="my-4">
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                    <input type="hidden" name="action" value="api_settings">
                    <div class="col-12">
                        <label class="form-label">API Durumu</label>
                        <select class="form-select" name="api_status">
                            <option value="active"<?= (($customer['api_status'] ?? 'active') === 'active') ? ' selected' : '' ?>>Aktif</option>
                            <option value="disabled"<?= (($customer['api_status'] ?? 'active') === 'disabled') ? ' selected' : '' ?>>Devre Dışı</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Yetkiler</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="scope_full" name="api_scopes[]" value="full"<?= in_array('full', $currentScopes, true) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="scope_full">Tam Erişim</label>
                        </div>
                        <div class="row mt-2 g-2">
                            <?php $scopeOptions = array('read' => 'Okuma', 'orders' => 'Sipariş', 'wallet' => 'Cüzdan'); ?>
                            <?php foreach ($scopeOptions as $value => $label): ?>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="scope_<?= $value ?>" name="api_scopes[]" value="<?= $value ?>"<?= in_array($value, $currentScopes, true) ? ' checked' : '' ?>>
                                        <label class="form-check-label" for="scope_<?= $value ?>"><?= Helpers::sanitize($label) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Tam erişim seçiliyken diğer yetkiler otomatik olarak tanımlanır.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">IP Beyaz Liste</label>
                        <textarea class="form-control" name="api_ip_whitelist" rows="3" placeholder="1.2.3.4\n10.0.0.0/24"><?= Helpers::sanitize($customer['api_ip_whitelist'] ?? '') ?></textarea>
                        <div class="form-text">Virgül veya satır sonu ile IP adreslerini ekleyin.</div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-primary w-100">API Ayarlarını Kaydet</button>
                    </div>
                </form>
                <hr class="my-4">
                <div>
                    <h6 class="fw-semibold">OTP / 2FA</h6>
                    <?php if (!empty($customer['api_otp_secret'])): ?>
                        <p class="small text-muted mb-2">OTP etkin. Doğrulama uygulamanıza tanımladığınız kodu kullanarak API çağrılarında <code>X-API-OTP</code> başlığını ekleyin.</p>
                        <div class="alert alert-secondary small">Gizli Anahtar: <strong><?= Helpers::sanitize($customer['api_otp_secret']) ?></strong></div>
                        <form method="post" class="d-grid gap-2">
                            <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                            <input type="hidden" name="action" value="otp">
                            <input type="hidden" name="otp_action" value="disable">
                            <button type="submit" class="btn btn-outline-danger">OTP'yi Devre Dışı Bırak</button>
                        </form>
                    <?php else: ?>
                        <p class="small text-muted mb-2">Ek güvenlik için OTP'yi etkinleştirin. Oluşan gizli anahtarı doğrulama uygulamanıza ekleyin.</p>
                        <form method="post" class="d-grid gap-2">
                            <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                            <input type="hidden" name="action" value="otp">
                            <input type="hidden" name="otp_action" value="enable">
                            <button type="submit" class="btn btn-outline-success">OTP'yi Etkinleştir</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../templates/customer-footer.php';
