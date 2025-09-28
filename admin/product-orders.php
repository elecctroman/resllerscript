<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;
use App\Database;
use App\Telegram;
use App\Mailer;

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    Helpers::redirect('/');
}

$pdo = Database::connection();
$errors = [];
$success = '';

$allowedStatuses = array('pending', 'processing', 'completed', 'cancelled');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $newStatus = isset($_POST['status']) ? $_POST['status'] : '';

    if ($orderId <= 0) {
        $errors[] = 'Geçersiz sipariş seçildi.';
    } elseif (!in_array($newStatus, $allowedStatuses, true)) {
        $errors[] = 'Geçersiz durum seçildi.';
    } else {
        try {
            $pdo->beginTransaction();

            $orderStmt = $pdo->prepare('SELECT po.*, u.name AS user_name, u.email AS user_email, u.id AS owner_id, p.name AS product_name, p.sku, c.name AS category_name FROM product_orders po INNER JOIN users u ON po.user_id = u.id INNER JOIN products p ON po.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE po.id = :id FOR UPDATE');
            $orderStmt->execute(array('id' => $orderId));
            $order = $orderStmt->fetch();

            if (!$order) {
                $pdo->rollBack();
                $errors[] = 'Sipariş bulunamadı.';
            } else {
                $currentStatus = $order['status'];

                if ($currentStatus === $newStatus) {
                    $pdo->rollBack();
                    $errors[] = 'Sipariş zaten bu durumda.';
                } else {
                    $userStmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
                    $userStmt->execute(array('id' => $order['owner_id']));
                    $userRow = $userStmt->fetch();

                    if (!$userRow) {
                        $pdo->rollBack();
                        $errors[] = 'Bayi bilgilerine ulaşılamadı.';
                    } else {
                        $amount = (float)$order['price'];
                        $userBalance = (float)$userRow['balance'];

                        if ($currentStatus === 'cancelled' && $newStatus !== 'cancelled') {
                            if ($userBalance < $amount) {
                                $pdo->rollBack();
                                $errors[] = 'Bayi bakiyesi yeniden tahsilat için yetersiz.';
                            } else {
                                $pdo->prepare('UPDATE users SET balance = balance - :amount WHERE id = :id')->execute(array(
                                    'amount' => $amount,
                                    'id' => $order['owner_id'],
                                ));

                                $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')->execute(array(
                                    'user_id' => $order['owner_id'],
                                    'amount' => $amount,
                                    'type' => 'debit',
                                    'description' => 'Yeniden tahsilat - Ürün siparişi #' . $orderId,
                                ));
                            }
                        } elseif ($newStatus === 'cancelled' && $currentStatus !== 'cancelled') {
                            $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')->execute(array(
                                'amount' => $amount,
                                'id' => $order['owner_id'],
                            ));

                            $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')->execute(array(
                                'user_id' => $order['owner_id'],
                                'amount' => $amount,
                                'type' => 'credit',
                                'description' => 'İade - Ürün siparişi #' . $orderId,
                            ));
                        }

                        if (!$errors) {
                            $pdo->prepare('UPDATE product_orders SET status = :status, updated_at = NOW() WHERE id = :id')->execute(array(
                                'status' => $newStatus,
                                'id' => $orderId,
                            ));

                            $pdo->commit();

                            if ($newStatus === 'completed') {
                                $message = "Merhaba {$order['user_name']}, siparişini verdiğiniz {$order['product_name']} ürününün teslimatı tamamlandı.";
                                Mailer::send($order['user_email'], 'Ürün Siparişiniz Tamamlandı', $message);

                                Telegram::notify(sprintf(
                                    "Ürün siparişi tamamlandı!\nBayi: %s\nÜrün: %s\nSipariş No: #%d",
                                    $order['user_name'],
                                    $order['product_name'],
                                    $orderId
                                ));
                            }

                            $success = 'Sipariş durumu güncellendi.';
                        }
                    }
                }
            }
        } catch (\PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Sipariş güncellenirken bir hata oluştu: ' . $exception->getMessage();
        }
    }
}

