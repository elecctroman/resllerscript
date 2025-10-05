<?php
require_once __DIR__ . '/../includes/bootstrap.php';
redirect_if_not_authenticated();
$user = current_user();
$title = 'Profil Bilgileri';
require __DIR__ . '/partials/header.php';
?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Kişisel Bilgiler</h2>
                <div class="alert alert-success d-none" id="profile-success"></div>
                <div class="alert alert-danger d-none" id="profile-error"></div>
                <form id="profile-form">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label for="profile-name" class="form-label">Ad Soyad</label>
                        <input type="text" class="form-control" id="profile-name" name="name" value="<?= sanitize($user['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="profile-email" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="profile-email" name="email" value="<?= sanitize($user['email']) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Bilgileri Güncelle</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Şifre Güncelle</h2>
                <div class="alert alert-success d-none" id="password-success"></div>
                <div class="alert alert-danger d-none" id="password-error"></div>
                <form id="password-form">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label for="current-password" class="form-label">Mevcut Şifre</label>
                        <input type="password" class="form-control" id="current-password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new-password" class="form-label">Yeni Şifre</label>
                        <input type="password" class="form-control" id="new-password" name="new_password" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label for="new-password-confirm" class="form-label">Yeni Şifre (Tekrar)</label>
                        <input type="password" class="form-control" id="new-password-confirm" name="new_password_confirm" minlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Şifreyi Değiştir</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
