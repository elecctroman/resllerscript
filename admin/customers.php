<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Customers\CustomerRepository;
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
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($customerId <= 0) {
            $errors[] = 'Geçersiz müşteri seçimi.';
        } else {
            if ($action === 'balance') {
                $amount = (float)($_POST['amount'] ?? 0);
                $type = $_POST['type'] === 'ekleme' ? 'ekleme' : 'cikarma';
                if ($amount <= 0) {
                    $errors[] = 'Geçerli bir tutar giriniz.';
                }
                if (!$errors) {
                    if ($type === 'cikarma') {
                        $amount = -1 * $amount;
                    }
                    CustomerRepository::adjustBalance($customerId, $amount, $type, 'Yönetici işlemi', 'admin-adjust');
                    $success = 'Müşteri bakiyesi güncellendi.';
                }
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
                $stmt->execute(array(':id' => $customerId));
                $success = 'Müşteri kaydı silindi.';
            }
        }
    }
}

$customers = CustomerRepository::list(200, 0);
$apiLogs = $pdo->query('SELECT l.*, c.email FROM customer_api_logs l LEFT JOIN customers c ON c.id = l.customer_id ORDER BY l.created_at DESC LIMIT 50')->fetchAll();
$pageTitle = 'Müşteriler';
require __DIR__ . '/../templates/header.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Müşteri Listesi</h5>
    </div>
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
                        <th>Ad Soyad</th>
                        <th>E-posta</th>
                        <th>Telefon</th>
                        <th>Bakiye</th>
                        <th>API Anahtarı</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td>#<?= (int)$customer['id'] ?></td>
                            <td><?= Helpers::sanitize($customer['name'] . ' ' . $customer['surname']) ?></td>
                            <td><?= Helpers::sanitize($customer['email']) ?></td>
                            <td><?= Helpers::sanitize($customer['phone']) ?></td>
                            <td><?= number_format((float)$customer['balance'], 2, ',', '.') ?></td>
                            <td><code><?= Helpers::sanitize($customer['api_token'] ?? '-') ?></code></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#balanceModal" data-customer="<?= (int)$customer['id'] ?>" data-name="<?= Helpers::sanitize($customer['name'] . ' ' . $customer['surname']) ?>">Bakiye</button>
                                    <form method="post" onsubmit="return confirm('Bu müşteriyi silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="customer_id" value="<?= (int)$customer['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal fade" id="balanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bakiye Güncelle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                <input type="hidden" name="action" value="balance">
                <input type="hidden" name="customer_id" id="balanceCustomerId">
                <div class="mb-3">
                    <label class="form-label">Müşteri</label>
                    <input type="text" class="form-control" id="balanceCustomerName" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tutar</label>
                    <input type="number" class="form-control" name="amount" min="0.01" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">İşlem</label>
                    <select class="form-select" name="type">
                        <option value="ekleme">Bakiye Ekle</option>
                        <option value="cikarma">Bakiye Çıkar</option>
                    </select>
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
    const balanceModal = document.getElementById('balanceModal');
    if (balanceModal) {
        balanceModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) { return; }
            const customerId = button.getAttribute('data-customer');
            const customerName = button.getAttribute('data-name');
            document.getElementById('balanceCustomerId').value = customerId;
            document.getElementById('balanceCustomerName').value = customerName;
        });
    }
</script>

<?php if ($apiLogs): ?>
    <div class="card mt-4">
        <div class="card-header"><h5 class="card-title mb-0">API Logları</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Müşteri</th>
                            <th>Endpoint</th>
                            <th>Metod</th>
                            <th>IP</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apiLogs as $log): ?>
                            <tr>
                                <td><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($log['created_at']))) ?></td>
                                <td><?= Helpers::sanitize($log['email'] ?? '-') ?></td>
                                <td><?= Helpers::sanitize($log['endpoint']) ?></td>
                                <td><?= Helpers::sanitize($log['method']) ?></td>
                                <td><?= Helpers::sanitize($log['ip_address']) ?></td>
                                <td><?= Helpers::sanitize($log['status_code']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../templates/footer.php';
