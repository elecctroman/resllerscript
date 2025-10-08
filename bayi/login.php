<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Lang;
use App\Settings;
use App\Payments\PaymentGatewayManager;

Lang::boot();

if (Auth::currentReseller()) {
    Helpers::redirect('/dashboard.php');
}

$pdo = Database::connection();

$loginErrors = array();
$loginFlashSuccess = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : null;
$loginFlashWarning = isset($_SESSION['flash_warning']) ? $_SESSION['flash_warning'] : null;
unset($_SESSION['flash_success'], $_SESSION['flash_warning']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_intent']) && $_POST['form_intent'] === 'login') {
    if (!Helpers::verifyCsrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $loginErrors[] = 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $identifier = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

        if ($identifier === '' || $password === '') {
            $loginErrors[] = 'Lütfen kullanıcı adı/e-posta ve şifre alanlarını doldurun.';
        } else {
            $user = Auth::attempt($identifier, $password);
            if ($user && $user['role'] === 'reseller') {
                Auth::loginReseller($user);

                $preferredLanguage = Settings::get('user_' . $user['id'] . '_preferred_language');
                if ($preferredLanguage) {
                    Lang::setLocale($preferredLanguage);
                } else {
                    Lang::boot();
                }

                Helpers::redirect('/dashboard.php');
            } else {
                $loginErrors[] = 'Bilgileriniz doğrulanamadı veya bu hesap bayi erişimine sahip değil.';
            }
        }
    }
}

// Application form context
$packages = $pdo->query('SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC')->fetchAll();
$packageOptions = array();
foreach ($packages as $package) {
    $packageOptions[] = array(
        'id' => (int) $package['id'],
        'name' => $package['name'],
        'price' => (float) $package['price'],
        'initial_balance' => isset($package['initial_balance']) ? (float) $package['initial_balance'] : 0.0,
    );
}

$applicationErrors = isset($_SESSION['application_errors']) && is_array($_SESSION['application_errors'])
    ? $_SESSION['application_errors']
    : array();
$applicationSuccess = isset($_SESSION['application_success']) ? $_SESSION['application_success'] : '';
$applicationWarnings = isset($_SESSION['application_warnings']) && is_array($_SESSION['application_warnings'])
    ? $_SESSION['application_warnings']
    : array();
$applicationOld = isset($_SESSION['application_old']) && is_array($_SESSION['application_old'])
    ? $_SESSION['application_old']
    : array();
$bankTransferNotice = isset($_SESSION['register_bank_transfer_notice']) && is_array($_SESSION['register_bank_transfer_notice'])
    ? $_SESSION['register_bank_transfer_notice']
    : array();

unset(
    $_SESSION['application_errors'],
    $_SESSION['application_success'],
    $_SESSION['application_warnings'],
    $_SESSION['application_old'],
    $_SESSION['register_bank_transfer_notice']
);

$phoneCountryOptions = array(
    '+90' => 'Türkiye (+90)',
    '+1' => 'ABD / Kanada (+1)',
    '+44' => 'Birleşik Krallık (+44)',
    '+49' => 'Almanya (+49)',
    '+33' => 'Fransa (+33)',
    '+39' => 'İtalya (+39)',
    '+971' => 'Birleşik Arap Emirlikleri (+971)',
    '+966' => 'Suudi Arabistan (+966)',
);
$defaultPhoneCountryCode = '+90';
$selectedPhoneCountry = isset($applicationOld['phone_country_code']) && isset($phoneCountryOptions[$applicationOld['phone_country_code']])
    ? $applicationOld['phone_country_code']
    : $defaultPhoneCountryCode;

$gateways = PaymentGatewayManager::getActiveGateways();
$bankTransferDetails = PaymentGatewayManager::getBankTransferDetails();
$bankTransferSummary = array();
if (isset($gateways['bank-transfer'])) {
    if (!empty($bankTransferDetails['bank_name'])) {
        $bankTransferSummary[] = 'Banka: ' . $bankTransferDetails['bank_name'];
    }
    if (!empty($bankTransferDetails['account_name'])) {
        $bankTransferSummary[] = 'Hesap Sahibi: ' . $bankTransferDetails['account_name'];
    }
    if (!empty($bankTransferDetails['iban'])) {
        $bankTransferSummary[] = 'IBAN: ' . $bankTransferDetails['iban'];
    }
    if (!empty($bankTransferDetails['instructions'])) {
        $lines = preg_split("/\r\n|\r|\n/", $bankTransferDetails['instructions']);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $bankTransferSummary[] = $trimmed;
            }
        }
    }
}

