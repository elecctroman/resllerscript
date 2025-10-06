<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Settings;
use App\Services\CurrencyService;

$acceptHeader = isset($_SERVER['HTTP_ACCEPT']) ? strtolower((string) $_SERVER['HTTP_ACCEPT']) : '';
$contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower((string) $_SERVER['CONTENT_TYPE']) : '';
$requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) : '';
$isJsonRequest = ($acceptHeader && strpos($acceptHeader, 'application/json') !== false)
    || ($contentType && strpos($contentType, 'application/json') !== false)
    || $requestedWith === 'xmlhttprequest';

$fallbackCurrencies = array(
    'USD' => array('code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate' => 1.0, 'decimals' => 2, 'is_default' => true, 'is_active' => true),
    'EUR' => array('code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate' => 0.95, 'decimals' => 2, 'is_default' => false, 'is_active' => true),
    'TRY' => array('code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺', 'rate' => 27.0, 'decimals' => 2, 'is_default' => false, 'is_active' => true),
);

if ($isJsonRequest && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        echo json_encode(array('success' => false, 'message' => 'Geçersiz istek.'));
        exit;
    }

    $code = isset($payload['currency']) ? strtoupper((string) $payload['currency']) : '';
    $token = isset($payload['csrf_token']) ? (string) $payload['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        echo json_encode(array('success' => false, 'message' => 'Oturum doğrulaması başarısız.'));
        exit;
    }

    $currencies = CurrencyService::isReady() ? CurrencyService::currenciesByCode() : $fallbackCurrencies;
    $defaultCurrency = CurrencyService::isReady() ? CurrencyService::defaultCurrency() : 'USD';

    if ($code === '' || !isset($currencies[$code]) || (isset($currencies[$code]['is_active']) && (int) $currencies[$code]['is_active'] !== 1)) {
        echo json_encode(array('success' => false, 'message' => 'Geçersiz para birimi.'));
        exit;
    }

    $_SESSION['app_currency'] = $code;

    if (!empty($_SESSION['user']) && isset($_SESSION['user']['id'])) {
        $userId = (int) $_SESSION['user']['id'];
        Settings::set('user_' . $userId . '_preferred_currency', $code);
        $_SESSION['user']['currency'] = $code;
    }

    echo json_encode(array(
        'success' => true,
        'currency' => $code,
        'default_currency' => $defaultCurrency,
        'currencies' => $currencies,
        'csrf_token' => Helpers::csrfToken(),
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = isset($_POST['currency']) ? strtoupper((string) $_POST['currency']) : '';
    $csrfToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($csrfToken)) {
        $code = isset($_SESSION['app_currency']) ? strtoupper((string) $_SESSION['app_currency']) : '';
    }
} else {
    $code = isset($_GET['currency']) ? strtoupper((string) $_GET['currency']) : '';
}

$currencies = CurrencyService::isReady() ? CurrencyService::currenciesByCode() : $fallbackCurrencies;
$defaultCurrency = CurrencyService::isReady() ? CurrencyService::defaultCurrency() : 'USD';

if ($code !== '' && isset($currencies[$code]) && (!isset($currencies[$code]['is_active']) || (int) $currencies[$code]['is_active'] === 1)) {
    $_SESSION['app_currency'] = $code;

    if (!empty($_SESSION['user']) && isset($_SESSION['user']['id'])) {
        $userId = (int) $_SESSION['user']['id'];
        Settings::set('user_' . $userId . '_preferred_currency', $code);
        $_SESSION['user']['currency'] = $code;
        $freshUser = Auth::findUser($userId);
        if ($freshUser) {
            $_SESSION['user'] = $freshUser;
            $_SESSION['user']['currency'] = $code;
        }
    }
}

$redirect = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : null;
} else {
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : null;
}

if (!$redirect) {
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
    if ($referer) {
        $redirect = $referer;
    }
}

if (!$redirect) {
    $redirect = '/';
}

Helpers::redirect($redirect);

