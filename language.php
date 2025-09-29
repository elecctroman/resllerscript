<?php
require __DIR__ . '/bootstrap.php';

use App\Helpers;
use App\Lang;
use App\Settings;
use App\Auth;

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

if (!empty($_SESSION['user']) && isset($_SESSION['user']['id'])) {
    $userId = (int)$_SESSION['user']['id'];
    Settings::set('user_' . $userId . '_preferred_language', Lang::locale());
    $freshUser = Auth::findUser($userId);
    if ($freshUser) {
        $_SESSION['user'] = $freshUser;
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
