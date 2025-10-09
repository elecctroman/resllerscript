<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Helpers;

$activeUser = Auth::currentUser();
if ($activeUser) {
    $redirectTarget = Auth::isAdminRole($activeUser['role']) ? '/admin/dashboard.php' : '/dashboard.php';
    Helpers::redirect($redirectTarget);
}

$errors = [];
$successMessage = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $reset = Auth::validateResetToken($token);

    if (!$reset) {
        $errors[] = 'Bu sıfırlama bağlantısı geçersiz veya süresi dolmuş.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $passwordConfirmation = isset($_POST['password_confirmation']) ? $_POST['password_confirmation'] : '';

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

    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authero - Şifre Sıfırlama</title>
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
            
            .alert-danger {
                background: #fee2e2;
                border-color: #dc2626;
                color: #991b1b;
            }
            
            .alert ul {
                margin: 0;
                padding-left: 1rem;
            }
            
            .text-center {
                text-align: center;
            }
            
            .link {
                color: #6b7280;
                font-size: 0.875rem;
                text-decoration: none;
                margin-top: 1rem;
                display: inline-block;
            }
            
            .link:hover {
                color: #3b82f6;
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
                    
                    <h1 class="form-title">Şifre Sıfırlama</h1>
                    <p class="form-subtitle">Yeni şifrenizi belirleyin.</p>

                    <?php if ($successMessage): ?>
                        <div class="alert alert-success"><?= Helpers::sanitize($successMessage) ?></div>
                        <a href="index.php" class="btn-primary" style="text-decoration: none; text-align: center; display: block;">Girişe Dön</a>
                    <?php else: ?>
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
                            <div class="form-group">
                                <label class="form-label">Yeni Şifre</label>
                                <input type="password" name="password" class="form-input" placeholder="En az 8 karakter" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Yeni Şifre (Tekrar)</label>
                                <input type="password" name="password_confirmation" class="form-input" placeholder="Şifrenizi tekrar girin" required>
                            </div>
                            <button type="submit" class="btn-primary">Şifremi Güncelle</button>
                            <div class="text-center">
                                <a href="index.php" class="link">Giriş sayfasına dön</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="right-panel">
                <div class="promo-content">
                    <h2 class="promo-title">Güvenli Şifre Sıfırlama</h2>
                    <div class="features">
                        <div class="feature-item">
                            <div class="feature-icon"></div>
                            Güvenli Bağlantı
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon"></div>
                            Hızlı İşlem
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon"></div>
                            Telegram Bildirimi
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon"></div>
                            Anında Erişim
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if ($email === '') {
        $errors[] = 'Lütfen e-posta adresinizi girin.';
    }

    if (!$errors) {
        $user = Auth::findUserByEmail($email);

        if (!$user) {
            $errors[] = 'Bu e-posta adresiyle kayıtlı bir hesap bulunamadı.';
        } elseif (empty($user['telegram_bot_token']) || empty($user['telegram_chat_id'])) {
            $errors[] = 'Hesabınız için Telegram bildirimi tanımlı olmadığı için sıfırlama bağlantısı gönderilemedi. Lütfen yönetici ile iletişime geçin.';
        } else {
            $token = Auth::createPasswordReset($email);
            $resetLink = Helpers::url('password-reset.php?token=' . urlencode($token), true);
            Auth::sendResetLink($email, $token, $resetLink);
            $successMessage = 'Şifre sıfırlama bağlantısı Telegram üzerinden gönderildi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authero - Şifremi Unuttum</title>
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
        
        .alert-danger {
            background: #fee2e2;
            border-color: #dc2626;
            color: #991b1b;
        }
        
        .alert ul {
            margin: 0;
            padding-left: 1rem;
        }
        
        .info-text {
            background: #f0f9ff;
            border: 1.5px solid #0ea5e9;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #0369a1;
            font-size: 0.875rem;
        }
        
        .text-center {
            text-align: center;
        }
        
        .link {
            color: #6b7280;
            font-size: 0.875rem;
            text-decoration: none;
            margin-top: 1rem;
            display: inline-block;
        }
        
        .link:hover {
            color: #3b82f6;
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
                
                <h1 class="form-title">Şifremi Unuttum</h1>
                <p class="form-subtitle">
                    Şifrenizi hatırladınız mı? <a href="index.php" style="color: #3b82f6; text-decoration: none;">Giriş Yapın</a>
                </p>
                
                <div class="info-text">
                    E-posta adresinizi girin, şifre sıfırlama bağlantısını Telegram üzerinden size göndereceğiz.
                </div>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($successMessage) ?></div>
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
                    <div class="form-group">
                        <label class="form-label">E-posta Adresiniz</label>
                        <input 
                            type="email" 
                            class="form-input" 
                            name="email" 
                            value="<?= Helpers::sanitize(isset($_POST['email']) ? $_POST['email'] : '') ?>" 
                            placeholder="ornek@bayi.com"
                            required
                        >
                    </div>
                    <button type="submit" class="btn-primary">Sıfırlama Bağlantısı Gönder</button>
                    <div class="text-center">
                        <a href="index.php" class="link">Giriş sayfasına dön</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="right-panel">
            <div class="promo-content">
                <h2 class="promo-title">Güvenli Şifre Sıfırlama</h2>
                <div class="features">
                    <div class="feature-item">
                        <div class="feature-icon"></div>
                        Güvenli Bağlantı
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"></div>
                        Hızlı İşlem
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"></div>
                        Telegram Bildirimi
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"></div>
                        Anında Erişim
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
