<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Mailer;

if (!empty($_SESSION['user'])) {
    Helpers::redirect('/dashboard.php');
}

$errors = [];
$successMessage = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $reset = Auth::validateResetToken($token);

    if (!$reset) {
        $errors[] = 'Bu sıfırlama bağlantısı geçersiz veya süresi dolmuş.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';

        if (strlen($password) < 8) {
            $errors[] = 'Şifreniz en az 8 karakter olmalıdır.';
        }

        if ($password !== $passwordConfirmation) {
            $errors[] = 'Şifreler eşleşmiyor.';
        }

        if (!$errors) {
            Auth::resetPassword($reset['email'], $password);
            Auth::markResetTokenUsed((int)$reset['id']);
            $successMessage = 'Şifreniz başarıyla güncellendi. Giriş yapabilirsiniz.';
        }
    }

    include __DIR__ . '/templates/auth-header.php';
    ?>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="mb-4 text-center">
                <div class="brand">Şifre Sıfırlama</div>
                <p class="text-muted">Yeni şifrenizi belirleyin.</p>
            </div>

            <?php if ($successMessage): ?>
                <div class="alert alert-success"><?= Helpers::sanitize($successMessage) ?></div>
                <a href="/" class="btn btn-primary w-100">Girişe Dön</a>
            <?php else: ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Yeni Şifre</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Yeni Şifre (Tekrar)</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Şifremi Güncelle</button>
                    <div class="text-center mt-3">
                        <a href="/" class="small">Giriş sayfasına dön</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
    include __DIR__ . '/templates/auth-footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email) {
        $errors[] = 'Lütfen e-posta adresinizi girin.';
    }

    if (!$errors) {
        $token = Auth::createPasswordReset($email);
        $resetLink = sprintf('%s/password-reset.php?token=%s', rtrim((isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'], '/'), $token);
        Auth::sendResetLink($email, $token, $resetLink);
        $successMessage = 'Şifre sıfırlama bağlantısı e-posta adresinize gönderildi.';
    }
}

include __DIR__ . '/templates/auth-header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="mb-4 text-center">
            <div class="brand">Şifremi Unuttum</div>
            <p class="text-muted">Kayıtlı e-posta adresinizi girerek sıfırlama bağlantısı alın.</p>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?= Helpers::sanitize($successMessage) ?></div>
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

        <form method="post">
            <div class="mb-3">
                <label class="form-label">E-posta Adresiniz</label>
                <input type="email" class="form-control" name="email" value="<?= Helpers::sanitize($_POST['email'] ?? '') ?>" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Bağlantı Gönder</button>
            <div class="text-center mt-3">
                <a href="/" class="small">Giriş sayfasına dön</a>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/templates/auth-footer.php';
