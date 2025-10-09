<?php
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$normalized = rtrim($requestUri, '/');

if (stripos($requestUri, '/api/') === 0 || $normalized === '/api' || $normalized === '/api/v1') {
    require __DIR__ . '/api/index.php';
    return;
}

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
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authero - Yapılandırma Gerekli</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem;
            }
            
            .auth-card {
                background: white;
                border-radius: 1rem;
                padding: 2rem;
                max-width: 500px;
                width: 100%;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            }
            
            .brand {
                color: #3b82f6;
                font-size: 2rem;
                font-weight: bold;
                margin-bottom: 1rem;
            }
            
            .alert {
                padding: 1rem;
                border-radius: 0.5rem;
                margin-bottom: 1rem;
            }
            
            .alert-warning {
                background: #fef3c7;
                border: 1px solid #d97706;
                color: #92400e;
            }
            
            code {
                background: #f3f4f6;
                padding: 0.2rem 0.4rem;
                border-radius: 0.25rem;
                font-size: 0.875rem;
            }
        </style>
    </head>
    <body>
        <div class="auth-card">
            <div class="text-center mb-4">
                <div class="brand">Authero</div>
                <p style="color: #6b7280; margin-top: 0.5rem;">Kuruluma başlamadan önce yapılandırmayı tamamlayın</p>
            </div>
            <div class="alert alert-warning">
                <h5 style="margin-bottom: 0.5rem; color: #92400e;">Yapılandırma Gerekli</h5>
                <p style="margin-bottom: 0.5rem;">Lütfen <code>config/config.sample.php</code> dosyasını <code>config/config.php</code> olarak kopyalayın ve MySQL bağlantı bilgilerinizi girin.</p>
                <ol style="margin-bottom: 0; padding-left: 1.5rem;">
                    <li><code>config/config.sample.php</code> dosyasını kopyalayın.</li>
                    <li>Yeni dosyada <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> ve <code>DB_PASSWORD</code> değerlerini güncelleyin.</li>
                    <li>Veritabanınızı oluşturup <code>schema.sql</code> dosyasındaki tabloları içeri aktarın.</li>
                    <li>Ardından bu sayfayı yenileyerek giriş ekranına ulaşın.</li>
                </ol>
            </div>
        </div>
    </body>
    </html>
    <?php
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
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authero - Veritabanı Hatası</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem;
            }
            
            .auth-card {
                background: white;
                border-radius: 1rem;
                padding: 2rem;
                max-width: 500px;
                width: 100%;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            }
            
            .brand {
                color: #3b82f6;
                font-size: 2rem;
                font-weight: bold;
                margin-bottom: 1rem;
            }
            
            .alert {
                padding: 1rem;
                border-radius: 0.5rem;
                margin-bottom: 1rem;
            }
            
            .alert-danger {
                background: #fee2e2;
                border: 1px solid #dc2626;
                color: #991b1b;
            }
            
            code {
                background: #f3f4f6;
                padding: 0.2rem 0.4rem;
                border-radius: 0.25rem;
                font-size: 0.875rem;
            }
        </style>
    </head>
    <body>
        <div class="auth-card">
            <div class="text-center mb-4">
                <div class="brand">Authero</div>
                <p style="color: #6b7280; margin-top: 0.5rem;">Veritabanı bağlantısı kurulamadı</p>
            </div>
            <div class="alert alert-danger">
                <h5 style="margin-bottom: 0.5rem; color: #991b1b;">Bağlantı Hatası</h5>
                <p style="margin-bottom: 0.5rem;">Lütfen <code>config/config.php</code> dosyanızdaki MySQL bilgilerini kontrol edin ve veritabanı sunucunuzu doğrulayın.</p>
                <p style="margin-bottom: 0; font-size: 0.875rem; color: #6b7280;">Hata detayı: <?= Helpers::sanitize($exception->getMessage()) ?></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

Lang::boot();

$siteName = Helpers::siteName();
$siteTagline = Helpers::siteTagline();

