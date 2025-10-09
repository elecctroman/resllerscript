<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Lang;
use App\Settings;
use App\Services\LanguageService;

$acceptHeader = isset($_SERVER['HTTP_ACCEPT']) ? strtolower((string) $_SERVER['HTTP_ACCEPT']) : '';
$contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower((string) $_SERVER['CONTENT_TYPE']) : '';
$requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) : '';
$isJsonRequest = ($acceptHeader && strpos($acceptHeader, 'application/json') !== false)
    || ($contentType && strpos($contentType, 'application/json') !== false)
    || $requestedWith === 'xmlhttprequest';

if ($isJsonRequest && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        echo json_encode(array('success' => false, 'message' => 'Geçersiz istek verisi.'));
        exit;
    }

    $locale = isset($payload['locale']) ? (string) $payload['locale'] : '';
    $token = isset($payload['csrf_token']) ? (string) $payload['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        echo json_encode(array('success' => false, 'message' => 'Oturum doğrulaması başarısız.'));
        exit;
    }

    if ($locale === '') {
        $locale = Lang::defaultLocale();
    }

    Lang::setLocale($locale);
    $activeLocale = Lang::locale();

    $currentUser = Auth::currentUser();
    if ($currentUser && isset($currentUser['id'])) {
        $userId = (int) $currentUser['id'];
        Settings::set('user_' . $userId . '_preferred_language', $activeLocale);
        $currentUser['locale'] = $activeLocale;
        Auth::refreshUser($currentUser);
    }

    $defaultLocale = class_exists(LanguageService::class) ? LanguageService::defaultCode() : Lang::defaultLocale();
    $translations = array();
    $fallback = array();
    $available = array();

    if (class_exists(LanguageService::class)) {
        $translations = LanguageService::catalog($activeLocale);
        $fallback = $activeLocale === $defaultLocale ? $translations : LanguageService::catalog($defaultLocale);

        foreach (LanguageService::languages(true) as $language) {
            if (!isset($language['code'])) {
                continue;
            }

            $available[] = array(
                'code' => strtolower((string) $language['code']),
                'name' => isset($language['name']) ? (string) $language['name'] : strtoupper((string) $language['code']),
                'native_name' => isset($language['native_name']) ? (string) $language['native_name'] : strtoupper((string) $language['code']),
                'is_active' => isset($language['is_active']) ? (int) $language['is_active'] === 1 : true,
                'is_default' => isset($language['is_default']) ? (int) $language['is_default'] === 1 : false,
            );
        }
    } else {
        $translations = array();
        $fallback = array();
        foreach (Lang::availableLocales() as $code) {
            $available[] = array(
                'code' => strtolower((string) $code),
                'name' => strtoupper((string) $code),
                'native_name' => strtoupper((string) $code),
                'is_active' => true,
                'is_default' => $code === Lang::defaultLocale(),
            );
        }
    }

    echo json_encode(array(
        'success' => true,
        'locale' => $activeLocale,
        'html_locale' => Lang::htmlLocale(),
        'translations' => $translations,
        'fallback' => $fallback,
        'default_locale' => $defaultLocale,
        'available_locales' => $available,
        'csrf_token' => Helpers::csrfToken(),
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $locale = isset($_POST['locale']) ? $_POST['locale'] : '';
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($csrfToken)) {
        $locale = Lang::locale();
    }
} else {
    $locale = isset($_GET['locale']) ? $_GET['locale'] : Lang::locale();
}

Lang::setLocale($locale);
$activeLocale = Lang::locale();

if (!isset($currentUser)) {
    $currentUser = Auth::currentUser();
}

if ($currentUser && isset($currentUser['id'])) {
    $userId = (int) $currentUser['id'];
    Settings::set('user_' . $userId . '_preferred_language', $activeLocale);

    $freshUser = Auth::findUser($userId);
    if ($freshUser) {
        $freshUser['locale'] = $activeLocale;
        Auth::refreshUser($freshUser);
    } else {
        $currentUser['locale'] = $activeLocale;
        Auth::refreshUser($currentUser);
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
