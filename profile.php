<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\ApiToken;
use App\Database;
use App\Helpers;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

$pdo = Database::connection();
$errors = array();
$successMessages = array();
$displayToken = '';

$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
$baseUrl = $host ? $scheme . $host : '';
$apiBaseUrl = $baseUrl ? rtrim($baseUrl, '/') . '/api/v1' : '/api/v1';
$smmEndpoint = $baseUrl ? rtrim($baseUrl, '/') . '/api/v2' : '/api/v2';
$legacySmmEndpoint = $baseUrl ? rtrim($baseUrl, '/') . '/api/smm.php' : '/api/smm.php';

try {
    $activeToken = ApiToken::getOrCreateForUser($user['id']);
} catch (\Throwable $exception) {
    $activeToken = null;
    $errors[] = 'API anahtarınıza erişilirken bir sorun oluştu: ' . $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'profile';
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($csrfToken)) {
        $errors[] = 'Oturum doğrulama anahtarınız geçersiz. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        if ($action === 'profile') {
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
            $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
            $newPasswordConfirm = isset($_POST['new_password_confirmation']) ? $_POST['new_password_confirmation'] : '';

            if ($name === '' || $email === '') {
                $errors[] = 'Ad ve e-posta alanları zorunludur.';
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Geçerli bir e-posta adresi giriniz.';
            }

            $changingPassword = $newPassword !== '' || $newPasswordConfirm !== '';

            if ($changingPassword) {
                if ($currentPassword === '') {
                    $errors[] = 'Şifrenizi değiştirmek için mevcut şifrenizi girmeniz gerekir.';
                }

                if ($newPassword === '' || $newPasswordConfirm === '') {
                    $errors[] = 'Yeni şifre alanları boş bırakılamaz.';
                }

                if ($newPassword !== '' && $newPasswordConfirm !== '' && $newPassword !== $newPasswordConfirm) {
                    $errors[] = 'Yeni şifre alanları birbiriyle eşleşmiyor.';
                }

                if ($newPassword !== '' && strlen($newPassword) < 8) {
                    $errors[] = 'Yeni şifre en az 8 karakter olmalıdır.';
                }
            }

            if (!$errors) {
                try {
                    $pdo->beginTransaction();

                    $duplicateStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
                    $duplicateStmt->execute(array('email' => $email, 'id' => $user['id']));
                    if ($duplicateStmt->fetch()) {
                        $errors[] = 'Bu e-posta adresi başka bir hesap tarafından kullanılıyor.';
                    } else {
                        if ($changingPassword) {
                            $passwordStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
                            $passwordStmt->execute(array('id' => $user['id']));
                            $passwordRow = $passwordStmt->fetch();

                            if (!$passwordRow || !password_verify($currentPassword, $passwordRow['password_hash'])) {
                                $errors[] = 'Mevcut şifreniz doğrulanamadı.';
                            }
                        }

                        if (!$errors) {
                            $pdo->prepare('UPDATE users SET name = :name, email = :email, updated_at = NOW() WHERE id = :id')->execute(array(
                                'name' => $name,
                                'email' => $email,
                                'id' => $user['id'],
                            ));

                            if ($changingPassword) {
                                $pdo->prepare('UPDATE users SET password_hash = :password WHERE id = :id')->execute(array(
                                    'password' => password_hash($newPassword, PASSWORD_BCRYPT),
                                    'id' => $user['id'],
                                ));
                            }

                            $pdo->commit();

                            $freshUser = Auth::findUser($user['id']);
                            if ($freshUser) {
                                $_SESSION['user'] = $freshUser;
                                $user = $freshUser;
                            }

                            $successMessages[] = 'Profil bilgileriniz güncellendi.';

                            if ($changingPassword) {
                                $successMessages[] = 'Şifreniz başarıyla değiştirildi.';
                            }
                        }
                    }

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                } catch (\PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Profiliniz güncellenirken bir hata oluştu: ' . $exception->getMessage();
                }
            }
        } elseif ($action === 'webhook' && $activeToken) {
            $webhookUrl = isset($_POST['webhook_url']) ? trim($_POST['webhook_url']) : '';

            if ($webhookUrl !== '' && !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'Geçerli bir webhook adresi giriniz.';
            }

            if (!$errors) {
                ApiToken::updateWebhook((int)$activeToken['id'], $webhookUrl !== '' ? $webhookUrl : null);
                $successMessages[] = 'Webhook adresiniz güncellendi.';
                if ($activeToken) {
                    $activeToken['webhook_url'] = $webhookUrl !== '' ? $webhookUrl : null;
                }
            }
        } elseif ($action === 'regenerate_token') {
            try {
                $newToken = ApiToken::regenerateForUser($user['id']);
                $displayToken = $newToken['token'];
                $successMessages[] = 'Yeni API anahtarınız oluşturuldu. Lütfen güvenli bir yerde saklayın.';
                $activeToken = array(
                    'id' => $newToken['id'],
                    'user_id' => $user['id'],
                    'token' => $newToken['token'],
                    'label' => 'WooCommerce Entegrasyonu',
                    'webhook_url' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'last_used_at' => null,
                );
            } catch (\Throwable $exception) {
                $errors[] = 'API anahtarınız yenilenirken bir sorun oluştu: ' . $exception->getMessage();
            }
        }
    }
}

