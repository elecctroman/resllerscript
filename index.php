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


    include __DIR__ . '/templates/auth-header.php';
    ?>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="text-center mb-4">
                <div class="brand">Bayi Yönetim Sistemi</div>
                <p class="text-muted mt-2">Kuruluma başlamadan önce yapılandırmayı tamamlayın</p>
            </div>
            <div class="alert alert-warning">
                <h5 class="alert-heading">Yapılandırma Gerekli</h5>
                <p class="mb-2">Lütfen <code>config/config.sample.php</code> dosyasını <code>config/config.php</code> olarak
                    kopyalayın ve MySQL bağlantı bilgilerinizi girin.</p>
                <ol class="mb-0 text-start">
                    <li><code>config/config.sample.php</code> dosyasını kopyalayın.</li>
                    <li>Yeni dosyada <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> ve <code>DB_PASSWORD</code>
                        değerlerini güncelleyin.</li>
                    <li>Veritabanınızı oluşturup <code>schema.sql</code> dosyasındaki tabloları içeri aktarın.</li>
                    <li>Ardından bu sayfayı yenileyerek giriş ekranına ulaşın.</li>
                </ol>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/templates/auth-footer.php';
    exit;
}

require $configPath;

use App\Auth;
use App\Helpers;

try {
    App\Database::initialize([
        'host' => DB_HOST,
        'name' => DB_NAME,
        'user' => DB_USER,
        'password' => DB_PASSWORD,
    ]);
} catch (\PDOException $exception) {

    include __DIR__ . '/templates/auth-header.php';
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
    include __DIR__ . '/templates/auth-footer.php';
    exit;
}

if (!empty($_SESSION['user'])) {
    Helpers::redirect('/dashboard.php');
}


$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$identifier || !$password) {
        $errors[] = 'Lütfen kullanıcı adı/e-posta ve şifre alanlarını doldurun.';
    } else {
        $user = Auth::attempt($identifier, $password);
        if ($user) {
            $_SESSION['user'] = $user;
            Helpers::redirect('/dashboard.php');
        } else {
            $errors[] = 'Bilgileriniz doğrulanamadı. Lütfen tekrar deneyin.';
        }
    }
}

include __DIR__ . '/templates/auth-header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="text-center mb-4">
            <div class="brand">Bayi Yönetim Sistemi</div>
            <p class="text-muted mt-2">Yetkili bayiler için profesyonel yönetim paneli</p>
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

        <form method="post" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="email" class="form-label">E-posta Adresi veya Kullanıcı Adı</label>
                <input type="text" class="form-control" id="email" name="email" required placeholder="ornek@bayinetwork.com" value="<?= Helpers::sanitize($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Şifre</label>
                <input type="password" class="form-control" id="password" name="password" required placeholder="Şifreniz">
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="/password-reset.php" class="small">Şifremi Unuttum</a>
                <a href="/register.php" class="small">Yeni Bayilik Başvurusu</a>
            </div>
            <button type="submit" class="btn btn-primary w-100">Panele Giriş Yap</button>
            <div class="text-center mt-3">
                <a href="/admin/" class="small text-muted">Yönetici misiniz? Admin girişine gidin.</a>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/templates/auth-footer.php';
