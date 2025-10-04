<?php
require __DIR__ . '/../bootstrap.php';

use App\Customers\CustomerAuth;
use App\Helpers;
use App\Lang;
use App\Settings;

CustomerAuth::requireGuest();

$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $errors[] = 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

        if ($email === '' || $password === '') {
            $errors[] = 'E-posta ve şifre alanları zorunludur.';
        } else {
            $customer = CustomerAuth::attempt($email, $password);
            if ($customer) {
                $_SESSION['customer'] = $customer;
                Helpers::redirect('/customer/dashboard.php');
            } else {
                $errors[] = 'Bilgiler doğrulanamadı. Lütfen tekrar deneyin.';
            }
        }
    }
}

$siteName = Helpers::siteName();
Lang::boot();
Helpers::includeTemplate('auth-header.php');
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="text-center mb-4">
            <div class="brand"><?= Helpers::sanitize($siteName) ?></div>
            <p class="text-muted mt-2">Müşteri paneline giriş yapın</p>
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
        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
            <div class="mb-3">
                <label for="email" class="form-label">E-posta Adresi</label>
                <input type="email" class="form-control" name="email" id="email" required value="<?= Helpers::sanitize(isset($_POST['email']) ? $_POST['email'] : '') ?>">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Şifre</label>
                <input type="password" class="form-control" name="password" id="password" required>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="/customer/password-reset.php" class="small">Şifremi Unuttum</a>
                <a href="/customer/register.php" class="small">Yeni Hesap Oluştur</a>
            </div>
            <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
            <div class="text-center mt-3">
                <a href="/index.php" class="small">Bayi giriş ekranına dön</a>
            </div>
        </form>
    </div>
</div>
<?php Helpers::includeTemplate('auth-footer.php');
