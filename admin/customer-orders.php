<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

Auth::requireRoles(array('super_admin', 'admin', 'support', 'finance'));
$pdo = Database::connection();
$errors = array();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Güvenlik doğrulaması başarısız oldu.';
    } else {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $status = $_POST['status'] ?? 'bekliyor';
        $license = trim((string)($_POST['license_key'] ?? ''));
        if ($orderId <= 0) {
            $errors[] = 'Geçersiz sipariş.';
        }
        if (!in_array($status, array('bekliyor', 'onaylandi', 'iptal'), true)) {
            $errors[] = 'Geçersiz durum seçimi.';
        }
        if (!$errors) {
            $stmt = $pdo->prepare('UPDATE customer_orders SET status = :status, license_key = :license, updated_at = NOW() WHERE id = :id');
            $stmt->execute(array(':status' => $status, ':license' => $license !== '' ? $license : null, ':id' => $orderId));
            $success = 'Sipariş güncellendi.';
        }
    }
}

$orders = $pdo->query('SELECT co.*, c.email, p.name AS product_name FROM customer_orders co INNER JOIN customers c ON c.id = co.customer_id INNER JOIN products p ON p.id = co.product_id ORDER BY co.created_at DESC LIMIT 200')->fetchAll();
$pageTitle = 'Müşteri Siparişleri';
require __DIR__ . '/../templates/header.php';
?>
<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Müşteri Siparişleri</h5></div>
    <div class="card-body">
        <?php if ($success): ?>
            <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
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
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Müşteri</th>
                        <th>Ürün</th>
                        <th>Adet</th>
                        <th>Tutar</th>
                        <th>Durum</th>
                        <th>Oluşturulma</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?= (int)$order['id'] ?></td>
                        <td><?= Helpers::sanitize($order['email']) ?></td>
                        <td><?= Helpers::sanitize($order['product_name']) ?></td>
                        <td><?= (int)$order['quantity'] ?></td>
                        <td><?= number_format((float)$order['total_price'], 2, ',', '.') ?></td>
                        <td><span class="badge text-bg-secondary text-capitalize"><?= Helpers::sanitize($order['status']) ?></span></td>
                        <td><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($order['created_at']))) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#orderModal" data-order='<?= json_encode($order, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>Düzenle</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sipariş Güncelle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                <input type="hidden" name="order_id" id="orderIdField">
                <div class="mb-3">
                    <label class="form-label">Durum</label>
                    <select class="form-select" name="status" id="orderStatusField">
                        <option value="bekliyor">Bekliyor</option>
                        <option value="onaylandi">Onaylandı</option>
                        <option value="iptal">İptal</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Teslimat İçeriği</label>
                    <textarea class="form-control" name="license_key" id="orderLicenseField" rows="6"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>
<script>
    const orderModal = document.getElementById('orderModal');
    if (orderModal) {
        orderModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) { return; }
            const order = JSON.parse(button.getAttribute('data-order'));
            document.getElementById('orderIdField').value = order.id;
            document.getElementById('orderStatusField').value = order.status;
            document.getElementById('orderLicenseField').value = order.license_key || '';
        });
    }
</script>
<?php require __DIR__ . '/../templates/footer.php';
