<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Lang;
use App\Settings;

$clientId = Settings::get('google_oauth_client_id');
$clientSecret = Settings::get('google_oauth_client_secret');

if (!$clientId || !$clientSecret) {
    $_SESSION['flash_warning'] = 'Google ile giriş özelliği yapılandırılmamış.';
    Helpers::redirect('/');
}

$redirectUri = Helpers::url('oauth/google.php', true);
$step = isset($_GET['step']) ? (string) $_GET['step'] : '';

if ($step === 'callback') {
    handleCallback($clientId, $clientSecret, $redirectUri);
    return;
}

startAuthorization($clientId, $redirectUri);

function startAuthorization(string $clientId, string $redirectUri): void
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;

    $params = array(
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account',
    );

    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    header('Location: ' . $authUrl);
    exit;
}

function handleCallback(string $clientId, string $clientSecret, string $redirectUri): void
{
    $state = isset($_GET['state']) ? (string) $_GET['state'] : '';
    $storedState = isset($_SESSION['google_oauth_state']) ? (string) $_SESSION['google_oauth_state'] : '';
    unset($_SESSION['google_oauth_state']);

    if ($state === '' || $storedState === '' || !hash_equals($storedState, $state)) {
        $_SESSION['flash_warning'] = 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
        Helpers::redirect('/');
    }

    if (isset($_GET['error'])) {
        $error = (string) $_GET['error'];
        $_SESSION['flash_warning'] = 'Google oturumu iptal edildi: ' . $error;
        Helpers::redirect('/');
    }

    $code = isset($_GET['code']) ? (string) $_GET['code'] : '';
    if ($code === '') {
        $_SESSION['flash_warning'] = 'Google tarafından geçerli bir yetkilendirme kodu gönderilmedi.';
        Helpers::redirect('/');
    }

    $tokenResponse = exchangeCodeForToken($code, $clientId, $clientSecret, $redirectUri);
    if (!$tokenResponse['success']) {
        $_SESSION['flash_warning'] = $tokenResponse['message'];
        Helpers::redirect('/');
    }

    $accessToken = $tokenResponse['access_token'];
    $userInfo = fetchUserInfo($accessToken);
    if (!$userInfo['success']) {
        $_SESSION['flash_warning'] = $userInfo['message'];
        Helpers::redirect('/');
    }

    $profile = $userInfo['profile'];
    $email = isset($profile['email']) ? strtolower(trim((string) $profile['email'])) : '';
    $emailVerified = isset($profile['email_verified']) ? (bool) $profile['email_verified'] : true;

    if ($email === '') {
        $_SESSION['flash_warning'] = 'Google hesabından e-posta bilgisi alınamadı.';
        Helpers::redirect('/');
    }

    if (!$emailVerified) {
        $_SESSION['flash_warning'] = 'Google hesabınız doğrulanmamış görünüyor. Lütfen önce hesabınızı doğrulayın.';
        Helpers::redirect('/');
    }

    $user = Auth::findActiveUserByEmail($email);
    if (!$user) {
        $_SESSION['flash_warning'] = 'Bu e-posta adresiyle kayıtlı aktif bayi bulunamadı.';
        Helpers::redirect('/');
    }

    $_SESSION['user'] = $user;

    $preferredLanguage = Settings::get('user_' . $user['id'] . '_preferred_language');
    if ($preferredLanguage) {
        Lang::setLocale($preferredLanguage);
    } else {
        Lang::boot();
    }

    $redirectTarget = Auth::isAdminRole($user['role']) ? '/admin/dashboard.php' : '/dashboard.php';
    Helpers::redirect($redirectTarget);
}

/**
 * @return array{success:bool,message:string,access_token?:string}
 */
function exchangeCodeForToken(string $code, string $clientId, string $clientSecret, string $redirectUri): array
{
    $payload = array(
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    );

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return array('success' => false, 'message' => 'Google token isteği başarısız: ' . $error);
    }

    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded)) {
        return array('success' => false, 'message' => 'Google token yanıtı çözümlenemedi.');
    }

    if ($status < 200 || $status >= 300) {
        $message = isset($decoded['error_description']) ? (string) $decoded['error_description'] : 'Yetkilendirme sırasında hata oluştu.';
        return array('success' => false, 'message' => $message);
    }

    if (empty($decoded['access_token'])) {
        return array('success' => false, 'message' => 'Google erişim anahtarı alınamadı.');
    }

    return array('success' => true, 'message' => 'OK', 'access_token' => (string) $decoded['access_token']);
}

/**
 * @return array{success:bool,message:string,profile?:array<string,mixed>}
 */
function fetchUserInfo(string $accessToken): array
{
    $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $accessToken, 'Accept: application/json'));

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return array('success' => false, 'message' => 'Google kullanıcı bilgileri alınamadı: ' . $error);
    }

    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded)) {
        return array('success' => false, 'message' => 'Google kullanıcı bilgileri çözümlenemedi.');
    }

    if ($status < 200 || $status >= 300) {
        $message = isset($decoded['error_description']) ? (string) $decoded['error_description'] : 'Google kullanıcı bilgileri alınamadı.';
        return array('success' => false, 'message' => $message);
    }

    return array('success' => true, 'message' => 'OK', 'profile' => $decoded);
}
