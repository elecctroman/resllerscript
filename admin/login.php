<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Lang;
use App\Settings;

$adminUser = Auth::currentAdmin();
if ($adminUser) {
    Helpers::redirect('/admin/dashboard.php');
}

Lang::boot();

$errors = array();
$flashSuccess = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : null;
$flashWarning = isset($_SESSION['flash_warning']) ? $_SESSION['flash_warning'] : null;
unset($_SESSION['flash_success'], $_SESSION['flash_warning']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $errors[] = 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $identifier = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

        if ($identifier === '' || $password === '') {
            $errors[] = 'Lütfen kullanıcı adı/e-posta ve şifre alanlarını doldurun.';
        } else {
            $user = Auth::attempt($identifier, $password);
            if ($user && Auth::isAdminRole($user['role'])) {
                Auth::loginAdmin($user);

                $preferredLanguage = Settings::get('user_' . $user['id'] . '_preferred_language');
                if ($preferredLanguage) {
                    Lang::setLocale($preferredLanguage);
                } else {
                    Lang::boot();
                }

                Helpers::redirect('/admin/dashboard.php');
            } else {
                $errors[] = 'Bilgileriniz doğrulanamadı veya bu hesap yönetici erişimine sahip değil.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Girişi</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        .auth-wrapper {
            width: 100%;
            max-width: 420px;
            background: rgba(15, 23, 42, 0.75);
            border-radius: 1.25rem;
            padding: 2.5rem 2rem;
            color: #f8fafc;
            box-shadow: 0 30px 40px -20px rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
        }
        .auth-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .auth-subtitle {
            color: rgba(248, 250, 252, 0.7);
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.95rem;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(148, 163, 184, 0.4);
            background: rgba(15, 23, 42, 0.6);
            color: #f8fafc;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: rgba(96, 165, 250, 0.9);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
        }
        .btn-primary {
            width: 100%;
            border: none;
            border-radius: 0.75rem;
            padding: 0.9rem 1rem;
            font-size: 1rem;
            font-weight: 600;
            background: linear-gradient(135deg, #2563eb, #60a5fa);
            color: #fff;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 15px 30px -15px rgba(37, 99, 235, 0.7);
        }
        .alert {
            border-radius: 0.75rem;
            padding: 0.85rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            color: #bbf7d0;
            border: 1px solid rgba(34, 197, 94, 0.35);
        }
        .alert-warning {
            background: rgba(251, 191, 36, 0.12);
            color: #fde68a;
            border: 1px solid rgba(251, 191, 36, 0.35);
        }
        .alert-danger {
            background: rgba(248, 113, 113, 0.15);
            color: #fecaca;
            border: 1px solid rgba(248, 113, 113, 0.4);
        }
        .auth-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.9rem;
            color: rgba(248, 250, 252, 0.65);
        }
        .auth-footer a {
            color: #93c5fd;
            text-decoration: none;
        }
        .auth-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <h1 class="auth-title">Yönetici Girişi</h1>
    <p class="auth-subtitle">Yönetim paneline erişmek için kimlik bilgilerinizle giriş yapın.</p>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><?= Helpers::sanitize($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashWarning): ?>
        <div class="alert alert-warning"><?= Helpers::sanitize($flashWarning) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul style="margin:0; padding-left:1rem;">
                <?php foreach ($errors as $error): ?>
                    <li><?= Helpers::sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
        <div class="form-group">
            <label for="loginEmail">Kullanıcı adı veya e-posta</label>
            <input type="text" id="loginEmail" name="email" value="<?= isset($_POST['email']) ? Helpers::sanitize($_POST['email']) : '' ?>" required autofocus>
        </div>
        <div class="form-group">
            <label for="loginPassword">Şifre</label>
            <input type="password" id="loginPassword" name="password" required>
        </div>
        <button type="submit" class="btn-primary">Giriş Yap</button>
    </form>

    <div class="auth-footer">
        <p><a href="/password-reset.php">Şifremi Unuttum</a></p>
        <p>Bayi misiniz? <a href="/bayi/login.php">Bayi giriş ekranına gidin</a></p>
    </div>
</div>
</body>
</html>
