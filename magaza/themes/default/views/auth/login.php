<?php
use App\Helpers;

$activeTab = isset($activeTab) ? (string) $activeTab : (isset($_GET['tab']) ? (string) $_GET['tab'] : 'login');
$activeTab = in_array($activeTab, array('login', 'application'), true) ? $activeTab : 'login';

$loginAction = isset($loginAction) ? (string) $loginAction : '/bayi/login.php';
$applicationAction = isset($applicationAction) ? (string) $applicationAction : '/register.php';
$bayiApplyUrl = isset($bayiApplyUrl) ? (string) $bayiApplyUrl : '/bayi/login.php?tab=application';
$adminLoginUrl = isset($adminLoginUrl) ? (string) $adminLoginUrl : '/admin/login.php';
$forgotUrl = isset($forgotUrl) ? (string) $forgotUrl : '/password-reset.php';

$csrfToken = class_exists(Helpers::class) ? Helpers::csrfToken() : '';
$csrfField = $csrfToken !== '' ? '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">' : '';

$loginErrors = isset($loginErrors) && is_array($loginErrors) ? $loginErrors : array();
$loginSuccess = isset($loginSuccess) ? (string) $loginSuccess : '';
$loginWarning = isset($loginWarning) ? (string) $loginWarning : '';
$applicationErrors = isset($applicationErrors) && is_array($applicationErrors) ? $applicationErrors : array();
$applicationWarnings = isset($applicationWarnings) && is_array($applicationWarnings) ? $applicationWarnings : array();
$applicationSuccess = isset($applicationSuccess) ? (string) $applicationSuccess : '';

$packageOptions = isset($packageOptions) && is_array($packageOptions) ? $packageOptions : array();
$paymentGateways = isset($paymentGateways) && is_array($paymentGateways) ? $paymentGateways : array();
$phoneCountries = isset($phoneCountries) && is_array($phoneCountries) ? $phoneCountries : array();

