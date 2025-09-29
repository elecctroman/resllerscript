<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Database;

Auth::requireRoles(array('super_admin', 'admin'));

$pdo = Database::connection();
$pageTitle = 'Aktivite Kayıtları';

$userIdFilter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$actionFilter = isset($_GET['action']) ? trim($_GET['action']) : '';
$fromInput = isset($_GET['from']) ? trim($_GET['from']) : '';
$toInput = isset($_GET['to']) ? trim($_GET['to']) : '';

$conditions = array();
$params = array();

if ($userIdFilter > 0) {
    $conditions[] = 'log.user_id = :user_id';
    $params['user_id'] = $userIdFilter;
}

if ($actionFilter !== '') {
    $conditions[] = 'log.action LIKE :action';
    $params['action'] = '%' . $actionFilter . '%';
}

if ($fromInput !== '' && $fromDate = DateTime::createFromFormat('Y-m-d', $fromInput)) {
    $conditions[] = 'log.created_at >= :from';
    $params['from'] = $fromDate->format('Y-m-d 00:00:00');
}

if ($toInput !== '' && $toDate = DateTime::createFromFormat('Y-m-d', $toInput)) {
    $conditions[] = 'log.created_at <= :to';
    $params['to'] = $toDate->format('Y-m-d 23:59:59');
}

$query = 'SELECT log.*, u.name AS user_name, u.role AS user_role FROM admin_activity_logs log LEFT JOIN users u ON log.user_id = u.id';

if ($conditions) {
    $query .= ' WHERE ' . implode(' AND ', $conditions);
}

$query .= ' ORDER BY log.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$adminUsers = $pdo->query("SELECT id, name FROM users WHERE role IN ('super_admin','admin','finance','support','content') ORDER BY name ASC")->fetchAll();

include __DIR__ . '/../templates/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Denetim Günlüğü</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <label class="form-label">Kullanıcı</label>
                <select name="user_id" class="form-select">
                    <option value="0">Tümü</option>
                    <?php foreach ($adminUsers as $adminUser): ?>
                        <option value="<?= (int)$adminUser['id'] ?>" <?= $userIdFilter === (int)$adminUser['id'] ? 'selected' : '' ?>><?= Helpers::sanitize($adminUser['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label">İşlem</label>
                <input type="text" name="action" class="form-control" placeholder="ör. product.update" value="<?= Helpers::sanitize($actionFilter) ?>">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label">Başlangıç</label>
                <input type="date" name="from" class="form-control" value="<?= Helpers::sanitize($fromInput) ?>">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label">Bitiş</label>
                <input type="date" name="to" class="form-control" value="<?= Helpers::sanitize($toInput) ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Filtrele</button>
                <a href="/admin/activity-logs.php" class="btn btn-outline-secondary">Temizle</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Kullanıcı</th>
                        <th>Rol</th>
                        <th>İşlem</th>
                        <th>Açıklama</th>
                        <th>Hedef</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$logs): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Kayıt bulunamadı.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></td>
                            <td><?= Helpers::sanitize($log['user_name'] ?: 'Sistem') ?></td>
                            <td><?= Helpers::sanitize($log['user_role'] ? Auth::roleLabel($log['user_role']) : '-') ?></td>
                            <td><code><?= Helpers::sanitize($log['action']) ?></code></td>
                            <td><?= Helpers::sanitize($log['description']) ?></td>
                            <td><?= Helpers::sanitize($log['target_type'] ? $log['target_type'] . '#' . (string)$log['target_id'] : '-') ?></td>
                            <td><?= Helpers::sanitize($log['ip_address'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
