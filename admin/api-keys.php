<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Database;
use App\ApiToken;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if (!Auth::isAdminRole($user['role'])) {
    Helpers::redirect('/dashboard.php');
}

$pageTitle = 'API Anahtarları';
$pdo = Database::connection();
$errors = array();
$success = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($csrf)) {
        $errors[] = 'Oturum doğrulama anahtarınız geçersiz. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $tokenId = isset($_POST['token_id']) ? (int)$_POST['token_id'] : 0;

        if ($tokenId <= 0) {
            $errors[] = 'API anahtarı bulunamadı.';
        } elseif ($action === 'disable') {
            ApiToken::updateStatus($tokenId, 'disabled');
            $success[] = 'API anahtarı devre dışı bırakıldı.';
        } elseif ($action === 'enable') {
            ApiToken::updateStatus($tokenId, 'active');
            $success[] = 'API anahtarı yeniden aktifleştirildi.';
        } elseif ($action === 'rotate_otp') {
            $secret = ApiToken::rotateOtpSecret($tokenId);
            $success[] = 'OTP gizli anahtarı yenilendi: ' . $secret;
        } elseif ($action === 'clear_otp') {
            ApiToken::clearOtpSecret($tokenId);
            $success[] = 'OTP doğrulaması kapatıldı.';
        }
    }
}

$tokensStmt = $pdo->query('SELECT t.*, u.name AS user_name, u.email AS user_email FROM api_tokens t INNER JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC');
$tokens = $tokensStmt ? $tokensStmt->fetchAll() : array();

$statsStmt = $pdo->prepare('SELECT token_id, COUNT(*) AS total, SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) AS failed, MAX(created_at) AS last_request FROM api_request_logs GROUP BY token_id');
$statsStmt->execute();
$stats = array();
foreach ($statsStmt->fetchAll() as $row) {
    $stats[(int)$row['token_id']] = array(
        'total' => (int)$row['total'],
        'failed' => (int)$row['failed'],
        'last_request' => $row['last_request'],
    );
}

$errorLogsStmt = $pdo->prepare('SELECT l.*, t.token, u.name AS user_name, u.email AS user_email FROM api_request_logs l LEFT JOIN api_tokens t ON l.token_id = t.id LEFT JOIN users u ON t.user_id = u.id WHERE l.status_code >= 400 ORDER BY l.created_at DESC LIMIT 50');
$errorLogsStmt->execute();
$errorLogs = $errorLogsStmt->fetchAll();

include __DIR__ . '/../templates/header.php';
?>
<div class="container-fluid py-4">
    <div class="row g-4 mb-4">
        <div class="col-12">
            <?php foreach ($errors as $message): ?>
                <div class="alert alert-danger mb-3"><?= Helpers::sanitize($message) ?></div>
            <?php endforeach; ?>
            <?php foreach ($success as $message): ?>
                <div class="alert alert-success mb-3"><?= Helpers::sanitize($message) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">API Anahtarları</h5>
                        <small class="text-muted">Hangi bayinin hangi anahtarı kullandığını, istek sayılarını ve son aktiviteyi görüntüleyin.</small>
                    </div>
                    <a href="/profile.php" class="btn btn-outline-primary btn-sm">Bayi Görünümü</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Bayi</th>
                                <th>Etiket</th>
                                <th>Durum</th>
                                <th>Yetki</th>
                                <th>Oluşturma</th>
                                <th>Son Kullanım</th>
                                <th>İstek</th>
                                <th>Hata</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$tokens): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">Henüz oluşturulmuş bir API anahtarı bulunmuyor.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tokens as $token): ?>
                                <?php
                                    $tokenId = (int)$token['id'];
                                    $tokenStats = isset($stats[$tokenId]) ? $stats[$tokenId] : array('total' => 0, 'failed' => 0, 'last_request' => null);
                                    $maskedToken = substr($token['token'], 0, 6) . '••••' . substr($token['token'], -4);
                                    $statusBadge = $token['status'] === 'active' ? 'bg-success' : 'bg-danger';
                                    $statusLabel = $token['status'] === 'active' ? 'Aktif' : 'Pasif';
                                    $scope = $token['scopes'] ?: 'full';
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= Helpers::sanitize($token['user_name']) ?></div>
                                        <small class="text-muted"><?= Helpers::sanitize($token['user_email']) ?></small>
                                    </td>
                                    <td>
                                        <div><?= Helpers::sanitize($token['label'] ?: 'Panel API Anahtarı') ?></div>
                                        <code class="small text-muted"><?= Helpers::sanitize($maskedToken) ?></code>
                                    </td>
                                    <td><span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span></td>
                                    <td><?= Helpers::sanitize(strtoupper($scope)) ?></td>
                                    <td><?= $token['created_at'] ? date('d.m.Y H:i', strtotime($token['created_at'])) : '-' ?></td>
                                    <td><?= $token['last_used_at'] ? date('d.m.Y H:i', strtotime($token['last_used_at'])) : '-' ?></td>
                                    <td><?= number_format($tokenStats['total']) ?></td>
                                    <td class="<?= $tokenStats['failed'] > 0 ? 'text-danger' : '' ?>"><?= number_format($tokenStats['failed']) ?></td>
                                    <td>
                                        <form method="post" class="d-flex gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                            <input type="hidden" name="token_id" value="<?= $tokenId ?>">
                                            <?php if ($token['status'] === 'active'): ?>
                                                <input type="hidden" name="action" value="disable">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Bu API anahtarını devre dışı bırakmak istediğinize emin misiniz?');">Pasifleştir</button>
                                            <?php else: ?>
                                                <input type="hidden" name="action" value="enable">
                                                <button type="submit" class="btn btn-outline-success btn-sm">Aktifleştir</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Son Hatalı API İstekleri</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Zaman</th>
                                <th>Bayi</th>
                                <th>IP</th>
                                <th>Endpoint</th>
                                <th>Metot</th>
                                <th>Durum</th>
                                <th>Kullanıcı Aracısı</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$errorLogs): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Herhangi bir hata kaydı bulunmuyor.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($errorLogs as $log): ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></td>
                                    <td>
                                        <?php if (!empty($log['user_name'])): ?>
                                            <div class="fw-semibold"><?= Helpers::sanitize($log['user_name']) ?></div>
                                            <small class="text-muted"><?= Helpers::sanitize($log['user_email']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Anonim</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= Helpers::sanitize($log['ip_address']) ?></td>
                                    <td><code><?= Helpers::sanitize($log['endpoint']) ?></code></td>
                                    <td><?= Helpers::sanitize(strtoupper($log['method'])) ?></td>
                                    <td><span class="badge bg-danger"><?= (int)$log['status_code'] ?></span></td>
                                    <td class="text-break" style="max-width: 320px;"><?= Helpers::sanitize($log['user_agent'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
