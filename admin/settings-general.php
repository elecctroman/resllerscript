<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Settings;

if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'demo'], true)) {
    Helpers::redirect('/');
}

$pdo = Database::connection();
$errors = [];
$success = '';
$demoModeEnabled = Settings::get('demo_mode_enabled', '0') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enableDemo = isset($_POST['demo_mode_enabled']);

    if ($enableDemo) {
        try {
            $column = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(\PDO::FETCH_ASSOC);
            if ($column && strpos($column['Type'], "'demo'") === false) {
                $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','reseller','demo') NOT NULL DEFAULT 'reseller'");
            }
        } catch (\PDOException $exception) {
            $errors[] = 'Demo rolü tanımlanırken bir veritabanı hatası oluştu: ' . $exception->getMessage();
        }
    }

    if (!$errors) {
        Settings::set('demo_mode_enabled', $enableDemo ? '1' : '0');
        $demoModeEnabled = $enableDemo;

        if ($enableDemo) {
            Auth::ensureDemoAccount(true);
            $success = 'Demo modu aktifleştirildi. Demo kullanıcı bilgilerini müşterilerinizle paylaşabilirsiniz.';
        } else {
            Auth::ensureDemoAccount(false);
            $success = 'Demo modu devre dışı bırakıldı. Demo kullanıcı giriş yapamaz.';
        }
    }
}

$demoCredentials = [
    'username' => 'demo',
    'email' => 'demo@demo.com',
    'password' => 'demo123!'
];

$pageTitle = 'Genel Ayarlar';

include __DIR__ . '/../templates/header.php';
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-8 col-xxl-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Demo Modu</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Demo modu, ürününüzü potansiyel müşterilere göstermek için hazır bir yönetici hesabı sunar. Bu hesap yalnızca görüntüleme amaçlıdır ve yapılan değişiklikler kaydedilmez.</p>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
                <?php endif; ?>

                <form method="post" class="vstack gap-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="demo-mode" name="demo_mode_enabled" <?= $demoModeEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="demo-mode">Demo modunu aktifleştir</label>
                    </div>

                    <div class="alert alert-info">
                        <h6 class="alert-heading mb-2">Hazır Demo Kullanıcısı</h6>
                        <ul class="mb-2">
                            <li><strong>Kullanıcı adı:</strong> <?= Helpers::sanitize($demoCredentials['username']) ?></li>
                            <li><strong>E-posta:</strong> <?= Helpers::sanitize($demoCredentials['email']) ?></li>
                            <li><strong>Şifre:</strong> <?= Helpers::sanitize($demoCredentials['password']) ?></li>
                        </ul>
                        <p class="mb-0 small">Demo modunu aktifleştirdiğinizde hesap otomatik olarak oluşturulur veya güncellenir.</p>
                    </div>

                    <button type="submit" class="btn btn-primary align-self-start">Ayarları Kaydet</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
