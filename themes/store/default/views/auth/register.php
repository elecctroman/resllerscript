<?php
use App\Helpers;

$errors = isset($errors) && is_array($errors) ? $errors : array();
$old = isset($old) && is_array($old) ? $old : array('name' => '', 'email' => '');
$csrfToken = Helpers::csrfToken();
?>
<section class="py-5">
    <div class="container-xxl auth-wrapper">
        <div class="auth-card">
            <div class="row g-4 align-items-center">
                <div class="col-12 col-lg-6">
                    <h1 class="fw-bold mb-3">Yeni Hesap Oluştur</h1>
                    <p class="text-muted mb-4">Oyun hesapları, dijital lisanslar ve abonelik ürünlerinde hızlı teslimatı deneyimlemek için hemen ücretsiz üyelik oluşturun.</p>
                    <ul class="list-unstyled text-muted small d-grid gap-2">
                        <li>• Tek panelden sipariş takibi</li>
                        <li>• Favori ürünleri kaydetme</li>
                        <li>• Güvenli ödeme ve faturalandırma</li>
                    </ul>
                    <div class="mt-4 text-muted small">
                        Zaten hesabınız var mı? <a class="text-decoration-none" href="<?php echo htmlspecialchars(store_url('account/login'), ENT_QUOTES, 'UTF-8'); ?>">Giriş yapın</a>.
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="card card-panel border-0">
                        <div class="card-body p-4 p-lg-5">
                            <h2 class="fw-semibold mb-3">Kayıt Formu</h2>
                            <p class="text-muted mb-4">Bilgilerinizi girdikten sonra mağaza hesabınız anında oluşturulur.</p>

                            <?php if ($errors): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="post" class="row g-3" autocomplete="off">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="col-12">
                                    <label class="form-label" for="registerName">Ad Soyad</label>
                                    <input type="text" class="form-control form-control-lg" id="registerName" name="name" required value="<?php echo isset($old['name']) ? htmlspecialchars((string) $old['name'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="registerEmail">E-posta</label>
                                    <input type="email" class="form-control form-control-lg" id="registerEmail" name="email" required value="<?php echo isset($old['email']) ? htmlspecialchars((string) $old['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="registerPassword">Şifre</label>
                                    <input type="password" class="form-control form-control-lg" id="registerPassword" name="password" required minlength="8">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="registerPasswordConfirm">Şifre Tekrarı</label>
                                    <input type="password" class="form-control form-control-lg" id="registerPasswordConfirm" name="password_confirmation" required minlength="8">
                                </div>
                                <div class="col-12 d-grid mt-3">
                                    <button type="submit" class="btn btn-primary btn-lg">Kayıt Ol</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
