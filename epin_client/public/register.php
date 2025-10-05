<?php
require_once __DIR__ . '/../includes/bootstrap.php';
redirect_if_authenticated();
$title = 'Yeni Hesap Oluştur';
require __DIR__ . '/partials/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 text-center mb-4">Hızlıca hesabını oluştur</h1>
                <div class="alert alert-danger d-none" id="register-error"></div>
                <form id="register-form" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="register-name" class="form-label">Ad Soyad</label>
                            <input type="text" class="form-control" id="register-name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="register-email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="register-email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="register-password" class="form-label">Şifre</label>
                            <input type="password" class="form-control" id="register-password" name="password" minlength="6" required>
                        </div>
                        <div class="col-md-6">
                            <label for="register-password-confirm" class="form-label">Şifre (Tekrar)</label>
                            <input type="password" class="form-control" id="register-password-confirm" name="password_confirm" minlength="6" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3">Hesap Oluştur</button>
                </form>
                <p class="text-center small mt-3">Zaten hesabın var mı? <a href="/epin_client/public/login.php">Giriş yap</a></p>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