$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

$query = 'SELECT po.*, u.name AS user_name, u.email AS user_email, p.name AS product_name, p.sku, c.name AS category_name FROM product_orders po INNER JOIN users u ON po.user_id = u.id INNER JOIN products p ON po.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id';
$params = array();

if ($statusFilter && in_array($statusFilter, $allowedStatuses, true)) {
    $query .= ' WHERE po.status = :status';
    $params['status'] = $statusFilter;
}

$query .= ' ORDER BY po.created_at DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Ürün Siparişleri';

include __DIR__ . '/../templates/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="mb-0">Ürün Siparişleri</h5>
            <small class="text-muted">Bayilerin katalogdan verdiği siparişleri buradan takip edebilirsiniz.</small>
        </div>
        <form method="get" class="d-flex gap-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Tümü</option>
                <?php foreach ($allowedStatuses as $statusOption): ?>
                    <?php $optionValue = $statusOption; ?>
                    <option value="<?= Helpers::sanitize($optionValue) ?>" <?= $statusFilter === $optionValue ? 'selected' : '' ?>><?= Helpers::sanitize(strtoupper($optionValue)) ?></option>
                <?php endforeach; ?>
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

        <?php if (!$orders): ?>
            <p class="text-muted mb-0">Henüz ürün siparişi bulunmuyor.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Bayi</th>
                        <th>Ürün</th>
                        <th>Fiyat</th>
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
                                <strong><?= Helpers::sanitize($order['user_name']) ?></strong><br>
                                <small class="text-muted"><?= Helpers::sanitize($order['user_email']) ?></small>
                            </td>
                            <td>
                                <strong><?= Helpers::sanitize($order['product_name']) ?></strong><br>
                                <small class="text-muted">Kategori: <?= Helpers::sanitize(isset($order['category_name']) ? $order['category_name'] : '-') ?> | SKU: <?= Helpers::sanitize(isset($order['sku']) ? $order['sku'] : '-') ?></small>
                            </td>
                            <td>$<?= number_format((float)$order['price'], 2, '.', ',') ?></td>
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
                                                <?= Helpers::sanitize($order['user_name']) ?><br>
                                                <?= Helpers::sanitize($order['user_email']) ?>
                                            </dd>
                                            <dt class="col-sm-4">Ürün</dt>
                                            <dd class="col-sm-8">
                                                <?= Helpers::sanitize($order['product_name']) ?><br>
                                                <small class="text-muted">Kategori: <?= Helpers::sanitize(isset($order['category_name']) ? $order['category_name'] : '-') ?> | SKU: <?= Helpers::sanitize(isset($order['sku']) ? $order['sku'] : '-') ?></small>
                                            </dd>
                                            <dt class="col-sm-4">Fiyat</dt>
                                            <dd class="col-sm-8">$<?= number_format((float)$order['price'], 2, '.', ',') ?></dd>
                                            <dt class="col-sm-4">Not</dt>
                                            <dd class="col-sm-8"><?= nl2br(Helpers::sanitize(isset($order['note']) ? $order['note'] : '-')) ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="orderStatus<?= (int)$order['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="post">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Sipariş Durumu</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Durum</label>
                                                <select name="status" class="form-select" required>
                                                    <?php foreach ($allowedStatuses as $statusOption): ?>
                                                        <?php $optionValue = $statusOption; ?>
                                                        <option value="<?= Helpers::sanitize($optionValue) ?>" <?= $order['status'] === $optionValue ? 'selected' : '' ?>><?= Helpers::sanitize(strtoupper($optionValue)) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <p class="text-muted small mb-0">İptal edilen siparişler bayinin bakiyesine otomatik olarak iade edilir.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Kapat</button>
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
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
