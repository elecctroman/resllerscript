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
$installerPath = __DIR__ . '/install.php';

if (!file_exists($configPath)) {
    if (file_exists($installerPath)) {
        header('Location: /install.php');
        exit;
    }

    include __DIR__ . '/templates/auth-header.php';
    ?>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="text-center mb-4">
                <div class="brand">Bayi Yönetim Sistemi</div>
                <p class="text-muted mt-2">Kurulum tamamlanmadı</p>
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
                <p class="mb-0 small text-muted">Hata detayı: <?= App\Helpers::sanitize($exception->getMessage()) ?></p>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/templates/auth-footer.php';
    exit;
}

App\Lang::boot();

if (!empty($_SESSION['user'])) {
    $freshUser = App\Auth::findUser((int)$_SESSION['user']['id']);
    if ($freshUser) {
        $_SESSION['user'] = $freshUser;
    }

    $preferredLanguage = App\Settings::get('user_' . $_SESSION['user']['id'] . '_preferred_language');
    if ($preferredLanguage) {
        App\Lang::setLocale($preferredLanguage);
    }
}
