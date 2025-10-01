<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Database;
use App\Helpers;
use App\Mailer;
use App\Services\PackageOrderService;
use App\Telegram;

Auth::requireRoles(array('super_admin', 'admin', 'finance'));

$currentUser = $_SESSION['user'];
$pdo = Database::connection();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $adminNote = isset($_POST['admin_note']) ? trim($_POST['admin_note']) : '';

    $stmt = $pdo->prepare('SELECT br.*, u.name AS user_name, u.email AS user_email, po.name AS applicant_name, po.email AS applicant_email, po.phone AS applicant_phone, po.company AS applicant_company, po.notes AS applicant_notes, po.status AS package_status, po.id AS order_id, p.name AS package_name FROM balance_requests br LEFT JOIN users u ON br.user_id = u.id LEFT JOIN package_orders po ON br.package_order_id = po.id LEFT JOIN packages p ON po.package_id = p.id WHERE br.id = :id');
    $stmt->execute(['id' => $requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        $errors[] = 'Bakiye talebi bulunamadı.';
    } elseif (!in_array($status, ['approved', 'rejected'], true)) {
        $errors[] = 'Geçersiz durum seçildi.';
    } elseif ($request['status'] !== 'pending') {
        $errors[] = 'Yalnızca bekleyen talepler güncellenebilir.';
    } else {
        $displayName = isset($request['user_name']) && $request['user_name'] !== null
            ? $request['user_name']
            : (isset($request['applicant_name']) ? $request['applicant_name'] : '');
        $displayEmail = isset($request['user_email']) && $request['user_email'] !== null
            ? $request['user_email']
            : (isset($request['applicant_email']) ? $request['applicant_email'] : '');

        $isPackageOrder = !empty($request['package_order_id']);
        $order = null;
        $fulfillmentResult = null;

        try {
            $pdo->beginTransaction();

            $pdo->prepare('UPDATE balance_requests SET status = :status, admin_note = :admin_note, processed_by = :processed_by, processed_at = NOW() WHERE id = :id')
                ->execute([
                    'status' => $status,
                    'admin_note' => $adminNote ?: null,
                    'processed_by' => $currentUser['id'],
                    'id' => $requestId,
                ]);

            if ($status === 'approved') {
                if ($isPackageOrder) {
                    $order = PackageOrderService::loadOrder((int)$request['package_order_id']);

                    if (!$order) {
                        throw new \RuntimeException('İlgili paket siparişi bulunamadı.');
                    }

                    if ($order['status'] !== 'completed') {
                        $fulfillmentResult = PackageOrderService::fulfill($order);
                        PackageOrderService::markCompleted((int)$request['package_order_id'], $order, $adminNote);

                        if ($fulfillmentResult && isset($fulfillmentResult['user_id'])) {
                            $pdo->prepare('UPDATE balance_requests SET user_id = :user_id WHERE id = :id')
                                ->execute([
                                    'user_id' => $fulfillmentResult['user_id'],
                                    'id' => $requestId,
                                ]);
                        }
                    }
                } else {
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
            }

            $pdo->commit();

            $success = 'Bakiye talebi güncellendi.';

            $message = $status === 'approved'
                ? ($isPackageOrder
                    ? "Bayilik başvurunuz onaylandı. Giriş bilgileriniz e-posta adresinize gönderildi."
                    : "Bakiye yükleme talebiniz onaylandı. Toplam: " . Helpers::formatCurrency((float)$request['amount'], 'USD'))
                : "Bakiye yükleme talebiniz reddedildi.";

            if ($adminNote) {
                $message .= "\nAçıklama: $adminNote";
            }

            if ($displayEmail !== '') {
                Mailer::send($displayEmail, $isPackageOrder ? 'Bayilik Başvurusu Güncellendi' : 'Bakiye Talebiniz Güncellendi', $message);
            }

            if ($status === 'approved') {
                if ($isPackageOrder) {
                    Telegram::notify(sprintf(
                        "Yeni bayilik başvurusu onaylandı!\nBayi: %s\nPaket: %s\nTutar: %s",
                        $displayName !== '' ? $displayName : 'Bilinmiyor',
                        isset($request['package_name']) ? $request['package_name'] : '-',
                        Helpers::formatCurrency((float)$request['amount'], 'USD')
                    ));
                } else {
                    Telegram::notify(sprintf(
                        "Yeni bakiye yüklemesi tamamlandı!\nBayi: %s\nTutar: %s",
                        $displayName !== '' ? $displayName : 'Bilinmiyor',
                        Helpers::formatCurrency((float)$request['amount'], 'USD')
                    ));
                }
            }

            AuditLog::record(
                $currentUser['id'],
                'balance.request.update',
                'balance_request',
                $requestId,
                sprintf('Talep #%d %s olarak güncellendi. Tutar: %0.2f', $requestId, $status, (float)$request['amount'])
            );
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            $errors[] = 'İşlem sırasında bir hata oluştu: ' . $exception->getMessage();
        }
    }
}

$pendingStmt = $pdo->prepare('SELECT br.*, u.name AS user_name, u.email AS user_email, po.name AS applicant_name, po.email AS applicant_email, po.phone AS applicant_phone, p.name AS package_name FROM balance_requests br LEFT JOIN users u ON br.user_id = u.id LEFT JOIN package_orders po ON br.package_order_id = po.id LEFT JOIN packages p ON po.package_id = p.id WHERE br.status = :status ORDER BY br.created_at ASC');
$pendingStmt->execute(['status' => 'pending']);
$pending = $pendingStmt->fetchAll();

$historyStmt = $pdo->prepare('SELECT br.*, u.name AS user_name, po.name AS applicant_name FROM balance_requests br LEFT JOIN users u ON br.user_id = u.id LEFT JOIN package_orders po ON br.package_order_id = po.id WHERE br.status != :status ORDER BY br.created_at DESC LIMIT 50');
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
                                <th>Bayi / Aday</th>
                                <th>Tutar</th>
                                <th>Yöntem</th>
                                <th>Tür</th>
                                <th>Referans</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pending as $request): ?>
                                <?php
                                $displayName = isset($request['user_name']) && $request['user_name'] !== null
                                    ? $request['user_name']
                                    : (isset($request['applicant_name']) ? $request['applicant_name'] : '-');
                                $displayEmail = isset($request['user_email']) && $request['user_email'] !== null
                                    ? $request['user_email']
                                    : (isset($request['applicant_email']) ? $request['applicant_email'] : '-');
                                $isPackageOrder = !empty($request['package_order_id']);
                                $typeLabel = $isPackageOrder ? 'Bayilik Başvurusu' : 'Bakiye Talebi';
                                $typeClass = $isPackageOrder ? 'bg-info' : 'bg-secondary';
                                ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime($request['created_at'])) ?></td>
                                    <td>
                                        <strong><?= Helpers::sanitize($displayName) ?></strong><br>
                                        <small class="text-muted"><?= Helpers::sanitize($displayEmail) ?></small>
                                    </td>
                                    <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$request['amount'])) ?></td>
                                    <td><?= Helpers::sanitize($request['payment_method']) ?></td>
                                    <td><span class="badge <?= $typeClass ?> text-uppercase" style="font-size: 0.7rem; letter-spacing: .08em;"><?= Helpers::sanitize($typeLabel) ?></span></td>
                                    <?php
                                    $displayReference = '-';
                                    if (!empty($request['payment_reference'])) {
                                        $displayReference = $request['payment_reference'];
                                    } elseif (!empty($request['reference'])) {
                                        $displayReference = $request['reference'];
                                    }
                                    ?>
                                    <td><?= Helpers::sanitize($displayReference) ?></td>
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
                                                        <label class="form-label">Talep Özeti</label>
                                                        <div class="small bg-light border rounded p-3">
                                                            <div class="mb-2"><strong><?= Helpers::sanitize($displayName) ?></strong></div>
                                                            <div class="mb-2">
                                                                <div><?= Helpers::sanitize($displayEmail) ?></div>
                                                                <?php if (!empty($request['applicant_phone'])): ?>
                                                                    <div><?= Helpers::sanitize($request['applicant_phone']) ?></div>
                                                                <?php endif; ?>
                                                                <?php if (!empty($request['package_name'])): ?>
                                                                    <div><span class="fw-semibold">Paket:</span> <?= Helpers::sanitize($request['package_name']) ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div><span class="fw-semibold">Tutar:</span> <?= Helpers::sanitize(Helpers::formatCurrency((float)$request['amount'])) ?></div>
                                                            <div><span class="fw-semibold">Ödeme Yöntemi:</span> <?= Helpers::sanitize($request['payment_method']) ?></div>
                                                            <?php if (!empty($request['notes'])): ?>
                                                                <div class="mt-2">
                                                                    <span class="fw-semibold d-block">Ödeme Bildirimi</span>
                                                                    <div class="text-muted"><?= nl2br(Helpers::sanitize($request['notes'])) ?></div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
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
                                    <td><?= date('d.m.Y H:i', strtotime(isset($item['processed_at']) ? $item['processed_at'] : $item['created_at'])) ?></td>
                                    <?php
                                    $historyName = isset($item['user_name']) && $item['user_name'] !== null
                                        ? $item['user_name']
                                        : (isset($item['applicant_name']) ? $item['applicant_name'] : '-');
                                    ?>
                                    <td><?= Helpers::sanitize($historyName) ?></td>
                                    <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$item['amount'])) ?></td>
                                    <td><span class="badge-status <?= Helpers::sanitize($item['status']) ?>"><?= strtoupper(Helpers::sanitize($item['status'])) ?></span></td>
                                    <td><?= Helpers::sanitize(isset($item['admin_note']) ? $item['admin_note'] : '-') ?></td>
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
