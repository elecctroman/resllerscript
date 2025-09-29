<?php
require __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Helpers;
use App\Auth;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

Auth::requirePermission('view_audit_logs');

$pdo = Database::connection();
$roleLabels = Auth::roleLabels();
$errors = [];

$filters = [
    'user_id' => isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0,
    'role' => trim($_GET['role'] ?? ''),
    'action' => trim($_GET['action'] ?? ''),
    'search' => trim($_GET['search'] ?? ''),
    'date_from' => trim($_GET['date_from'] ?? ''),
    'date_to' => trim($_GET['date_to'] ?? ''),
];

if ($filters['role'] && !isset($roleLabels[$filters['role']])) {
    $errors[] = 'Geçersiz rol filtresi seçildi.';
    $filters['role'] = '';
}

$dateFromParam = null;
$dateToParam = null;

if ($filters['date_from']) {
    $from = \DateTime::createFromFormat('Y-m-d', $filters['date_from']);
    if ($from === false) {
        $errors[] = 'Başlangıç tarihi geçersiz.';
        $filters['date_from'] = '';
    } else {
        $dateFromParam = $from->format('Y-m-d 00:00:00');
    }
}

if ($filters['date_to']) {
    $to = \DateTime::createFromFormat('Y-m-d', $filters['date_to']);
    if ($to === false) {
        $errors[] = 'Bitiş tarihi geçersiz.';
        $filters['date_to'] = '';
    } else {
        $dateToParam = $to->format('Y-m-d 23:59:59');
    }
}

$actions = $pdo->query('SELECT DISTINCT action FROM admin_activity_logs ORDER BY action ASC')->fetchAll(\PDO::FETCH_COLUMN) ?: [];
$users = $pdo->query('SELECT id, name, email FROM users ORDER BY name ASC')->fetchAll();

$query = 'SELECT logs.*, u.name AS user_name, u.email FROM admin_activity_logs logs LEFT JOIN users u ON logs.user_id = u.id WHERE 1=1';
$params = [];

if ($filters['user_id'] > 0) {
    $query .= ' AND logs.user_id = :user_id';
    $params['user_id'] = $filters['user_id'];
}

if ($filters['role']) {
    $query .= ' AND logs.user_role = :role';
    $params['role'] = $filters['role'];
}

if ($filters['action']) {
    $query .= ' AND logs.action = :action';
    $params['action'] = $filters['action'];
}

if ($filters['search']) {
    $query .= ' AND (logs.description LIKE :search OR logs.metadata LIKE :search OR logs.action LIKE :search)';
    $params['search'] = '%' . $filters['search'] . '%';
}

if ($dateFromParam) {
    $query .= ' AND logs.created_at >= :date_from';
    $params['date_from'] = $dateFromParam;
}

if ($dateToParam) {
    $query .= ' AND logs.created_at <= :date_to';
    $params['date_to'] = $dateToParam;
}

$query .= ' ORDER BY logs.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$pageTitle = 'Aktivite Kayıtları';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label">Kullanıcı</label>
                        <select name="user_id" class="form-select">
                            <option value="0">Tümü</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int)$user['id'] ?>" <?= $filters['user_id'] === (int)$user['id'] ? 'selected' : '' ?>>
                                    <?= Helpers::sanitize($user['name']) ?> (<?= Helpers::sanitize($user['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">Rol</label>
                        <select name="role" class="form-select">
                            <option value="">Tümü</option>
                            <?php foreach ($roleLabels as $value => $label): ?>
                                <option value="<?= Helpers::sanitize($value) ?>" <?= $filters['role'] === $value ? 'selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">Aksiyon</label>
                        <select name="action" class="form-select">
                            <option value="">Tümü</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?= Helpers::sanitize($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>><?= Helpers::sanitize($action) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">Arama</label>
                        <input type="text" name="search" class="form-control" value="<?= Helpers::sanitize($filters['search']) ?>" placeholder="Açıklama veya meta">
                    </div>
                    <div class="col-sm-6 col-md-1">
                        <label class="form-label">Başlangıç</label>
                        <input type="date" name="date_from" class="form-control" value="<?= Helpers::sanitize($filters['date_from']) ?>">
                    </div>
                    <div class="col-sm-6 col-md-1">
                        <label class="form-label">Bitiş</label>
                        <input type="date" name="date_to" class="form-control" value="<?= Helpers::sanitize($filters['date_to']) ?>">
                    </div>
                    <div class="col-sm-6 col-md-1 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">Filtrele</button>
                        <a href="/admin/activity-logs.php" class="btn btn-outline-secondary" title="Filtreleri temizle">Sıfırla</a>
                    </div>
                </form>
                <?php if ($errors): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Son Kayıtlar</h5>
                <span class="text-muted small">Maksimum 200 kayıt gösterilir.</span>
            </div>
            <div class="card-body">
                <?php if (!$logs): ?>
                    <p class="text-muted mb-0">Filtre kriterlerine uygun kayıt bulunamadı.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Kullanıcı</th>
                                <th>Rol</th>
                                <th>Aksiyon</th>
                                <th>Açıklama</th>
                                <th>Hedef</th>
                                <th>IP</th>
                                <th>Detay</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $metadata = null;
                                if (!empty($log['metadata'])) {
                                    $decoded = json_decode($log['metadata'], true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                        $metadata = $decoded;
                                    } else {
                                        $metadata = $log['metadata'];
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td>
                                        <?php if ($log['user_name']): ?>
                                            <strong><?= Helpers::sanitize($log['user_name']) ?></strong><br>
                                            <small class="text-muted"><?= Helpers::sanitize($log['email']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Silinmiş Kullanıcı</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?= Helpers::sanitize(Auth::roleLabel($log['user_role'])) ?></span></td>
                                    <td><code><?= Helpers::sanitize($log['action']) ?></code></td>
                                    <td><?= Helpers::sanitize($log['description'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($log['target_type']): ?>
                                            <span class="text-muted small"><?= Helpers::sanitize($log['target_type']) ?></span><br>
                                            <?php if (!empty($log['target_id'])): ?>
                                                <span class="fw-semibold">#<?= (int)$log['target_id'] ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="small text-muted"><?= Helpers::sanitize($log['ip_address'] ?? '-') ?></span></td>
                                    <td>
                                        <?php if (is_array($metadata)): ?>
                                            <details>
                                                <summary>Meta</summary>
                                                <pre class="bg-light rounded p-2 small mb-0"><?= Helpers::sanitize(json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                            </details>
                                        <?php elseif ($metadata): ?>
                                            <pre class="bg-light rounded p-2 small mb-0"><?= Helpers::sanitize($metadata) ?></pre>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
