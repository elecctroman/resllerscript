<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Helpers;
use App\Database;
use App\Services\PackageOrderService;

Auth::requireRoles(array('super_admin', 'admin', 'support'));

$currentUser = $_SESSION['user'];
$pdo = Database::connection();
$errors = Helpers::getFlash('errors', array());
$success = Helpers::getFlash('success', '');
$currentUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/admin/orders.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectPath = Helpers::normalizeRedirectPath(isset($_POST['redirect']) ? $_POST['redirect'] : '', '/admin/orders.php');
    $formErrors = array();

    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $newStatus = isset($_POST['status']) ? $_POST['status'] : '';
    $statusNote = isset($_POST['status_note']) ? trim($_POST['status_note']) : '';

    $order = PackageOrderService::loadOrder($orderId);

    if (!$order) {
        $formErrors[] = 'Sipariş bulunamadı.';
    } elseif (!in_array($newStatus, array('pending', 'paid', 'completed', 'cancelled'), true)) {
        $formErrors[] = 'Geçersiz durum seçildi.';
    } else {
        $pdo->prepare('UPDATE package_orders SET status = :status, admin_note = :admin_note, updated_at = NOW() WHERE id = :id')->execute(array(
            'status' => $newStatus,
            'admin_note' => $statusNote !== '' ? $statusNote : null,
            'id' => $orderId,
        ));

        if ($newStatus === 'completed') {
            PackageOrderService::fulfill($order);
            PackageOrderService::markCompleted($orderId, $order);
        } elseif ($newStatus === 'paid') {
            $pdo->prepare('UPDATE package_orders SET payment_provider = :provider WHERE id = :id AND payment_provider IS NULL')
                ->execute(array(
                    'provider' => 'cryptomus',
                    'id' => $orderId,
                ));
        }

        AuditLog::record(
            $currentUser['id'],
            'package_order.status_change',
            'package_order',
            $orderId,
            sprintf('Sipariş #%d durumu %s -> %s olarak güncellendi', $orderId, $order['status'], $newStatus)
        );

        Helpers::redirectWithFlash($redirectPath, array('success' => 'Sipariş durumu güncellendi.'));
    }

    if ($formErrors) {
        Helpers::redirectWithFlash($redirectPath, array('errors' => $formErrors));
    }
}

$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$query = 'SELECT po.*, p.name AS package_name FROM package_orders po INNER JOIN packages p ON po.package_id = p.id';
$params = [];

if ($statusFilter && in_array($statusFilter, ['pending', 'paid', 'completed', 'cancelled'], true)) {
    $query .= ' WHERE po.status = :status';
    $params['status'] = $statusFilter;
}

$query .= ' ORDER BY po.created_at DESC';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Paket Siparişleri';
include __DIR__ . '/../templates/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Paket Siparişleri</h5>
        <form method="get" class="d-flex gap-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Tümü</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Beklemede</option>
                <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Ödeme Alındı</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Tamamlandı</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>İptal</option>
            </select>
        </form>
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

        <?php if ($success): ?>
            <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Bayi</th>
                    <th>Paket</th>
                    <th>Tutar</th>
                    <th>Durum</th>
                    <th>Oluşturma</th>
                    <th class="text-end">İşlemler</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= (int)$order['id'] ?></td>
                        <td>
                            <strong><?= Helpers::sanitize($order['name']) ?></strong><br>
                            <small class="text-muted"><?= Helpers::sanitize($order['email']) ?></small>
                        </td>
                        <td><?= Helpers::sanitize($order['package_name']) ?></td>
                        <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$order['total_amount'])) ?></td>
                        <td><span class="badge-status <?= Helpers::sanitize($order['status']) ?>"><?= strtoupper(Helpers::sanitize($order['status'])) ?></span></td>
                        <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#orderDetail<?= (int)$order['id'] ?>">Detay</button>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#orderStatus<?= (int)$order['id'] ?>">Durum Değiştir</button>
                        </td>
                    </tr>

                    <div class="modal fade" id="orderDetail<?= (int)$order['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Sipariş Detayı</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <dl class="row">
                                        <dt class="col-sm-4">Bayi Bilgisi</dt>
                                        <dd class="col-sm-8">
                                            <?= Helpers::sanitize($order['name']) ?><br>
                                            <?= Helpers::sanitize($order['email']) ?><br>
                                            <?= Helpers::sanitize(isset($order['phone']) ? $order['phone'] : '-') ?>
                                        </dd>
                                        <dt class="col-sm-4">Paket</dt>
                                        <dd class="col-sm-8"><?= Helpers::sanitize($order['package_name']) ?></dd>
                                        <dt class="col-sm-4">Notlar</dt>
                                        <dd class="col-sm-8"><?= nl2br(Helpers::sanitize(isset($order['notes']) ? $order['notes'] : '-')) ?></dd>
                                        <dt class="col-sm-4">Yönetici Notu</dt>
                                        <dd class="col-sm-8"><?= nl2br(Helpers::sanitize(isset($order['admin_note']) ? $order['admin_note'] : '-')) ?></dd>
                                        <dt class="col-sm-4">Ek Form Verisi</dt>
                                        <dd class="col-sm-8"><pre class="bg-light p-3 rounded small"><?= Helpers::sanitize(isset($order['form_data']) ? $order['form_data'] : '{}') ?></pre></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="orderStatus<?= (int)$order['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="post">
                                    <input type="hidden" name="redirect" value="<?= Helpers::sanitize($currentUrl) ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Sipariş Durumu</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Durum Seçin</label>
                                                <select name="status" class="form-select" required>
                                                <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Beklemede</option>
                                                <option value="paid" <?= $order['status'] === 'paid' ? 'selected' : '' ?>>Ödeme Alındı</option>
                                                <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Tamamlandı</option>
                                                <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>İptal</option>
                                            </select>
                                        </div>
                                            <p class="text-muted small">"Tamamlandı" seçeneği ile birlikte bayi hesabı oluşturulur ve giriş bilgileri otomatik olarak e-posta ile gönderilir.</p>
                                            <div class="mb-3">
                                                <label class="form-label">Yönetici Notu</label>
                                                <textarea name="status_note" class="form-control" rows="3" placeholder="Siparişe ilişkin notunuzu girin."><?= Helpers::sanitize(isset($order['admin_note']) ? $order['admin_note'] : '') ?></textarea>
                                            </div>
                                        </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                        <button type="submit" class="btn btn-primary">Güncelle</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
