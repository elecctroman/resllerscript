<?php
require __DIR__ . '/../store/bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Services\UserProfileService;

$user = Auth::requireCustomer('/account/login');

$profile = UserProfileService::fetch((int) $user['id']);

if ($profile['first_name'] === '' && !empty($user['name'])) {
    $parts = preg_split('/\s+/u', trim((string) $user['name']));
    if ($parts && count($parts) > 0) {
        $profile['first_name'] = array_shift($parts);
        $profile['last_name'] = $parts ? implode(' ', $parts) : '';
    }
}

$errors = array();
$success = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum doğrulama anahtarınız geçersiz. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $input = array(
            'first_name' => isset($_POST['first_name']) ? trim((string) $_POST['first_name']) : '',
            'last_name'  => isset($_POST['last_name']) ? trim((string) $_POST['last_name']) : '',
            'phone'      => isset($_POST['phone']) ? trim((string) $_POST['phone']) : '',
            'country'    => isset($_POST['country']) ? trim((string) $_POST['country']) : '',
            'city'       => isset($_POST['city']) ? trim((string) $_POST['city']) : '',
            'district'   => isset($_POST['district']) ? trim((string) $_POST['district']) : '',
            'address'    => isset($_POST['address']) ? trim((string) $_POST['address']) : '',
        );

        if ($input['first_name'] === '') {
            $errors[] = 'İsim alanı zorunludur.';
        }

        if ($input['last_name'] === '') {
            $errors[] = 'Soyisim alanı zorunludur.';
        }

        if ($input['phone'] !== '' && !preg_match('/^[0-9+()\-\s]{6,20}$/', $input['phone'])) {
            $errors[] = 'Telefon numarası geçerli bir formatta olmalıdır.';
        }

        if (!$errors) {
            try {
                UserProfileService::save((int) $user['id'], $input);
                $profile = array_merge($profile, $input);

                $freshUser = Auth::findUser((int) $user['id']);
                if ($freshUser) {
                    Auth::refreshUser($freshUser);
                    $user = $freshUser;
                }

                $success[] = 'Profil bilgileriniz güncellendi.';
            } catch (\PDOException $exception) {
                $errors[] = 'Profiliniz kaydedilirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
            }
        }
    }
}

$viewData = array(
    'title' => 'Hesabım',
    'user' => $user,
    'profile' => $profile,
    'errors' => $errors,
    'success' => $success,
    'csrf_token' => Helpers::csrfToken(),
    'balance' => isset($user['balance']) ? (float) $user['balance'] : 0.0,
    'logout_token' => Helpers::csrfToken(),
);

store_render('account/panel', $viewData);
