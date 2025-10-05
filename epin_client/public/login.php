<?php
require_once __DIR__ . '/../includes/bootstrap.php';
redirect_if_authenticated();
$title = 'Müşteri Girişi';
require __DIR__ . '/partials/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 text-center mb-4">Müşteri Paneline Giriş</h1>
                <div class="alert alert-danger d-none" id="login-error"></div>
                <form id="login-form" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label for="login-email" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="login-email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="login-password" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="login-password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
                </form>
                <p class="text-center small mt-3">Hesabın yok mu? <a href="/epin_client/public/register.php">Hemen kayıt ol</a></p>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
