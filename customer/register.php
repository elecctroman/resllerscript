<?php
require __DIR__ . '/../bootstrap.php';

use App\Customers\CustomerAuth;
use App\Helpers;
use App\Lang;

CustomerAuth::requireGuest();

$errors = array();
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $errors[] = 'Oturum doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $surname = trim((string)($_POST['surname'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        if ($name === '' || $surname === '' || $email === '' || $password === '' || $confirm === '') {
            $errors[] = 'Lütfen tüm zorunlu alanları doldurun.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir e-posta adresi girin.';
        }

        if ($password !== $confirm) {
            $errors[] = 'Şifreler eşleşmiyor.';
        }

        if (!$errors) {
            try {
                $customer = CustomerAuth::register(array(
                    'name' => $name,
                    'surname' => $surname,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => $password,
                    'locale' => $_POST['locale'] ?? 'tr',
                    'currency' => $_POST['currency'] ?? 'TRY',
                ));
                $_SESSION['customer'] = $customer;
                Helpers::redirect('/customer/dashboard.php');
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
    }
}

Helpers::includeTemplate('auth-header.php');
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="text-center mb-4">
            <div class="brand">Yeni Müşteri Kaydı</div>
            <p class="text-muted">Saniyeler içinde panel hesabınızı oluşturun.</p>
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
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">Ad</label>
                    <input type="text" class="form-control" name="name" id="name" required value="<?= Helpers::sanitize($_POST['name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="surname">Soyad</label>
                    <input type="text" class="form-control" name="surname" id="surname" required value="<?= Helpers::sanitize($_POST['surname'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">E-posta</label>
                    <input type="email" class="form-control" name="email" id="email" required value="<?= Helpers::sanitize($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="phone">Telefon</label>
                    <input type="text" class="form-control" name="phone" id="phone" value="<?= Helpers::sanitize($_POST['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">Şifre</label>
                    <input type="password" class="form-control" name="password" id="password" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password_confirm">Şifre (Tekrar)</label>
                    <input type="password" class="form-control" name="password_confirm" id="password_confirm" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="locale">Dil</label>
                    <select class="form-select" name="locale" id="locale">
                        <option value="tr"<?= (($_POST['locale'] ?? '') === 'tr') ? ' selected' : '' ?>>Türkçe</option>
                        <option value="en"<?= (($_POST['locale'] ?? '') === 'en') ? ' selected' : '' ?>>English</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="currency">Para Birimi</label>
                    <select class="form-select" name="currency" id="currency">
                        <option value="TRY"<?= (($_POST['currency'] ?? '') === 'TRY') ? ' selected' : '' ?>>TRY</option>
                        <option value="USD"<?= (($_POST['currency'] ?? '') === 'USD') ? ' selected' : '' ?>>USD</option>
                        <option value="EUR"<?= (($_POST['currency'] ?? '') === 'EUR') ? ' selected' : '' ?>>EUR</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-4">Hesap Oluştur</button>
            <div class="text-center mt-3">
                <a href="/customer/login.php" class="small">Zaten hesabım var</a>
            </div>
        </form>
    </div>
</div>
<?php Helpers::includeTemplate('auth-footer.php');