$selectedPackageId = isset($applicationOld['package_id']) ? (int)$applicationOld['package_id'] : (count($packageOptions) ? (int)$packageOptions[0]['id'] : 0);
$selectedGateway = isset($applicationOld['payment_provider'])
    ? $applicationOld['payment_provider']
    : (count($gateways) ? array_key_first($gateways) : '');

$packagesEnabled = Helpers::featureEnabled('packages');
$googleClientId = Settings::get('google_oauth_client_id');
$googleClientSecret = Settings::get('google_oauth_client_secret');
$googleLoginEnabled = $googleClientId && $googleClientSecret;

$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'application' ? 'application' : 'login';
if ($applicationErrors || $applicationSuccess) {
    $activeTab = 'application';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bayi Girişi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(120deg, #0f172a 0%, #1e3a8a 50%, #0f172a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .auth-shell {
            width: 100%;
            max-width: 1100px;
            background: rgba(15, 23, 42, 0.85);
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.6);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        .tab-nav .nav-link {
            color: rgba(226, 232, 240, 0.75);
            font-weight: 600;
            border: none;
            padding: 1.1rem 1.5rem;
        }
        .tab-nav .nav-link.active {
            color: #fff;
            background: rgba(37, 99, 235, 0.35);
        }
        .form-label {
            font-weight: 600;
            color: #0f172a;
        }
        .card-contrast {
            background: #fff;
            border-radius: 1.25rem;
            border: none;
            box-shadow: 0 20px 35px -20px rgba(15, 23, 42, 0.4);
        }
        .card-contrast .card-header {
            border-bottom: none;
            background: transparent;
            padding-bottom: 0;
        }
        .alert ul {
            margin: 0;
            padding-left: 1rem;
        }
        .application-sidebar {
            background: rgba(15, 23, 42, 0.85);
            color: #e2e8f0;
            border-radius: 1.25rem;
            padding: 1.5rem;
        }
        .application-sidebar h5 {
            color: #93c5fd;
            font-weight: 600;
        }
        .application-sidebar li {
            margin-bottom: 0.4rem;
        }
        .nav-link, .btn-link {
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="auth-shell">
    <div class="row g-0">
        <div class="col-12">
            <ul class="nav nav-pills tab-nav justify-content-center bg-transparent pt-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'login' ? 'active' : '' ?>" id="login-tab" data-bs-toggle="pill" data-bs-target="#login-pane" type="button" role="tab">Giriş Yap</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'application' ? 'active' : '' ?>" id="apply-tab" data-bs-toggle="pill" data-bs-target="#apply-pane" type="button" role="tab">Bayi Başvurusu</button>
                </li>
            </ul>
        </div>
        <div class="col-12">
            <div class="tab-content p-4 p-lg-5">
                <div class="tab-pane fade <?= $activeTab === 'login' ? 'show active' : '' ?>" id="login-pane" role="tabpanel" aria-labelledby="login-tab">
                    <div class="row g-4 align-items-center">
                        <div class="col-12 col-lg-5">
                            <div class="text-white">
                                <h2 class="fw-bold mb-3">Bayi Yönetim Sistemi</h2>
                                <p class="text-light opacity-75 mb-4">Siparişlerinizi yönetin, stok durumunu takip edin ve destek ekibimizle tek panelden iletişime geçin.</p>
                                <ul class="list-unstyled opacity-75 small">
                                    <li class="mb-2">• Anlık stok ve otomatik teslimat</li>
                                    <li class="mb-2">• Detaylı raporlama ve finans ekranları</li>
                                    <li>• 7/24 destek ve Telegram bildirimleri</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-12 col-lg-7">
                            <div class="card card-contrast">
                                <div class="card-body p-4 p-lg-5">
                                    <h3 class="fw-semibold mb-3">Bayi Girişi</h3>
                                    <p class="text-muted mb-4">Yalnızca bayi hesapları bu panelden giriş yapabilir. Yönetici misiniz? <a href="/admin/login.php">Admin girişine gidin</a>.</p>

                                    <?php if ($loginFlashSuccess): ?>
                                        <div class="alert alert-success"><?= Helpers::sanitize($loginFlashSuccess) ?></div>
                                    <?php endif; ?>
                                    <?php if ($loginFlashWarning): ?>
                                        <div class="alert alert-warning"><?= Helpers::sanitize($loginFlashWarning) ?></div>
                                    <?php endif; ?>
                                    <?php if ($loginErrors): ?>
                                        <div class="alert alert-danger">
                                            <ul>
                                                <?php foreach ($loginErrors as $error): ?>
                                                    <li><?= Helpers::sanitize($error) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <form method="post" autocomplete="off" class="needs-validation" novalidate>
                                        <input type="hidden" name="form_intent" value="login">
                                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                        <div class="mb-3">
                                            <label for="loginEmail" class="form-label">Kullanıcı adı veya e-posta</label>
                                            <input type="text" id="loginEmail" name="email" value="<?= isset($_POST['email']) ? Helpers::sanitize($_POST['email']) : '' ?>" class="form-control form-control-lg" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="loginPassword" class="form-label">Şifre</label>
                                            <input type="password" id="loginPassword" name="password" class="form-control form-control-lg" required>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <a class="small text-decoration-none" href="/password-reset.php">Şifremi unuttum</a>
                                            <a class="small text-decoration-none" href="/admin/login.php">Admin girişi</a>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-lg w-100">Giriş Yap</button>
                                    </form>

                                    <?php if ($googleLoginEnabled): ?>
                                        <a class="btn btn-outline-light btn-lg w-100 mt-3" href="/oauth/google.php">
                                            Google ile Giriş Yap
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade <?= $activeTab === 'application' ? 'show active' : '' ?>" id="apply-pane" role="tabpanel" aria-labelledby="apply-tab">
                    <?php if (!$packagesEnabled): ?>
                        <div class="alert alert-warning">Yeni bayilik başvuruları şu anda kapalı. Lütfen daha sonra tekrar deneyin.</div>
                    <?php else: ?>
                        <div class="row g-4">
                            <div class="col-12 col-lg-7">
                                <div class="card card-contrast">
                                    <div class="card-body p-4 p-lg-5">
                                        <h3 class="fw-semibold mb-3">Yeni Bayi Başvurusu</h3>
                                        <p class="text-muted mb-4">Aşağıdaki formu doldurarak bayilik başvurunuzu iletebilirsiniz. Bilgileriniz tarafımıza ulaştığında en kısa sürede değerlendirme yapılacaktır.</p>

                                        <?php if ($applicationSuccess): ?>
                                            <div class="alert alert-success"><?= Helpers::sanitize($applicationSuccess) ?></div>
                                        <?php endif; ?>
                                        <?php if ($applicationWarnings): ?>
                                            <div class="alert alert-warning">
                                                <ul>
                                                    <?php foreach ($applicationWarnings as $warning): ?>
                                                        <li><?= Helpers::sanitize($warning) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($applicationErrors): ?>
                                            <div class="alert alert-danger">
                                                <ul>
                                                    <?php foreach ($applicationErrors as $error): ?>
                                                        <li><?= Helpers::sanitize($error) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                        <form method="post" action="/register.php" enctype="multipart/form-data">
                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                            <div class="row g-3">
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="package_id">Paket Seçimi</label>
                                                    <select class="form-select form-select-lg" id="package_id" name="package_id" required>
                                                        <?php foreach ($packageOptions as $package): ?>
                                                            <option value="<?= (int)$package['id'] ?>" <?= $selectedPackageId === (int)$package['id'] ? 'selected' : '' ?>>
                                                                <?= Helpers::sanitize($package['name']) ?> - <?= Helpers::formatCurrencyHtml($package['price']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="payment_provider">Ödeme Yöntemi</label>
                                                    <select class="form-select form-select-lg" id="payment_provider" name="payment_provider">
                                                        <?php foreach ($gateways as $identifier => $gateway): ?>
                                                            <option value="<?= Helpers::sanitize($identifier) ?>" <?= $selectedGateway === $identifier ? 'selected' : '' ?>>
                                                                <?= Helpers::sanitize($gateway['label'] ?? strtoupper($identifier)) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="name">Ad Soyad</label>
                                                    <input type="text" id="name" name="name" class="form-control form-control-lg" value="<?= isset($applicationOld['name']) ? Helpers::sanitize($applicationOld['name']) : '' ?>" required>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="email">E-posta</label>
                                                    <input type="email" id="email" name="email" class="form-control form-control-lg" value="<?= isset($applicationOld['email']) ? Helpers::sanitize($applicationOld['email']) : '' ?>" required>
                                                </div>
                                                <div class="col-12 col-md-5">
                                                    <label class="form-label" for="phone_country_code">Ülke Kodu</label>
                                                    <select class="form-select form-select-lg" id="phone_country_code" name="phone_country_code">
                                                        <?php foreach ($phoneCountryOptions as $code => $label): ?>
                                                            <option value="<?= Helpers::sanitize($code) ?>" <?= $selectedPhoneCountry === $code ? 'selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-7">
                                                    <label class="form-label" for="phone_number">Telefon</label>
                                                    <input type="text" id="phone_number" name="phone_number" class="form-control form-control-lg" value="<?= isset($applicationOld['phone_number']) ? Helpers::sanitize($applicationOld['phone_number']) : '' ?>" required>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="company">Şirket / Mağaza Adı</label>
                                                    <input type="text" id="company" name="company" class="form-control form-control-lg" value="<?= isset($applicationOld['company']) ? Helpers::sanitize($applicationOld['company']) : '' ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label" for="notes">Notlar</label>
                                                    <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="İş modelinizi veya beklentilerinizi paylaşabilirsiniz."><?= isset($applicationOld['notes']) ? Helpers::sanitize($applicationOld['notes']) : '' ?></textarea>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="password">Şifre</label>
                                                    <input type="password" id="password" name="password" class="form-control form-control-lg" required>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="password_confirmation">Şifre (Tekrar)</label>
                                                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control form-control-lg" required>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="telegram_bot_token">Telegram Bot Token</label>
                                                    <input type="text" id="telegram_bot_token" name="telegram_bot_token" class="form-control" value="<?= isset($applicationOld['telegram_bot_token']) ? Helpers::sanitize($applicationOld['telegram_bot_token']) : '' ?>">
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="telegram_chat_id">Telegram Chat ID</label>
                                                    <input type="text" id="telegram_chat_id" name="telegram_chat_id" class="form-control" value="<?= isset($applicationOld['telegram_chat_id']) ? Helpers::sanitize($applicationOld['telegram_chat_id']) : '' ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label" for="payment_reference">Ödeme Referansı</label>
                                                    <input type="text" id="payment_reference" name="payment_reference" class="form-control" value="<?= isset($applicationOld['payment_reference']) ? Helpers::sanitize($applicationOld['payment_reference']) : '' ?>" placeholder="Banka dekont numarası veya açıklaması">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label" for="payment_notice">Ödeme Notu</label>
                                                    <textarea id="payment_notice" name="payment_notice" class="form-control" rows="2" placeholder="Ödeme ile ilgili ek açıklamalarınız varsa paylaşın."><?= isset($applicationOld['payment_notice']) ? Helpers::sanitize($applicationOld['payment_notice']) : '' ?></textarea>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-success btn-lg w-100">Başvuruyu Gönder</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-5">
                                <div class="application-sidebar h-100">
                                    <h5 class="mb-3">Başvuru Sonrası</h5>
                                    <ul class="list-unstyled mb-4">
                                        <li>• Bilgileriniz incelendikten sonra ekibimiz sizinle iletişime geçecek.</li>
                                        <li>• Telegram bilgilerinizi doldurmanız durumunda süreç bildirimleri alırsınız.</li>
                                        <li>• Banka transferi yaptıysanız dekont ve açıklama alanını doldurmayı unutmayın.</li>
                                    </ul>
                                    <?php if ($bankTransferSummary): ?>
                                        <h5 class="mb-2">Banka Transfer Bilgileri</h5>
                                        <ul class="list-unstyled small">
                                            <?php foreach ($bankTransferSummary as $line): ?>
                                                <li><?= Helpers::sanitize($line) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <?php if ($bankTransferNotice): ?>
                                        <div class="alert alert-info mt-4">
                                            <ul class="mb-0">
                                                <?php foreach ($bankTransferNotice as $noticeLine): ?>
                                                    <li><?= Helpers::sanitize($noticeLine) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($activeTab === 'application'): ?>
<script>
    const tabEl = document.querySelector('#apply-tab');
    if (tabEl) {
        const tab = new bootstrap.Tab(tabEl);
        tab.show();
    }
</script>
<?php endif; ?>
</body>
</html>
