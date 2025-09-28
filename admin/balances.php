<?php
require __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Helpers;
use App\Mailer;
use App\Telegram;

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    Helpers::redirect('/');
}

$pdo = Database::connection();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $adminNote = trim($_POST['admin_note'] ?? '');

    $stmt = $pdo->prepare('SELECT br.*, u.name, u.email FROM balance_requests br INNER JOIN users u ON br.user_id = u.id WHERE br.id = :id');
    $stmt->execute(['id' => $requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        $errors[] = 'Bakiye talebi bulunamadı.';
    } elseif (!in_array($status, ['approved', 'rejected'], true)) {
        $errors[] = 'Geçersiz durum seçildi.';
    } elseif ($request['status'] !== 'pending') {
        $errors[] = 'Yalnızca bekleyen talepler güncellenebilir.';
    } else {
        try {
            $pdo->beginTransaction();

            $pdo->prepare('UPDATE balance_requests SET status = :status, admin_note = :admin_note, processed_by = :processed_by, processed_at = NOW() WHERE id = :id')
                ->execute([
                    'status' => $status,
                    'admin_note' => $adminNote ?: null,
                    'processed_by' => $_SESSION['user']['id'],
                    'id' => $requestId,
                ]);

            if ($status === 'approved') {
                $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')
                    ->execute([
                        'user_id' => $request['user_id'],
                        'amount' => $request['amount'],
                        'type' => 'credit',
                        'description' => 'Bakiye yükleme onayı',
                    ]);

                $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')
                    ->execute([
                        'amount' => $request['amount'],
                        'id' => $request['user_id'],
                    ]);
            }

            $pdo->commit();

            $success = 'Bakiye talebi güncellendi.';

            $message = $status === 'approved'
                ? "Bakiye yükleme talebiniz onaylandı. Toplam: $" . number_format((float)$request['amount'], 2, '.', ',')
                : "Bakiye yükleme talebiniz reddedildi.";

            if ($adminNote) {
                $message .= "\nAçıklama: $adminNote";
            }

            Mailer::send($request['email'], 'Bakiye Talebiniz Güncellendi', $message);

            if ($status === 'approved') {
                Telegram::notify(sprintf(
                    "Yeni bakiye yüklemesi tamamlandı!\nBayi: %s\nTutar: $%s",
                    $request['name'],
                    number_format((float)$request['amount'], 2, '.', ',')
                ));
            }
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            $errors[] = 'İşlem sırasında bir hata oluştu: ' . $exception->getMessage();
        }
    }
}

$pendingStmt = $pdo->prepare('SELECT br.*, u.name, u.email FROM balance_requests br INNER JOIN users u ON br.user_id = u.id WHERE br.status = :status ORDER BY br.created_at ASC');
$pendingStmt->execute(['status' => 'pending']);
$pending = $pendingStmt->fetchAll();

$historyStmt = $pdo->prepare('SELECT br.*, u.name FROM balance_requests br INNER JOIN users u ON br.user_id = u.id WHERE br.status != :status ORDER BY br.created_at DESC LIMIT 50');
$historyStmt->execute(['status' => 'pending']);
$history = $historyStmt->fetchAll();

$pageTitle = 'Bakiye Talepleri';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Bekleyen Bakiye Talepleri</h5>
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

                <?php if (!$pending): ?>
                    <p class="text-muted mb-0">Bekleyen bakiye talebi bulunmuyor.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Bayi</th>
                                <th>Tutar</th>
                                <th>Yöntem</th>
                                <th>Referans</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pending as $request): ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime($request['created_at'])) ?></td>
                                    <td>
                                        <strong><?= Helpers::sanitize($request['name']) ?></strong><br>
                                        <small class="text-muted"><?= Helpers::sanitize($request['email']) ?></small>
                                    </td>
                                    <td>$<?= number_format((float)$request['amount'], 2, '.', ',') ?></td>
                                    <td><?= Helpers::sanitize($request['payment_method']) ?></td>
                                    <td><?= Helpers::sanitize($request['reference'] ?? '-') ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#processRequest<?= (int)$request['id'] ?>">Değerlendir</button>
                                    </td>
                                </tr>

                                <div class="modal fade" id="processRequest<?= (int)$request['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Bakiye Talebini Güncelle</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Durum</label>
                                                        <select name="status" class="form-select">
                                                            <option value="approved">Onayla</option>
                                                            <option value="rejected">Reddet</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Yönetici Notu</label>
                                                        <textarea name="admin_note" class="form-control" rows="3" placeholder="Talep hakkında kısa not"></textarea>
                                                    </div>
                                                    <p class="text-muted small mb-0">Onayladığınızda tutar otomatik olarak bayi hesabına eklenecektir.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                                    <button type="submit" class="btn btn-primary">Kaydet</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Geçmiş İşlemler</h5>
            </div>
            <div class="card-body">
                <?php if (!$history): ?>
                    <p class="text-muted mb-0">Henüz tamamlanan veya reddedilen talep bulunmuyor.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Bayi</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th>Not</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($history as $item): ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime($item['processed_at'] ?? $item['created_at'])) ?></td>
                                    <td><?= Helpers::sanitize($item['name']) ?></td>
                                    <td>$<?= number_format((float)$item['amount'], 2, '.', ',') ?></td>
                                    <td><span class="badge-status <?= Helpers::sanitize($item['status']) ?>"><?= strtoupper(Helpers::sanitize($item['status'])) ?></span></td>
                                    <td><?= Helpers::sanitize($item['admin_note'] ?? '-') ?></td>
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
