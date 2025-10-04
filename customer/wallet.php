<?php
require __DIR__ . '/../bootstrap.php';

use App\Customers\CustomerAuth;
use App\Customers\WalletService;
use App\Customers\CustomerRepository;
use App\Helpers;

$customer = CustomerAuth::ensureCustomer();
$errors = array();
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Güvenlik doğrulaması başarısız oldu.';
    } else {
        $amount = (float)($_POST['amount'] ?? 0);
        $method = $_POST['method'] ?? 'Cuzdan';
        $allowedMethods = array('Cuzdan', 'Shopier', 'PayTR', 'Kripto');
        if (!in_array($method, $allowedMethods, true)) {
            $errors[] = 'Geçersiz ödeme yöntemi seçildi.';
        }
        if ($amount <= 0) {
            $errors[] = 'Geçerli bir tutar girin.';
        }
        if ($method !== 'Cuzdan' && $amount < 10) {
            $errors[] = 'Online bakiye yüklemelerinde minimum tutar 10 birimdir.';
        }
        if (!$errors) {
            if ($method === 'Cuzdan') {
                WalletService::add((int)$customer['id'], $amount, 'Panel üzerinden bakiye ekleme', 'manual-topup');
                $_SESSION['customer'] = CustomerRepository::findById((int)$customer['id']);
                $customer = $_SESSION['customer'];
                $success = 'Bakiyeniz güncellendi.';
            } else {
                WalletService::createTopupRequest((int)$customer['id'], $amount, $method, null, 'Panelden oluşturulan yükleme talebi');
                $success = sprintf('%s ile %.2f tutarında bakiye yükleme talebiniz alınmıştır. Ödeme tamamlandığında bakiyeniz onaylanacaktır.', $method, $amount);
            }
        }
    }
}

$history = WalletService::history((int)$customer['id']);
$pendingTopups = WalletService::pendingRequests((int)$customer['id']);
$pageTitle = 'Cüzdan';
require __DIR__ . '/../templates/customer-header.php';
?>
<div class="row g-4">
    <div class="col-lg-5" id="topup">
        <div class="card customer-card">
            <div class="card-header"><h5 class="card-title mb-0">Bakiye Ekle</h5></div>
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
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                    <div class="col-12">
                        <label class="form-label">Yükleme Tutarı</label>
                        <input type="number" class="form-control" step="0.01" name="amount" min="1" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Ödeme Yöntemi</label>
                        <select class="form-select" name="method">
                            <option value="Shopier">Shopier</option>
                            <option value="PayTR">PayTR</option>
                            <option value="Kripto">Kripto Cüzdan</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100">Yükleme Talebi Oluştur</button>
                    </div>
                </form>
                <?php if ($pendingTopups): ?>
                    <div class="alert alert-info mt-4">
                        <h6 class="fw-semibold">Bekleyen Yükleme Talepleriniz</h6>
                        <ul class="mb-0 small">
                            <?php foreach ($pendingTopups as $request): ?>
                                <li>
                                    <?= Helpers::sanitize(date('d.m.Y H:i', strtotime($request['created_at']))) ?> ·
                                    <?= Helpers::sanitize($request['method']) ?> ·
                                    <?= number_format((float)$request['amount'], 2, ',', '.') ?> <?= Helpers::sanitize($customer['currency'] ?? 'TRY') ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card customer-card">
            <div class="card-header"><h5 class="card-title mb-0">Cüzdan Özeti</h5></div>
            <div class="card-body">
                <div class="mb-4">
                    <div class="customer-metric-title">Güncel Bakiye</div>
                    <div class="customer-metric-value">
                        <?= number_format((float)$customer['balance'], 2, ',', '.') ?> <?= Helpers::sanitize($customer['currency'] ?? 'TRY') ?>
                    </div>
                </div>
                <?php if ($history): ?>
                    <div class="table-responsive">
                        <table class="table customer-table">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Tip</th>
                                    <th>Tutar</th>
                                    <th>Açıklama</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($history as $log): ?>
                                <tr>
                                    <td><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($log['created_at']))) ?></td>
                                    <td class="text-capitalize"><?= Helpers::sanitize($log['type']) ?></td>
                                    <td class="fw-semibold<?= $log['type'] === 'ekleme' ? ' text-success' : ' text-danger' ?>">
                                        <?= $log['type'] === 'ekleme' ? '+' : '-' ?><?= number_format((float)$log['amount'], 2, ',', '.') ?>
                                    </td>
                                    <td><?= Helpers::sanitize($log['description']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Cüzdan hareketiniz bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../templates/customer-footer.php';
