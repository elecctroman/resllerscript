<?php
require_once __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

if (Auth::check()) {
    Helpers::redirect('/account');
}

$pdo = Database::connection();

$errors = array();
$old = array(
    'name' => '',
    'email' => '',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($csrfToken)) {
        $errors[] = 'Oturum doğrulaması başarısız oldu. Lütfen formu yeniden gönderin.';
    }

    $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
    $email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $passwordConfirm = isset($_POST['password_confirmation']) ? (string) $_POST['password_confirmation'] : '';

    $old['name'] = $name;
    $old['email'] = $email;

    if ($name === '') {
        $errors[] = 'Ad alanı zorunludur.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi girin.';
    }

    if ($password === '' || strlen($password) < 8) {
        $errors[] = 'Şifreniz en az 8 karakter olmalıdır.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'Şifre ve şifre doğrulama alanları eşleşmiyor.';
    }

    if (!$errors) {
        $existing = Auth::findUserByEmail($email);
        if ($existing) {
            $errors[] = 'Bu e-posta adresiyle kayıtlı bir hesap zaten mevcut.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $userId = Auth::createUser($name, $email, $password, 'customer');
            $pdo->commit();

            $user = Auth::findUser($userId);
            if ($user) {
                Auth::login($user);
                Helpers::redirect('/account');
            }

            $errors[] = 'Hesabınız oluşturuldu ancak oturum açılamadı. Lütfen giriş yapmayı deneyin.';
        } catch (\PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Storefront Register] Kullanıcı oluşturulamadı: ' . $exception->getMessage());
            $errors[] = 'Kayıt işlemi sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
        }
    }
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
    error_log('[Storefront Register] Kategori başlıkları yüklenemedi: ' . $exception->getMessage());
    $headerCategories = array();
}

store_render('auth/register', array(
    'pageTitle' => 'Yeni Hesap Oluştur',
    'errors' => $errors,
    'old' => $old,
    'headerCategories' => $headerCategories,
    'metaDescription' => (string) get_setting('seo_description', ''),
    'loginUrl' => store_url('account/login'),
));
