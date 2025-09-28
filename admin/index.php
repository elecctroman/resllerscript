<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;

if (!empty($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        Helpers::redirect('/admin/dashboard.php');
    }

    Helpers::redirect('/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$identifier || !$password) {
        $errors[] = 'Lütfen kullanıcı adı/e-posta ve şifre alanlarını doldurun.';
    } else {
        $user = Auth::attempt($identifier, $password);

        if ($user && $user['role'] === 'admin') {
            $_SESSION['user'] = $user;
            Helpers::redirect('/admin/dashboard.php');
        }

        $errors[] = 'Yalnızca yöneticiler giriş yapabilir. Bilgilerinizi kontrol edin.';
    }
}

include __DIR__ . '/../templates/auth-header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="text-center mb-4">
            <div class="brand">Yönetici Girişi</div>
            <p class="text-muted mt-2">Bayi ağı yönetimi için yetkili paneli</p>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= Helpers::sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="admin-email" class="form-label">E-posta veya Kullanıcı Adı</label>
                <input type="text" class="form-control" id="admin-email" name="email" required value="<?= Helpers::sanitize($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="admin-password" class="form-label">Şifre</label>
                <input type="password" class="form-control" id="admin-password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Yönetici Paneline Gir</button>
            <div class="text-center mt-3">
                <a href="/" class="small">Bayi giriş ekranına dön</a>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../templates/auth-footer.php';
