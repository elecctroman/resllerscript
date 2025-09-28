<?php
require __DIR__ . '/bootstrap.php';

use App\Database;
use App\Helpers;
use App\Mailer;
use App\Settings;
use App\Payments\CryptomusClient;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if ($user['role'] === 'admin') {
    Helpers::redirect('/admin/balances.php');
}

$pdo = Database::connection();
$errors = [];
$cryptomusEnabled = Settings::get('cryptomus_enabled') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($amount <= 0) {
        $errors[] = 'Lütfen geçerli bir yükleme tutarı belirtin.';
    }

    if (!$cryptomusEnabled) {
        $errors[] = 'Ödeme sağlayıcısı aktif olmadığı için bakiye yükleme işlemi yapılamıyor.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO balance_requests (user_id, amount, payment_method, notes, status, created_at) VALUES (:user_id, :amount, :payment_method, :notes, :status, NOW())');
            $stmt->execute([
                'user_id' => $user['id'],
                'amount' => $amount,
                'payment_method' => 'Cryptomus',
                'notes' => $notes ?: null,
                'status' => 'pending',
            ]);

            $requestId = (int)$pdo->lastInsertId();
            $pdo->commit();

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $baseUrl = $scheme . '://' . $host;

            $client = new CryptomusClient();

            $description = Settings::get('cryptomus_description');
            if ($description === null || $description === '') {
                $description = 'Bakiye yükleme talebi';
            }

            $successUrl = Settings::get('cryptomus_success_url');
            $failUrl = Settings::get('cryptomus_fail_url');
            $callbackUrl = $baseUrl . '/webhooks/cryptomus.php';

            $invoice = $client->createInvoice(
                $amount,
                'USD',
                'BAL-' . $requestId,
                $description,
                $user['email'],
                $successUrl ?: $baseUrl . '/balance.php',
                $failUrl ?: $baseUrl . '/balance.php',
                $callbackUrl
            );

            $paymentReference = isset($invoice['uuid']) ? $invoice['uuid'] : (isset($invoice['order_id']) ? $invoice['order_id'] : null);
            $paymentUrl = isset($invoice['url']) ? $invoice['url'] : null;

            $pdo->prepare('UPDATE balance_requests SET payment_provider = :provider, payment_reference = :reference, payment_url = :url, reference = :display_reference WHERE id = :id')
                ->execute([
                    'provider' => 'cryptomus',
                    'reference' => $paymentReference,
                    'url' => $paymentUrl,
                    'display_reference' => 'BAL-' . $requestId,
                    'id' => $requestId,
                ]);

            $adminEmails = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(\PDO::FETCH_COLUMN);
            $message = "Yeni bir bakiye yükleme talebi oluşturuldu.\n\n" .
                "Bayi: {$user['name']}\n" .
                "E-posta: {$user['email']}\n" .
                "Tutar: " . Helpers::formatCurrency($amount, 'USD') . "\n" .
                "Ödeme Yöntemi: Cryptomus\n";

            foreach ($adminEmails as $adminEmail) {
                Mailer::send($adminEmail, 'Yeni Bakiye Talebi', $message);
            }

            if ($paymentUrl) {
                Helpers::redirect($paymentUrl);
            } else {
                $errors[] = 'Ödeme bağlantısı oluşturulamadı. Lütfen tekrar deneyin.';
            }
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($requestId) && $requestId > 0) {
                $pdo->prepare('DELETE FROM balance_requests WHERE id = :id')->execute(['id' => $requestId]);
            }
            $errors[] = 'Bakiye talebiniz kaydedilirken bir hata oluştu: ' . $exception->getMessage();
            $success = '';
        }
    }
}

$requests = [];
$transactions = [];

try {
    $requestStmt = $pdo->prepare('SELECT * FROM balance_requests WHERE user_id = :user_id ORDER BY created_at DESC');
    $requestStmt->execute(['user_id' => $user['id']]);
    $requests = $requestStmt->fetchAll();

    $transactionsStmt = $pdo->prepare('SELECT * FROM balance_transactions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20');
    $transactionsStmt->execute(['user_id' => $user['id']]);
    $transactions = $transactionsStmt->fetchAll();
} catch (\PDOException $exception) {
    $errors[] = 'Bakiye hareketleri yüklenirken bir veritabanı hatası oluştu. Lütfen yöneticiyle iletişime geçin.';
}

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

                <?php if (!$cryptomusEnabled): ?>
                    <div class="alert alert-warning">Cryptomus entegrasyonu yapılandırılana kadar bakiye yükleme işlemi pasif durumdadır.</div>
                <?php endif; ?>

                <form method="post" class="vstack gap-3">
                    <div>
                        <label class="form-label"><?= Helpers::sanitize('Yüklenecek Tutar') ?> (<?= Helpers::sanitize(Helpers::currencySymbol()) ?>)</label>
                        <input type="number" step="0.01" min="1" name="amount" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Açıklama</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Ek bilgi iletmek isterseniz yazabilirsiniz."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" <?= !$cryptomusEnabled ? 'disabled' : '' ?>>Cryptomus ile Ödeme Yap</button>
                    <p class="text-muted small mb-0">Ödeme tamamlandığında işlem durumu otomatik olarak güncellenir.</p>
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
                                    <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$request['amount'])) ?></td>
                                    <td><?= Helpers::sanitize($request['payment_method']) ?></td>
                                    <?php
                                    $displayReference = '-';
                                    if (!empty($request['payment_reference'])) {
                                        $displayReference = $request['payment_reference'];
                                    } elseif (!empty($request['reference'])) {
                                        $displayReference = $request['reference'];
                                    }
                                    ?>
                                    <td><?= Helpers::sanitize($displayReference) ?></td>
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
                                    <td><?= Helpers::sanitize($transaction['type'] === 'credit' ? '+' : '-') ?><?= Helpers::sanitize(Helpers::formatCurrency((float)$transaction['amount'])) ?></td>
                                    <td><?= strtoupper(Helpers::sanitize($transaction['type'])) ?></td>
                                    <td><?= Helpers::sanitize(isset($transaction['description']) ? $transaction['description'] : '-') ?></td>
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