$pageTitle = 'Profilim';

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Profil Bilgileri</h5>
                <small class="text-muted">Bayi iletişim ve şifre ayarlarınızı güncelleyin.</small>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($successMessages): ?>
                    <div class="alert alert-success">
                        <ul class="mb-0">
                            <?php foreach ($successMessages as $message): ?>
                                <li><?= Helpers::sanitize($message) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="mb-4">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <input type="hidden" name="action" value="profile">
                    <div class="mb-3">
                        <label class="form-label">Ad Soyad</label>
                        <input type="text" name="name" class="form-control" value="<?= Helpers::sanitize($user['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-posta</label>
                        <input type="email" name="email" class="form-control" value="<?= Helpers::sanitize($user['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mevcut Şifre</label>
                        <input type="password" name="current_password" class="form-control" placeholder="Şifrenizi değiştirmek için doldurun">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Yeni Şifre</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Yeni şifreniz">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Yeni Şifre (Tekrar)</label>
                            <input type="password" name="new_password_confirmation" class="form-control" placeholder="Yeni şifre tekrarı">
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Profili Kaydet</button>
                    </div>
                </form>

                <dl class="row mb-0">
                    <dt class="col-sm-4">Üyelik Başlangıcı</dt>
                    <dd class="col-sm-8"><?= isset($user['created_at']) ? date('d.m.Y H:i', strtotime($user['created_at'])) : '-' ?></dd>
                    <dt class="col-sm-4">Durum</dt>
                    <dd class="col-sm-8"><span class="badge bg-success">Aktif</span></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">WooCommerce API Erişimi</h5>
                    <small class="text-muted">WordPress eklentisini bu bilgilerle yapılandırın.</small>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <input type="hidden" name="action" value="regenerate_token">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Yeni bir API anahtarı oluşturmak istediğinize emin misiniz? Mevcut anahtar kullanım dışı kalacaktır.');">Anahtarı Yenile</button>
                </form>
            </div>
            <div class="card-body">
                <?php if ($displayToken !== ''): ?>
                    <div class="alert alert-warning">
                        <strong>Yeni API Anahtarı:</strong>
                        <div class="mt-2"><code><?= Helpers::sanitize($displayToken) ?></code></div>
                        <p class="mb-0 small text-muted">Bu anahtar yalnızca bir kez gösterilir. Lütfen güvenli bir yerde saklayın.</p>
                    </div>
                <?php endif; ?>

                <?php if ($activeToken): ?>
                    <div class="mb-4">
                        <label class="form-label">Aktif API Anahtarı</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= Helpers::sanitize($activeToken['token']) ?>" readonly>
                            <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= Helpers::sanitize($activeToken['token']) ?>'); this.textContent='Kopyalandı'; setTimeout(()=>{this.textContent='Kopyala';},2000);">Kopyala</button>
                        </div>
                        <?php if (!empty($activeToken['last_used_at'])): ?>
                            <small class="text-muted d-block mt-2">Son kullanım: <?= date('d.m.Y H:i', strtotime($activeToken['last_used_at'])) ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Webhook Adresi</label>
                        <form method="post" class="d-flex gap-2 flex-column flex-lg-row">
                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                            <input type="hidden" name="action" value="webhook">
                            <input type="url" name="webhook_url" class="form-control" placeholder="https://ornek.com/wp-json/..." value="<?= Helpers::sanitize(isset($activeToken['webhook_url']) ? $activeToken['webhook_url'] : '') ?>">
                            <button type="submit" class="btn btn-outline-primary">Kaydet</button>
                        </form>
                        <small class="text-muted">Sistemimiz sipariş durumu değişikliklerini bu adrese iletir.</small>
                    </div>

                    <div class="border rounded p-3 bg-light">
                        <h6>WordPress Eklenti Ayarları</h6>
                        <ul class="small mb-0">
                            <li>API URL: <code><?= Helpers::sanitize($apiBaseUrl) ?></code></li>
                            <li>API Anahtarı: Yukarıdaki anahtarı kullanın.</li>
                            <li>Webhook URL: WordPress sitenizin <code>/wp-json/reseller-sync/v1/order-status</code> adresi.</li>
                        </ul>
                    </div>
                    <div class="border rounded p-3 bg-light mt-3">
                        <h6 class="mb-2">SMM Panel API Kullanımı</h6>
                        <ul class="small mb-0">
                            <li>Uç Nokta: <code><?= Helpers::sanitize($smmEndpoint) ?></code></li>
                            <li class="text-muted small">Eski uyumluluk noktası: <code><?= Helpers::sanitize($legacySmmEndpoint) ?></code></li>
                            <li>Kimlik Doğrulama: İsteklerinize <code>key</code> parametresi ile bu API anahtarını ekleyin.</li>
                            <li>İşlemler: <code>action=services</code>, <code>action=add</code>, <code>action=status</code>, <code>action=balance</code>.</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">API anahtarınız oluşturulamadı. Lütfen daha sonra tekrar deneyin veya destek ekibine ulaşın.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';
