<?php
require __DIR__ . '/bootstrap.php';

use App\Helpers;
use App\Database;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];
$pdo = Database::connection();

$pageTitle = 'Kontrol Paneli';

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Merhaba, <?= Helpers::sanitize($user['name']) ?></h4>
                    <p class="text-muted mb-0">Hesap durumunuzu ve işlerinizi buradan yönetebilirsiniz.</p>
                </div>
                <div class="text-end">
                    <span class="badge bg-success rounded-pill fs-6">Bakiye: <?= number_format((float)$user['balance'], 2, ',', '.') ?> ₺</span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($user['role'] === 'admin'): ?>
        <div class="col-12 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-uppercase text-muted">Toplam Bayi</h6>
                    <h3 class="mb-0">
                        <?php
                        $totalResellers = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE role = 'reseller'")->fetchColumn();
                        echo (int)$totalResellers;
                        ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-uppercase text-muted">Bekleyen Sipariş</h6>
                    <h3 class="mb-0">
                        <?php
                        $pendingOrders = $pdo->query("SELECT COUNT(*) FROM package_orders WHERE status = 'pending'")->fetchColumn();
                        echo (int)$pendingOrders;
                        ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-uppercase text-muted">Aktif Paket</h6>
                    <h3 class="mb-0">
                        <?php
                        $activePackages = $pdo->query('SELECT COUNT(*) FROM packages WHERE is_active = 1')->fetchColumn();
                        echo (int)$activePackages;
                        ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-uppercase text-muted">Açık Destek</h6>
                    <h3 class="mb-0">
                        <?php
                        $openTickets = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status != 'closed'")->fetchColumn();
                        echo (int)$openTickets;
                        ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Hızlı Yönetim</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="/admin/packages.php" class="btn btn-outline-primary w-100">Paketleri Yönet</a>
                        </div>
                        <div class="col-md-3">
                            <a href="/admin/orders.php" class="btn btn-outline-primary w-100">Siparişler</a>
                        </div>
                        <div class="col-md-3">
                            <a href="/admin/users.php" class="btn btn-outline-primary w-100">Bayiler</a>
                        </div>
                        <div class="col-md-3">
                            <a href="/admin/products.php" class="btn btn-outline-primary w-100">Ürünler</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Paket Siparişleri</h5>
                </div>
                <div class="card-body">
                    <?php
                    $orders = $pdo->prepare('SELECT po.*, p.name AS package_name FROM package_orders po INNER JOIN packages p ON po.package_id = p.id WHERE po.email = :email ORDER BY po.created_at DESC LIMIT 5');
                    $orders->execute(['email' => $user['email']]);
                    $orderRows = $orders->fetchAll();
                    ?>
                    <?php if ($orderRows): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Paket</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th>Tarih</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($orderRows as $order): ?>
                                    <tr>
                                        <td><?= Helpers::sanitize($order['package_name']) ?></td>
                                        <td><?= number_format((float)$order['total_amount'], 2, ',', '.') ?> ₺</td>
                                        <td><span class="badge-status <?= Helpers::sanitize($order['status']) ?>"><?= strtoupper(Helpers::sanitize($order['status'])) ?></span></td>
                                        <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Henüz bir sipariş geçmişiniz bulunmamaktadır.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Destek Talepleri</h5>
                </div>
                <div class="card-body">
                    <?php
                    $tickets = $pdo->prepare('SELECT * FROM support_tickets WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5');
                    $tickets->execute(['user_id' => $user['id']]);
                    $ticketRows = $tickets->fetchAll();
                    ?>
                    <?php if ($ticketRows): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Konu</th>
                                    <th>Durum</th>
                                    <th>Oluşturma Tarihi</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($ticketRows as $ticket): ?>
                                    <tr>
                                        <td><?= Helpers::sanitize($ticket['subject']) ?></td>
                                        <td><span class="badge-status <?= Helpers::sanitize($ticket['status']) ?>"><?= strtoupper(Helpers::sanitize($ticket['status'])) ?></span></td>
                                        <td><?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Henüz bir destek talebi oluşturmadınız.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/templates/footer.php';
