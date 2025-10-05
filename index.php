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

        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= Helpers::sanitize($flashSuccess) ?></div>
        <?php endif; ?>

        <?php if ($flashWarning): ?>
            <div class="alert alert-warning"><?= Helpers::sanitize($flashWarning) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= Helpers::sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="vstack gap-3">
            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
            <div>
                <label class="form-label" for="loginEmail">Kullanıcı adı veya e-posta</label>
                <input type="text" class="form-control" id="loginEmail" name="email" placeholder="ornek@bayi.com" required autofocus>
            </div>
            <div>
                <label class="form-label" for="loginPassword">Şifre</label>
                <input type="password" class="form-control" id="loginPassword" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
        </form>


        </div>
    </div>
</div>
<?php Helpers::includeTemplate('auth-footer.php');
