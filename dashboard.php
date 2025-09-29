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

if ($user['role'] === 'admin') {
    Helpers::redirect('/admin/dashboard.php');
}

$orderRows = [];
$ticketRows = [];
$dashboardErrors = [];

try {
    $orders = $pdo->prepare('SELECT po.*, p.name AS package_name FROM package_orders po INNER JOIN packages p ON po.package_id = p.id WHERE po.email = :email ORDER BY po.created_at DESC LIMIT 5');
    $orders->execute(['email' => $user['email']]);
    $orderRows = $orders->fetchAll();

    $tickets = $pdo->prepare('SELECT * FROM support_tickets WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5');
    $tickets->execute(['user_id' => $user['id']]);
    $ticketRows = $tickets->fetchAll();
} catch (\PDOException $exception) {
    $dashboardErrors[] = 'Panel verileri yüklenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin veya yöneticiyle iletişime geçin.';
}

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h4 class="mb-1"><?= Helpers::sanitize('Merhaba') ?>, <?= Helpers::sanitize($user['name']) ?></h4>
                    <p class="text-muted mb-0"><?= Helpers::sanitize('Siparişlerinizi, ürünleri ve destek taleplerinizi tek panelden yönetin.') ?></p>
                </div>
                <div class="text-md-end">
                    <span class="badge bg-success rounded-pill fs-6"><?= Helpers::sanitize('Bakiye') ?>: <?= Helpers::sanitize(Helpers::formatCurrency((float)$user['balance'])) ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($dashboardErrors): ?>
        <div class="col-12">
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($dashboardErrors as $error): ?>
                        <li><?= Helpers::sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?= Helpers::sanitize('Paket Siparişleri') ?></h5>
                <a href="/register.php" class="btn btn-sm btn-outline-primary"><?= Helpers::sanitize('Yeni Paket Talebi') ?></a>
            </div>
            <div class="card-body">
                <?php if ($orderRows): ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
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
                                    <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$order['total_amount'])) ?></td>
                                    <td><span class="badge-status <?= Helpers::sanitize($order['status']) ?>"><?= strtoupper(Helpers::sanitize($order['status'])) ?></span></td>
                                    <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0"><?= Helpers::sanitize('Henüz bir sipariş geçmişiniz bulunmamaktadır.') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?= Helpers::sanitize('Destek Talepleri') ?></h5>
                <a href="/support.php" class="btn btn-sm btn-outline-secondary"><?= Helpers::sanitize('Tüm Destek Talepleri') ?></a>
            </div>
            <div class="card-body">
                <?php if ($ticketRows): ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Konu</th>
                                <th>Durum</th>
                                <th>Oluşturma</th>
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
                    <p class="text-muted mb-0"><?= Helpers::sanitize('Henüz bir destek talebi oluşturmadınız.') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';
