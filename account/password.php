<?php
require __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Helpers;
use App\Auth;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

$pdo = Database::connection();
$errors = array();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    $currentPassword = isset($_POST['current_password']) ? (string)$_POST['current_password'] : '';
    $newPassword = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
    $newPasswordConfirm = isset($_POST['new_password_confirmation']) ? (string)$_POST['new_password_confirmation'] : '';

    if (!Helpers::verifyCsrf($csrfToken)) {
        $errors[] = 'Oturum doğrulama anahtarınız geçersiz. Lütfen sayfayı yenileyip tekrar deneyin.';
    }

    if (!$errors) {
        if ($currentPassword === '') {
            $errors[] = 'Mevcut şifrenizi girmeniz gerekir.';
        }

        if ($newPassword === '' || $newPasswordConfirm === '') {
            $errors[] = 'Yeni şifre alanları boş bırakılamaz.';
        }

        if ($newPassword !== '' && strlen($newPassword) < 8) {
            $errors[] = 'Yeni şifre en az 8 karakter olmalıdır.';
        }

        if ($newPassword !== $newPasswordConfirm) {
            $errors[] = 'Yeni şifre alanları birbiriyle eşleşmiyor.';
        }
    }

    if (!$errors) {
        try {
            $passwordStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
            $passwordStmt->execute(array('id' => $user['id']));
            $passwordRow = $passwordStmt->fetch();

            if (!$passwordRow || !password_verify($currentPassword, $passwordRow['password_hash'])) {
                $errors[] = 'Mevcut şifreniz doğrulanamadı.';
            }
        } catch (\PDOException $exception) {
            $errors[] = 'Şifreniz doğrulanırken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
        }
    }

    if (!$errors) {
        try {
            $pdo->prepare('UPDATE users SET password_hash = :password, updated_at = NOW() WHERE id = :id')
                ->execute(array(
                    'password' => password_hash($newPassword, PASSWORD_BCRYPT),
                    'id' => $user['id'],
                ));

            $freshUser = Auth::findUser($user['id']);
            if ($freshUser) {
                $_SESSION['user'] = $freshUser;
            }

            $success = 'Şifreniz başarıyla güncellendi.';
        } catch (\PDOException $exception) {
            $errors[] = 'Şifreniz güncellenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
        }
    }
}

$pageTitle = 'Şifre Değişikliği';
$pageDescription = 'Güvenliğiniz için mevcut şifrenizi doğrulayın ve yeni bir şifre belirleyin.';
$activeMenu = 'password';

ob_start();
?>
<div class="account-section">
    <div class="account-section__header">
        <h5 class="account-section__title">Şifrenizi Güncelleyin</h5>
        <span class="text-muted small">Güçlü bir şifre hesabınızı korur.</span>
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
    <?php if ($success): ?>
        <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
    <?php endif; ?>
    <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
        <div class="col-12">
            <label class="form-label">Mevcut Şifre</label>
            <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Yeni Şifre</label>
            <input type="password" name="new_password" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Yeni Şifre (Tekrar)</label>
            <input type="password" name="new_password_confirmation" class="form-control" required>
        </div>
        <div class="col-12">
            <small class="text-muted">Şifreniz en az 8 karakter uzunluğunda olmalıdır.</small>
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-success">Şifreyi Güncelle</button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();

require __DIR__ . '/../themes/store/default/account/layout.php';
