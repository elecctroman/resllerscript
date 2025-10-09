<?php
require_once __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Payments\PaymentGatewayManager;
use App\Settings;

if (Auth::check()) {
    Helpers::redirect('/');
}

$pdo = Database::connection();

$loginErrors = array();
$loginSuccess = isset($_SESSION['account_login_success']) ? (string) $_SESSION['account_login_success'] : '';
$loginWarning = isset($_SESSION['account_login_warning']) ? (string) $_SESSION['account_login_warning'] : '';
unset($_SESSION['account_login_success'], $_SESSION['account_login_warning']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_intent']) && $_POST['form_intent'] === 'login') {
    $csrfToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($csrfToken)) {
        $loginErrors[] = 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $identifier = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

        if ($identifier === '' || $password === '') {
            $loginErrors[] = 'Lütfen e-posta ve şifre alanlarını doldurun.';
        } else {
            $user = Auth::attempt($identifier, $password);
            if ($user) {
                Auth::login($user);
                Helpers::redirect('/');
            } else {
                $loginErrors[] = 'Bilgileriniz doğrulanamadı. Lütfen tekrar deneyin veya şifrenizi sıfırlayın.';
            }
        }
    }
}

$packages = $pdo->query('SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC')->fetchAll(PDO::FETCH_ASSOC);
$packageOptions = array();
foreach ($packages as $package) {
    if (!isset($package['id'], $package['name'])) {
        continue;
    }

    $packageOptions[] = array(
        'id' => (int) $package['id'],
        'name' => (string) $package['name'],
        'price' => isset($package['price']) ? (float) $package['price'] : 0.0,
        'initial_balance' => isset($package['initial_balance']) ? (float) $package['initial_balance'] : 0.0,
    );
}

$applicationErrors = isset($_SESSION['application_errors']) && is_array($_SESSION['application_errors']) ? $_SESSION['application_errors'] : array();
$applicationSuccess = isset($_SESSION['application_success']) ? (string) $_SESSION['application_success'] : '';
$applicationWarnings = isset($_SESSION['application_warnings']) && is_array($_SESSION['application_warnings']) ? $_SESSION['application_warnings'] : array();
$applicationOld = isset($_SESSION['application_old']) && is_array($_SESSION['application_old']) ? $_SESSION['application_old'] : array();
$bankTransferNotice = isset($_SESSION['register_bank_transfer_notice']) && is_array($_SESSION['register_bank_transfer_notice']) ? $_SESSION['register_bank_transfer_notice'] : array();

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
    ? (string) $applicationOld['phone_country_code']
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

$selectedPackageId = isset($applicationOld['package_id']) ? (int) $applicationOld['package_id'] : (count($packageOptions) ? (int) $packageOptions[0]['id'] : 0);
$selectedGateway = isset($applicationOld['payment_provider']) ? (string) $applicationOld['payment_provider'] : (count($gateways) ? (string) array_key_first($gateways) : '');

$packagesEnabled = Helpers::featureEnabled('packages');
$googleClientId = Settings::get('google_oauth_client_id');
$googleClientSecret = Settings::get('google_oauth_client_secret');
$googleLoginEnabled = $googleClientId && $googleClientSecret;

$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'application' ? 'application' : 'login';
if ($applicationErrors || $applicationSuccess) {
    $activeTab = 'application';
}

$headerCategories = array();
try {
    $categoryStmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC LIMIT 9');
    if ($categoryStmt !== false) {
        foreach ($categoryStmt->fetchAll(PDO::FETCH_ASSOC) as $category) {
            if (!isset($category['id'], $category['name'])) {
                continue;
            }

            $headerCategories[] = array(
                'name' => (string) $category['name'],
                'url' => store_url('category/' . (int) $category['id']),
                'icon' => '',
            );
        }
    }
} catch (\PDOException $exception) {
    error_log('[Storefront Login] Kategori başlıkları yüklenemedi: ' . $exception->getMessage());
    $headerCategories = array();
}

store_render('auth/login', array(
    'pageTitle' => 'Hesabınıza Giriş Yapın',
    'activeTab' => $activeTab,
    'loginErrors' => $loginErrors,
    'loginSuccess' => $loginSuccess,
    'loginWarning' => $loginWarning,
    'packageOptions' => $packageOptions,
    'paymentGateways' => $gateways,
    'phoneCountries' => $phoneCountryOptions,
    'selectedPackageId' => $selectedPackageId,
    'selectedGateway' => $selectedGateway,
    'selectedPhoneCountry' => $selectedPhoneCountry,
    'applicationErrors' => $applicationErrors,
    'applicationWarnings' => $applicationWarnings,
    'applicationSuccess' => $applicationSuccess,
    'applicationOld' => $applicationOld,
    'bankTransferSummary' => $bankTransferSummary,
    'bankTransferNotice' => $bankTransferNotice,
    'googleLoginEnabled' => $googleLoginEnabled,
    'packagesEnabled' => $packagesEnabled,
    'loginAction' => '/account/login',
    'applicationAction' => '/register.php',
    'bayiApplyUrl' => '/account/login?tab=application',
    'adminLoginUrl' => '/admin/login.php',
    'forgotUrl' => '/password-reset.php',
    'headerCategories' => $headerCategories,
    'metaDescription' => (string) get_setting('seo_description', ''),
    'loginUrl' => store_url('account/login'),
    'registerUrl' => store_url('account/register'),
));