if (!empty($_SESSION['user'])) {
    $redirectTarget = Auth::isAdminRole($_SESSION['user']['role']) ? '/admin/dashboard.php' : '/account/index.php';
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
                $redirectTarget = Auth::isAdminRole($user['role']) ? '/admin/dashboard.php' : '/account/index.php';
                Helpers::redirect($redirectTarget);
            } else {
                $errors[] = 'Bilgileriniz doğrulanamadı. Lütfen tekrar deneyin.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authero - Giriş Yap</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
        }
        
        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        .left-panel {
            flex: 1;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .right-panel {
            flex: 1;
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), #364352;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
        }
        
        .form-container {
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            color: #3b82f6;
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 2rem;
        }
        
        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .form-subtitle {
            color: #6b7280;
            margin-bottom: 2rem;
        }
        
        .form-subtitle a {
            color: #3b82f6;
            text-decoration: none;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.2s;
            background: #f9fafb;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
        }
        
        .form-input::placeholder {
            color: #9ca3af;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: 0.5rem;
        }
        
        .forgot-password a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #8b5cf6, #3b82f6);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            margin-bottom: 1.5rem;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            width: 100%;
            padding: 0.875rem;
            background: white;
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
        
        .btn-secondary:hover {
            background: #f9fafb;
        }
        
        .google-icon {
            width: 20px;
            height: 20px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23EA4335" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="%2334A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="%23FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="%23EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>') no-repeat center;
            background-size: contain;
        }
        
        .facebook-icon {
            width: 20px;
            height: 20px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%231877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>') no-repeat center;
            background-size: contain;
        }
        
        .promo-content {
            text-align: center;
            z-index: 1;
            position: relative;
        }
        
        .promo-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 2rem;
            line-height: 1.2;
        }
        
        .features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }
        
        .feature-icon {
            width: 20px;
            height: 20px;
            background: #3b82f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .feature-icon::after {
            content: '✓';
            color: white;
            font-size: 0.75rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid;
        }
        
        .alert-success {
            background: #dcfce7;
            border-color: #16a34a;
            color: #166534;
        }
        
        .alert-warning {
            background: #fef3c7;
            border-color: #d97706;
            color: #92400e;
        }
        
        .alert-danger {
            background: #fee2e2;
            border-color: #dc2626;
            color: #991b1b;
        }
        
        .alert ul {
            margin: 0;
            padding-left: 1rem;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .right-panel {
                order: -1;
                min-height: 300px;
            }
            
            .promo-title {
                font-size: 1.8rem;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="form-container">
                <div class="logo">Authero</div>
                
                <h1 class="form-title">Giriş Yap</h1>
                <p class="form-subtitle">
                    Hesabınız yok mu? <a href="register.php">Kayıt Olun</a>
                </p>
                
                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($flashSuccess) ?></div>
                <?php endif; ?>

                <?php if ($flashWarning): ?>
                    <div class="alert alert-warning"><?= Helpers::sanitize($flashWarning) ?></div>
                <?php endif; ?>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="loginEmail">Kullanıcı adı veya e-posta</label>
                        <input 
                            type="text" 
                            id="loginEmail" 
                            name="email" 
                            class="form-input" 
                            placeholder="ornek@bayi.com"
                            value="<?= isset($_POST['email']) ? Helpers::sanitize($_POST['email']) : '' ?>"
                            required 
                            autofocus
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="loginPassword">Şifre</label>
                        <input 
                            type="password" 
                            id="loginPassword" 
                            name="password" 
                            class="form-input" 
                            placeholder="••••••••"
                            required
                        >
                        <div class="forgot-password">
                            <a href="forgot-password.php">Şifremi Unuttum</a>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">Giriş Yap</button>
                </form>
                
                <!-- OAuth butonları (isteğe bağlı) -->
                <!--
                <button type="button" class="btn-secondary">
                    <div class="google-icon"></div>
                    Google ile Giriş Yap
                </button>
                
                <button type="button" class="btn-secondary">
                    <div class="facebook-icon"></div>
                    Facebook ile Giriş Yap
                </button>
                -->
            </div>
        </div>
        
        <div class="right-panel">
            <div class="promo-content">
                <h2 class="promo-title"><?= Helpers::sanitize($siteName ?: 'Bayi Yönetim Sistemi') ?></h2>
                <div class="features">
                    <div class="feature-item">
                        <div class="feature-icon"></div>
                        Güvenli Giriş
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"></div>
                        Kolay Yönetim
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"></div>
                        Hızlı İşlemler
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"></div>
                        7/24 Destek
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
