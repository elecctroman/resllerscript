<?php
require __DIR__ . '/../bootstrap.php';

use App\Customers\CustomerRepository;
use App\Helpers;

$success = null;
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '') {
            $errors[] = 'E-posta adresi zorunludur.';
        } else {
            $customer = CustomerRepository::findByEmail($email);
            if ($customer) {
                $newPassword = substr(bin2hex(random_bytes(6)), 0, 12);
                CustomerRepository::updatePassword((int)$customer['id'], $newPassword);
                App\mail($email, 'Şifre Sıfırlama', "Yeni şifreniz: " . $newPassword);
                $success = 'Yeni şifreniz e-posta adresinize gönderildi.';
            } else {
                $errors[] = 'Bu e-posta adresine ait bir müşteri bulunamadı.';
            }
        }
    }
}

Helpers::includeTemplate('auth-header.php');
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="text-center mb-4">
            <div class="brand">Şifre Sıfırlama</div>
            <p class="text-muted">E-posta adresinizi girerek yeni bir şifre talep edin.</p>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
        <?php endif; ?>
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
                <label class="form-label" for="email">E-posta</label>
                <input type="email" class="form-control" id="email" name="email" required value="<?= Helpers::sanitize($_POST['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary w-100">Şifreyi Sıfırla</button>
            <div class="text-center mt-3">
                <a href="/customer/login.php" class="small">Giriş ekranına dön</a>
            </div>
        </form>
    </div>
</div>
<?php Helpers::includeTemplate('auth-footer.php');
