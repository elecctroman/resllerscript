<?php
require __DIR__ . '/bootstrap.php';

use App\Database;
use App\Helpers;
use App\Mailer;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if ($user['role'] === 'admin') {
    Helpers::redirect('/admin/balances.php');
}

$pdo = Database::connection();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $reference = trim($_POST['reference'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($amount <= 0) {
        $errors[] = 'Lütfen geçerli bir yükleme tutarı belirtin.';
    }

    if (!$paymentMethod) {
        $errors[] = 'Ödeme yöntemi zorunludur.';
    }

    if (!$errors) {


$pageTitle = 'Bakiye Yönetimi';

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Bakiye Yükleme</h5>
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

                <form method="post" class="vstack gap-3">
                    <div>
                        <label class="form-label">Yüklenecek Tutar ($)</label>
                        <input type="number" step="0.01" min="1" name="amount" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Ödeme Yöntemi</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="">Seçiniz</option>
                            <option value="Bank Transfer">Banka Havalesi</option>
                            <option value="Credit Card">Kredi Kartı</option>
                            <option value="Other">Diğer</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Ödeme Referansı</label>
                        <input type="text" name="reference" class="form-control" placeholder="Dekont numarası vb.">
                    </div>
                    <div>
                        <label class="form-label">Açıklama</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Ek bilgi iletmek isterseniz yazabilirsiniz."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Talebi Gönder</button>
                    <p class="text-muted small mb-0">Ödeme sağlayıcı detayları yönetici tarafından sağlanacaktır. İşlem sonrası dekontu destek üzerinden paylaşmayı unutmayın.</p>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Bakiye Talepleri</h5>
            </div>
            <div class="card-body">
                <?php if (!$requests): ?>
                    <p class="text-muted mb-0">Henüz bir bakiye talebi oluşturmadınız.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Tutar</th>
                                <th>Yöntem</th>
                                <th>Referans</th>
                                <th>Durum</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime($request['created_at'])) ?></td>
                                    <td>$<?= number_format((float)$request['amount'], 2, '.', ',') ?></td>
                                    <td><?= Helpers::sanitize($request['payment_method']) ?></td>
                                    <td><?= Helpers::sanitize($request['reference'] ?? '-') ?></td>
                                    <td><span class="badge-status <?= Helpers::sanitize($request['status']) ?>"><?= strtoupper(Helpers::sanitize($request['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Son İşlemler</h5>
            </div>
            <div class="card-body">
                <?php if (!$transactions): ?>
                    <p class="text-muted mb-0">Herhangi bir hareket bulunamadı.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Tutar</th>
                                <th>Tip</th>
                                <th>Açıklama</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime($transaction['created_at'])) ?></td>
                                    <td><?= $transaction['type'] === 'credit' ? '+' : '-' ?>$<?= number_format((float)$transaction['amount'], 2, '.', ',') ?></td>
                                    <td><?= strtoupper(Helpers::sanitize($transaction['type'])) ?></td>
                                    <td><?= Helpers::sanitize($transaction['description'] ?? '-') ?></td>
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
<?php include __DIR__ . '/templates/footer.php';
