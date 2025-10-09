<?php
require_once __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Helpers;

$token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
if (!Helpers::verifyCsrf($token)) {
    $_SESSION['account_login_warning'] = 'Oturum kapatma isteğiniz doğrulanamadı. Lütfen tekrar deneyin.';
    Helpers::redirect('/account');
}

Auth::logoutStoreUser();
Auth::logoutAdmin();
Auth::logoutReseller();

Helpers::redirect('/');
