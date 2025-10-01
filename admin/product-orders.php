<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Helpers;
use App\Database;
use App\Telegram;
use App\Notifications\ResellerNotifier;
use App\ApiToken;

Auth::requireRoles(array('super_admin', 'admin', 'support'));

$currentUser = $_SESSION['user'];
$pdo = Database::connection();
$errors = Helpers::getFlash('errors', array());
$success = Helpers::getFlash('success', '');
$currentUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/admin/product-orders.php';

$allowedStatuses = array('pending', 'processing', 'completed', 'cancelled');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectPath = Helpers::normalizeRedirectPath(isset($_POST['redirect']) ? $_POST['redirect'] : '', '/admin/product-orders.php');
    $formErrors = array();
    $formSuccess = '';

    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $newStatus = isset($_POST['status']) ? $_POST['status'] : '';
    $statusNote = isset($_POST['status_note']) ? trim($_POST['status_note']) : '';

    if ($orderId <= 0) {
        $formErrors[] = 'Geçersiz sipariş seçildi.';
    } elseif (!in_array($newStatus, $allowedStatuses, true)) {
        $formErrors[] = 'Geçersiz durum seçildi.';
    } else {
        try {
            $pdo->beginTransaction();

            $orderStmt = $pdo->prepare('SELECT po.*, u.name AS user_name, u.email AS user_email, u.id AS owner_id, u.notify_order_completed, u.telegram_bot_token, u.telegram_chat_id, p.name AS product_name, p.sku, c.name AS category_name FROM product_orders po INNER JOIN users u ON po.user_id = u.id INNER JOIN products p ON po.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE po.id = :id FOR UPDATE');
            $orderStmt->execute(array('id' => $orderId));
            $order = $orderStmt->fetch();

            if (!$order) {
                $pdo->rollBack();
                $formErrors[] = 'Sipariş bulunamadı.';
            } else {
                $currentStatus = $order['status'];

                if ($currentStatus === $newStatus) {
                    $pdo->rollBack();
                    $formErrors[] = 'Sipariş zaten bu durumda.';
                } else {
                    $userStmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
                    $userStmt->execute(array('id' => $order['owner_id']));
                    $userRow = $userStmt->fetch();

                    if (!$userRow) {
                        $pdo->rollBack();
                        $formErrors[] = 'Bayi bilgilerine ulaşılamadı.';
                    } else {
                        $amount = (float)$order['price'];
                        $userBalance = (float)$userRow['balance'];

                        if ($currentStatus === 'cancelled' && $newStatus !== 'cancelled') {
                            if ($userBalance < $amount) {
                                $pdo->rollBack();
                                $formErrors[] = 'Bayi bakiyesi yeniden tahsilat için yetersiz.';
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

                        if (!$formErrors) {
                            $pdo->prepare('UPDATE product_orders SET status = :status, admin_note = :admin_note, updated_at = NOW() WHERE id = :id')->execute(array(
                                'status' => $newStatus,
                                'admin_note' => $statusNote !== '' ? $statusNote : null,
                                'id' => $orderId,
                            ));

                            $pdo->commit();

                            if ($newStatus === 'completed') {
                                $orderPayload = array(
                                    'id' => $orderId,
                                    'product_name' => isset($order['product_name']) ? $order['product_name'] : null,
                                    'quantity' => isset($order['quantity']) ? (int)$order['quantity'] : 1,
                                    'price' => isset($order['price']) ? $order['price'] : null,
                                );

                                $userPayload = array(
                                    'email' => $order['user_email'],
                                    'name' => isset($order['user_name']) ? $order['user_name'] : null,
                                    'notify_order_completed' => isset($order['notify_order_completed']) ? $order['notify_order_completed'] : null,
                                    'telegram_bot_token' => isset($order['telegram_bot_token']) ? $order['telegram_bot_token'] : null,
                                    'telegram_chat_id' => isset($order['telegram_chat_id']) ? $order['telegram_chat_id'] : null,
                                );

                                ResellerNotifier::sendOrderCompleted($orderPayload, $userPayload);

                                Telegram::notify(sprintf(
                                    "Ürün siparişi tamamlandı!\nBayi: %s\nÜrün: %s\nSipariş No: #%d",
                                    $order['user_name'],
                                    $order['product_name'],
                                    $orderId
                                ));
                            }

                            if (!empty($order['api_token_id'])) {
                                $metadata = null;
                                $externalReference = isset($order['external_reference']) ? $order['external_reference'] : null;
                                if ((!$externalReference || $externalReference === '') && isset($order['external_metadata']) && $order['external_metadata'] !== '') {
                                    $metadata = json_decode($order['external_metadata'], true);
                                    if (is_array($metadata) && isset($metadata['woocommerce_order']['id'])) {
                                        $externalReference = (string)$metadata['woocommerce_order']['id'];
                                    }
                                }

                                $adminNotePayload = $statusNote !== '' ? $statusNote : (isset($order['admin_note']) ? $order['admin_note'] : null);

                                $payload = array(
                                    'event' => 'order_status_changed',
                                    'order_id' => (int)$orderId,
                                    'remote_order_id' => (int)$orderId,
                                    'status' => $newStatus,
                                    'previous_status' => $currentStatus,
                                    'external_reference' => $externalReference,
                                    'woocommerce_order_id' => $externalReference,
                                    'sku' => isset($order['sku']) ? $order['sku'] : null,
                                    'quantity' => isset($order['quantity']) ? (int)$order['quantity'] : 1,
                                    'total' => (float)$order['price'],
                                );

                                if ($adminNotePayload !== null && $adminNotePayload !== '') {
                                    $payload['admin_note'] = $adminNotePayload;
                                } else {
                                    $payload['admin_note'] = null;
                                }

                                if (is_array($metadata) && isset($metadata['woocommerce_order']['key']) && $metadata['woocommerce_order']['key'] !== '') {
                                    $payload['woocommerce_order_key'] = $metadata['woocommerce_order']['key'];
                                }

                                $webhookResult = ApiToken::notifyWebhook((int)$order['api_token_id'], $payload);
                                if (!$webhookResult['success']) {
                                    $formErrors[] = 'WooCommerce sipariş bildirimi gönderilemedi: ' . (isset($webhookResult['error']) ? $webhookResult['error'] : 'Bilinmeyen hata');
                                }
                            }

                            if (!$formErrors) {
                                $formSuccess = 'Sipariş durumu güncellendi.';
                                AuditLog::record(
                                    $currentUser['id'],
                                    'product_order.status_change',
                                    'product_order',
                                    $orderId,
                                    sprintf('Sipariş #%d durumu %s -> %s olarak güncellendi', $orderId, $currentStatus, $newStatus)
                                );
                            }
                        }
                    }
                }
            }
        } catch (\PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $formErrors[] = 'Sipariş güncellenirken bir hata oluştu: ' . $exception->getMessage();
        }
    }

    if ($formErrors) {
        Helpers::redirectWithFlash($redirectPath, array('errors' => $formErrors));
    }

    if ($formSuccess !== '') {
        Helpers::redirectWithFlash($redirectPath, array('success' => $formSuccess));
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
                        <th>Adet</th>
                        <th>Fiyat</th>
                        <th>Kaynak</th>
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
                            <td><?= isset($order['quantity']) ? (int)$order['quantity'] : 1 ?></td>
                            <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$order['price'])) ?></td>
                            <td>
                                <?php
                                $source = isset($order['source']) ? $order['source'] : 'panel';
                                echo '<span class="badge bg-light text-dark">' . Helpers::sanitize(strtoupper($source)) . '</span>';
                                if (!empty($order['external_reference'])) {
                                    echo '<div class="small text-muted mt-1">Ref: ' . Helpers::sanitize($order['external_reference']) . '</div>';
                                }
                                ?>
                            </td>
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
                                            <dd class="col-sm-8"><?= Helpers::sanitize(Helpers::formatCurrency((float)$order['price'])) ?></dd>
                                            <dt class="col-sm-4">Adet</dt>
                                            <dd class="col-sm-8"><?= isset($order['quantity']) ? (int)$order['quantity'] : 1 ?></dd>
                                            <dt class="col-sm-4">Kaynak</dt>
                                            <dd class="col-sm-8">
                                                <?php
                                                $sourceLabel = isset($order['source']) ? $order['source'] : 'panel';
                                                echo Helpers::sanitize(strtoupper($sourceLabel));
                                                if (!empty($order['external_reference'])) {
                                                    echo '<br><small class="text-muted">Referans: ' . Helpers::sanitize($order['external_reference']) . '</small>';
                                                }
                                                ?>
                                            </dd>
                                            <?php if (!empty($order['external_metadata'])): ?>
                                                <dt class="col-sm-4">Harici Bilgi</dt>
                                                <dd class="col-sm-8"><pre class="bg-light p-2 small mb-0"><?= Helpers::sanitize($order['external_metadata']) ?></pre></dd>
                                            <?php endif; ?>
                                            <dt class="col-sm-4">Not</dt>
                                            <dd class="col-sm-8"><?= nl2br(Helpers::sanitize(isset($order['note']) ? $order['note'] : '-')) ?></dd>
                                            <dt class="col-sm-4">Yönetici Notu</dt>
                                            <dd class="col-sm-8"><?= nl2br(Helpers::sanitize(isset($order['admin_note']) ? $order['admin_note'] : '-')) ?></dd>
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
                                                <label class="form-label">Durum</label>
                                                <select name="status" class="form-select" required>
                                                    <?php foreach ($allowedStatuses as $statusOption): ?>
                                                        <?php $optionValue = $statusOption; ?>
                                                        <option value="<?= Helpers::sanitize($optionValue) ?>" <?= $order['status'] === $optionValue ? 'selected' : '' ?>><?= Helpers::sanitize(strtoupper($optionValue)) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Yönetici Notu</label>
                                                <textarea name="status_note" class="form-control" rows="3" placeholder="Siparişe ilişkin notunuzu girin."><?= Helpers::sanitize(isset($order['admin_note']) ? $order['admin_note'] : '') ?></textarea>
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
