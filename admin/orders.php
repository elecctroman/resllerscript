<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;
use App\Database;
use App\Auth;
use App\Mailer;
use App\Telegram;

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    Helpers::redirect('/');
}

$pdo = Database::connection();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $newStatus = isset($_POST['status']) ? $_POST['status'] : '';

    $stmt = $pdo->prepare('SELECT po.*, p.name AS package_name, p.initial_balance, p.price FROM package_orders po INNER JOIN packages p ON po.package_id = p.id WHERE po.id = :id');
    $stmt->execute(['id' => $orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        $errors[] = 'Sipariş bulunamadı.';
    } elseif (!in_array($newStatus, ['pending', 'paid', 'completed', 'cancelled'], true)) {
        $errors[] = 'Geçersiz durum seçildi.';
    } else {
        $pdo->prepare('UPDATE package_orders SET status = :status, updated_at = NOW() WHERE id = :id')->execute([
            'status' => $newStatus,
            'id' => $orderId,
        ]);

        if ($newStatus === 'completed') {
            $userStmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
            $userStmt->execute(['email' => $order['email']]);
            $existingUser = $userStmt->fetch();

            if ($existingUser) {
                $userId = (int)$existingUser['id'];
                $password = null;
            } else {
                $password = bin2hex(random_bytes(4));
                $userId = Auth::createUser($order['name'], $order['email'], $password, 'reseller', (float)$order['initial_balance']);
            }

            $initialCredit = (float)$order['initial_balance'];

            if ($initialCredit > 0) {
                $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')->execute([
                    'user_id' => $userId,
                    'amount' => $initialCredit,
                    'type' => 'credit',
                    'description' => $order['package_name'] . ' paket başlangıç bakiyesi',
                ]);

                if ($existingUser) {
                    $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')->execute([
                        'amount' => $initialCredit,
                        'id' => $userId,
                    ]);
                }
            }

            $hostName = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'bayi-paneli';
            $loginMessage = "Merhaba {$order['name']},\n\n" .
                "Bayilik hesabınız aktif edilmiştir.\n" .
                "Panel girişi: " . $hostName . "\n" .
                "Kullanıcı adı: {$order['email']}\n";
            if (!empty($password)) {
                $loginMessage .= "Geçici şifre: $password\n";
            } else {
                $loginMessage .= "Kayıtlı şifrenizle giriş yapabilirsiniz.\n";
            }
            $loginMessage .= "\nSatın aldığınız paket: {$order['package_name']}\nTutar: $" . number_format((float)$order['price'], 2, '.', ',') . "\n\nİyi çalışmalar.";

            Mailer::send($order['email'], 'Bayilik Hesabınız Hazır', $loginMessage);

            Telegram::notify(sprintf(
                "Yeni teslimat tamamlandı!\nBayi: %s\nPaket: %s\nTutar: $%s",
                $order['name'],
                $order['package_name'],
                number_format((float)$order['price'], 2, '.', ',')
            ));
        }

        $success = 'Sipariş durumu güncellendi.';
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

$pageTitle = 'Sipariş Yönetimi';
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
                        <td>$<?= number_format((float)$order['total_amount'], 2, '.', ',') ?></td>
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