$googleEnabled = !empty($googleLoginEnabled);
?>
<section class="py-5">
    <div class="container-xxl auth-wrapper">
        <div class="auth-card">
            <ul class="nav nav-tabs px-4 pt-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'login' ? 'active' : ''; ?>" id="auth-login-tab" data-bs-toggle="tab" data-bs-target="#auth-login-pane" type="button" role="tab" aria-controls="auth-login-pane" aria-selected="<?php echo $activeTab === 'login' ? 'true' : 'false'; ?>">Giriş Yap</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'application' ? 'active' : ''; ?>" id="auth-apply-tab" data-bs-toggle="tab" data-bs-target="#auth-apply-pane" type="button" role="tab" aria-controls="auth-apply-pane" aria-selected="<?php echo $activeTab === 'application' ? 'true' : 'false'; ?>">Bayi Başvurusu</button>
                </li>
            </ul>
            <div class="tab-content p-4 p-lg-5">
                <div class="tab-pane fade <?php echo $activeTab === 'login' ? 'show active' : ''; ?>" id="auth-login-pane" role="tabpanel" aria-labelledby="auth-login-tab">
                    <div class="row g-4 align-items-center">
                        <div class="col-12 col-lg-5">
                            <h2 class="fw-bold mb-3">Tek Hesapla Tüm Oyunlar</h2>
                            <p class="text-muted mb-4">Oyun hesapları, yazılım lisansları ve abonelik ürünlerinde anında teslimat deneyimini yaşayın. Bayi panelinden stoklarınızı yönetin.</p>
                            <ul class="list-unstyled text-muted small d-grid gap-2">
                                <li>• Otomatik teslimat altyapısı</li>
                                <li>• Geniş ürün portföyü</li>
                                <li>• Finans & raporlama modülleri</li>
                            </ul>
                        </div>
                        <div class="col-12 col-lg-7">
                            <div class="card card-panel border-0">
                                <div class="card-body p-4 p-lg-5">
                                    <h3 class="fw-semibold mb-3">Bayi Girişi</h3>
                                    <p class="text-muted mb-4">Yalnızca bayi hesapları bu panelden giriş yapabilir. Yönetici misiniz? <a href="<?php echo htmlspecialchars($adminLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">Admin girişine gidin</a>.</p>

                                    <?php if ($loginSuccess !== ''): ?>
                                        <div class="alert alert-success"><?php echo htmlspecialchars($loginSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <?php if ($loginWarning !== ''): ?>
                                        <div class="alert alert-warning"><?php echo htmlspecialchars($loginWarning, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <?php if ($loginErrors): ?>
                                        <div class="alert alert-danger">
                                            <ul class="mb-0">
                                                <?php foreach ($loginErrors as $error): ?>
                                                    <li><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <form method="post" action="<?php echo htmlspecialchars($loginAction, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" class="needs-validation" novalidate>
                                        <?php echo $csrfField; ?>
                                        <input type="hidden" name="form_intent" value="login">
                                        <div class="mb-3">
                                            <label for="loginIdentifier" class="form-label">E-posta veya kullanıcı adı</label>
                                            <input type="text" class="form-control form-control-lg" id="loginIdentifier" name="email" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="loginPassword" class="form-label">Şifre</label>
                                            <input type="password" class="form-control form-control-lg" id="loginPassword" name="password" required>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <a class="small text-decoration-none" href="<?php echo htmlspecialchars($forgotUrl, ENT_QUOTES, 'UTF-8'); ?>">Şifremi unuttum</a>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" value="1" id="rememberMe" name="remember">
                                                <label class="form-check-label" for="rememberMe">Beni hatırla</label>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-lg w-100">Giriş Yap</button>
                                    </form>

                                    <?php if ($googleEnabled): ?>
                                        <a class="btn btn-outline btn-lg w-100 mt-3" href="/oauth/google.php">Google ile Giriş Yap</a>
                                    <?php endif; ?>

                                    <div class="d-grid mt-4">
                                        <a class="btn btn-outline" href="<?php echo htmlspecialchars($bayiApplyUrl, ENT_QUOTES, 'UTF-8'); ?>">Bayi Başvurusu Yap</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade <?php echo $activeTab === 'application' ? 'show active' : ''; ?>" id="auth-apply-pane" role="tabpanel" aria-labelledby="auth-apply-tab">
                    <div class="row g-4">
                        <div class="col-12 col-lg-7">
                            <div class="card card-panel border-0">
                                <div class="card-body p-4 p-lg-5">
                                    <h3 class="fw-semibold mb-3">Yeni Bayi Başvurusu</h3>
                                    <p class="text-muted mb-4">Aşağıdaki formu doldurarak bayilik başvurunuzu iletebilirsiniz. Ekibimiz en kısa sürede sizinle iletişime geçecek.</p>

                                    <?php if ($applicationSuccess !== ''): ?>
                                        <div class="alert alert-success"><?php echo htmlspecialchars($applicationSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <?php if ($applicationWarnings): ?>
                                        <div class="alert alert-warning">
                                            <ul class="mb-0">
                                                <?php foreach ($applicationWarnings as $warning): ?>
                                                    <li><?php echo htmlspecialchars((string) $warning, ENT_QUOTES, 'UTF-8'); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($applicationErrors): ?>
                                        <div class="alert alert-danger">
                                            <ul class="mb-0">
                                                <?php foreach ($applicationErrors as $error): ?>
                                                    <li><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <form method="post" action="<?php echo htmlspecialchars($applicationAction, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data" class="row g-3">
                                        <?php echo $csrfField; ?>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label" for="applicationPackage">Paket Seçimi</label>
                                            <select class="form-select form-select-lg" id="applicationPackage" name="package_id">
                                                <?php if ($packageOptions): ?>
                                                    <?php foreach ($packageOptions as $package): ?>
                                                        <?php
                                                        if (!is_array($package)) {
                                                            continue;
                                                        }
                                                        $packageId = isset($package['id']) ? (int) $package['id'] : 0;
                                                        $packageName = isset($package['name']) ? (string) $package['name'] : 'Paket';
                                                        $packagePrice = isset($package['price']) ? (float) $package['price'] : 0;
                                                        ?>
                                                        <option value="<?php echo $packageId; ?>"><?php echo htmlspecialchars($packageName, ENT_QUOTES, 'UTF-8'); ?> - ₺<?php echo number_format($packagePrice, 2, ',', '.'); ?></option>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <option value="standart">Standart Paket</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label" for="applicationGateway">Ödeme Yöntemi</label>
                                            <select class="form-select form-select-lg" id="applicationGateway" name="payment_provider">
                                                <?php if ($paymentGateways): ?>
                                                    <?php foreach ($paymentGateways as $identifier => $gateway): ?>
                                                        <?php
                                                        if (is_array($gateway) && isset($gateway['label'])) {
                                                            $label = (string) $gateway['label'];
                                                        } else {
                                                            $label = is_string($gateway) ? $gateway : strtoupper((string) $identifier);
                                                        }
                                                        $value = is_string($identifier) ? $identifier : $label;
                                                        ?>
                                                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <option value="transfer">Banka Havalesi</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label" for="applicationName">Ad Soyad</label>
                                            <input type="text" id="applicationName" name="name" class="form-control form-control-lg" required>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label" for="applicationEmail">E-posta</label>
                                            <input type="email" id="applicationEmail" name="email" class="form-control form-control-lg" required>
                                        </div>
                                        <div class="col-12 col-md-5">
                                            <label class="form-label" for="applicationPhoneCode">Ülke Kodu</label>
                                            <select class="form-select form-select-lg" id="applicationPhoneCode" name="phone_country_code">
                                                <?php if ($phoneCountries): ?>
                                                    <?php foreach ($phoneCountries as $code => $label): ?>
                                                        <option value="<?php echo htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?></option>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <option value="90">+90 Türkiye</option>
                                                    <option value="1">+1 ABD</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-7">
                                            <label class="form-label" for="applicationPhone">Telefon</label>
                                            <input type="text" id="applicationPhone" name="phone_number" class="form-control form-control-lg" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" for="applicationCompany">Şirket / Mağaza Adı</label>
                                            <input type="text" id="applicationCompany" name="company" class="form-control" placeholder="Varsa şirket veya mağaza adınız">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" for="applicationNotes">Notlar</label>
                                            <textarea id="applicationNotes" name="notes" class="form-control" rows="3" placeholder="İş modelinizi veya beklentilerinizi paylaşabilirsiniz."></textarea>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label" for="applicationPassword">Şifre</label>
                                            <input type="password" id="applicationPassword" name="password" class="form-control form-control-lg" required>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label" for="applicationPasswordConfirm">Şifre (Tekrar)</label>
                                            <input type="password" id="applicationPasswordConfirm" name="password_confirmation" class="form-control form-control-lg" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" for="applicationReference">Ödeme Referansı</label>
                                            <input type="text" id="applicationReference" name="payment_reference" class="form-control" placeholder="Banka dekont numarası veya açıklaması">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" for="applicationNotice">Ödeme Notu</label>
                                            <textarea id="applicationNotice" name="payment_notice" class="form-control" rows="2" placeholder="Ödeme ile ilgili ek açıklamalarınız"></textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary btn-lg w-100">Başvuruyu Gönder</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-5">
                            <div class="card card-panel border-0 h-100">
                                <div class="card-body p-4 p-lg-5 d-grid gap-3">
                                    <h5 class="fw-semibold">Başvuru Sonrası</h5>
                                    <p class="text-muted small mb-0">Bilgileriniz incelendikten sonra ekibimiz sizinle iletişime geçer. Telegram detaylarını paylaşmanız durumunda süreç bildirimlerini anlık alabilirsiniz.</p>
                                    <div class="border-top border-light-subtle pt-3">
                                        <h6 class="text-uppercase text-muted small">Belgeler</h6>
                                        <ul class="list-unstyled text-muted small d-grid gap-2 mb-0">
                                            <li>• Vergi levhası veya şirket bilgisi (varsa)</li>
                                            <li>• Ödeme dekontu ve açıklaması</li>
                                            <li>• Telegram bot bilgileri (opsiyonel)</li>
                                        </ul>
                                    </div>
                                    <div class="bg-dark bg-opacity-25 rounded-3 p-3">
                                        <h6 class="fw-semibold mb-2">Destek Ekibi</h6>
                                        <p class="text-muted small mb-2">Süreçle ilgili sorularınız için 7/24 destek hattımızdan bize ulaşabilirsiniz.</p>
                                        <a class="btn btn-outline btn-sm" href="mailto:support@oyunhesap.com.tr">support@oyunhesap.com.tr</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
