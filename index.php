<?php
session_start();

$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

$configPath = __DIR__ . '/config/config.php';

if (!file_exists($configPath)) {
    App\Helpers::includeTemplate('auth-header.php');
    ?>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="text-center mb-4">
                <div class="brand">Bayi Yönetim Sistemi</div>
                <p class="text-muted mt-2">Kuruluma başlamadan önce yapılandırmayı tamamlayın</p>
            </div>
            <div class="alert alert-warning">
                <h5 class="alert-heading">Yapılandırma Gerekli</h5>
                <p class="mb-2">Lütfen <code>config/config.sample.php</code> dosyasını <code>config/config.php</code> olarak kopyalayın ve MySQL bağlantı bilgilerinizi girin.</p>
                <ol class="mb-0 text-start">
                    <li><code>config/config.sample.php</code> dosyasını kopyalayın.</li>
                    <li>Yeni dosyada <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> ve <code>DB_PASSWORD</code> değerlerini güncelleyin.</li>
                    <li>Veritabanınızı oluşturup <code>schema.sql</code> dosyasındaki tabloları içeri aktarın.</li>
                    <li>Ardından bu sayfayı yenileyerek giriş ekranına ulaşın.</li>
                </ol>
            </div>
        </div>
    </div>
    <?php
    App\Helpers::includeTemplate('auth-footer.php');
    exit;
}

require $configPath;

use App\Auth;
use App\Helpers;
use App\Lang;
use App\Settings;

try {
    App\Database::initialize([
        'host' => DB_HOST,
        'name' => DB_NAME,
        'user' => DB_USER,
        'password' => DB_PASSWORD,
    ]);
} catch (\PDOException $exception) {
    App\Helpers::includeTemplate('auth-header.php');
    ?>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="text-center mb-4">
                <div class="brand">Bayi Yönetim Sistemi</div>
                <p class="text-muted mt-2">Veritabanı bağlantısı kurulamadı</p>
            </div>
            <div class="alert alert-danger">
                <h5 class="alert-heading">Bağlantı Hatası</h5>
                <p class="mb-2">Lütfen <code>config/config.php</code> dosyanızdaki MySQL bilgilerini kontrol edin ve veritabanı sunucunuzu doğrulayın.</p>
                <p class="mb-0 small text-muted">Hata detayı: <?= Helpers::sanitize($exception->getMessage()) ?></p>
            </div>
        </div>
    </div>
    <?php
    App\Helpers::includeTemplate('auth-footer.php');
    exit;
}

Lang::boot();

$siteName = Helpers::siteName();
$siteTagline = Helpers::siteTagline();

if (!empty($_SESSION['user'])) {
    $redirectTarget = Auth::isAdminRole($_SESSION['user']['role']) ? '/admin/dashboard.php' : '/dashboard.php';
    Helpers::redirect($redirectTarget);
}

$flashSuccess = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : null;
$flashWarning = isset($_SESSION['flash_warning']) ? $_SESSION['flash_warning'] : null;
unset($_SESSION['flash_success'], $_SESSION['flash_warning']);

$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $errors[] = 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $identifier = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

        if ($identifier === '' || $password === '') {
            $errors[] = 'Lütfen kullanıcı adı/e-posta ve şifre alanlarını doldurun.';
        } else {
            $user = Auth::attempt($identifier, $password);
            if ($user) {
                $_SESSION['user'] = $user;
                $preferredLanguage = Settings::get('user_' . $user['id'] . '_preferred_language');
                if ($preferredLanguage) {
                    Lang::setLocale($preferredLanguage);
                } else {
                    Lang::boot();
                }
                $redirectTarget = Auth::isAdminRole($user['role']) ? '/admin/dashboard.php' : '/dashboard.php';
                Helpers::redirect($redirectTarget);
            } else {
                $errors[] = 'Bilgileriniz doğrulanamadı. Lütfen tekrar deneyin.';
            }
        }
    }
}

Helpers::includeTemplate('auth-header.php');
?>
<style>
    .authero-page {
        min-height: 100vh;
        display: flex;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .authero-container {
        display: flex;
        width: 100%;
        min-height: 100vh;
    }

    .authero-left-panel,
    .authero-right-panel {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .authero-left-panel {
        background: #ffffff;
        padding: 2rem;
    }

    .authero-right-panel {
        color: #ffffff;
        background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), #364352;
        position: relative;
        padding: 2rem;
    }

    .authero-form-container {
        width: 100%;
        max-width: 400px;
    }

    .authero-logo {
        color: #3b82f6;
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 2rem;
    }

    .authero-form-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.5rem;
    }

    .authero-form-subtitle {
        color: #6b7280;
        margin-bottom: 2rem;
    }

    .authero-form-subtitle a {
        color: #3b82f6;
        text-decoration: none;
    }

    .authero-form-group {
        margin-bottom: 1.5rem;
    }

    .authero-form-label {
        display: block;
        margin-bottom: 0.5rem;
        color: #374151;
        font-weight: 500;
    }

    .authero-form-input {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 0.5rem;
        font-size: 1rem;
        transition: border-color 0.2s;
        background: #f9fafb;
    }

    .authero-form-input:focus {
        outline: none;
        border-color: #3b82f6;
        background: #ffffff;
    }

    .authero-form-input::placeholder {
        color: #9ca3af;
    }

    .authero-forgot-password {
        text-align: right;
        margin-top: 0.5rem;
    }

    .authero-forgot-password a {
        color: #3b82f6;
        text-decoration: none;
        font-size: 0.875rem;
    }

    .authero-btn-primary {
        width: 100%;
        padding: 0.875rem;
        background: linear-gradient(135deg, #8b5cf6, #3b82f6);
        color: #ffffff;
        border: none;
        border-radius: 0.5rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s;
        margin-bottom: 1.5rem;
    }

    .authero-btn-primary:hover {
        transform: translateY(-1px);
    }

    .authero-btn-secondary {
        width: 100%;
        padding: 0.875rem;
        background: #ffffff;
        color: #374151;
        border: 2px solid #e5e7eb;
        border-radius: 0.5rem;
        font-size: 1rem;
        cursor: pointer;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: background-color 0.2s;
    }

    .authero-btn-secondary:hover {
        background: #f9fafb;
    }

    .authero-social-icon {
        width: 20px;
        height: 20px;
        background-size: contain;
        background-repeat: no-repeat;
        background-position: center;
    }

    .authero-google-icon {
        background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23EA4335" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="%2334A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="%23FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="%23EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>');
    }

    .authero-facebook-icon {
        background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%231877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>');
    }

    .authero-promo-content {
        text-align: center;
        position: relative;
        z-index: 1;
    }

    .authero-promo-title {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 2rem;
        line-height: 1.2;
    }

    .authero-features {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        max-width: 400px;
        margin: 0 auto;
    }

    .authero-feature-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1rem;
    }

    .authero-feature-icon {
        width: 20px;
        height: 20px;
        background: #3b82f6;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 0.75rem;
    }

    @media (max-width: 768px) {
        .authero-container {
            flex-direction: column;
        }

        .authero-right-panel {
            order: -1;
            min-height: 300px;
        }

        .authero-promo-title {
            font-size: 1.8rem;
        }

        .authero-features {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="authero-page">
    <div class="authero-container">
        <div class="authero-left-panel">
            <div class="authero-form-container">
                <div class="authero-logo">Authero</div>
                <h1 class="authero-form-title">Sign In</h1>
                <p class="authero-form-subtitle">
                    Don't have an account? <a href="/register.php">Sign up</a>
                </p>

                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success mb-3"><?= Helpers::sanitize($flashSuccess) ?></div>
                <?php endif; ?>

                <?php if ($flashWarning): ?>
                    <div class="alert alert-warning mb-3"><?= Helpers::sanitize($flashWarning) ?></div>
                <?php endif; ?>

                <?php if ($errors): ?>
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="authero-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <div class="authero-form-group">
                        <label class="authero-form-label" for="loginEmail">Username</label>
                        <input
                            type="text"
                            id="loginEmail"
                            name="email"
                            class="authero-form-input"
                            placeholder="Enter email to get started"
                            required
                            autofocus
                        >
                    </div>

                    <div class="authero-form-group">
                        <label class="authero-form-label" for="loginPassword">Password</label>
                        <input
                            type="password"
                            id="loginPassword"
                            name="password"
                            class="authero-form-input"
                            placeholder="Enter your password"
                            required
                        >
                        <div class="authero-forgot-password">
                            <a href="/password-reset.php">Forgot Password</a>
                        </div>
                    </div>

                    <button type="submit" class="authero-btn-primary">Sign In</button>
                </form>

                <button type="button" class="authero-btn-secondary">
                    <span class="authero-social-icon authero-google-icon"></span>
                    Sign In with Google
                </button>

                <button type="button" class="authero-btn-secondary">
                    <span class="authero-social-icon authero-facebook-icon"></span>
                    Sign in with Facebook
                </button>
            </div>
        </div>
        <div class="authero-right-panel">
            <div class="authero-promo-content">
                <h2 class="authero-promo-title">Connect with over 12k web pros &amp; craft your site.</h2>
                <div class="authero-features">
                    <div class="authero-feature-item">
                        <div class="authero-feature-icon">✓</div>
                        Commercial License
                    </div>
                    <div class="authero-feature-item">
                        <div class="authero-feature-icon">✓</div>
                        Unlimited Exports
                    </div>
                    <div class="authero-feature-item">
                        <div class="authero-feature-icon">✓</div>
                        120+ Coded Blocks
                    </div>
                    <div class="authero-feature-item">
                        <div class="authero-feature-icon">✓</div>
                        Design Files Included
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php Helpers::includeTemplate('auth-footer.php');
