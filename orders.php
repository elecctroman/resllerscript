<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if (Auth::isAdminRole($user['role'])) {
    Helpers::redirect('/admin/orders.php');
}

$pageTitle = 'Siparişlerim';

if (!Helpers::featureEnabled('orders')) {
    Helpers::setFlash('warning', 'Sipariş geçmişi şu anda görüntülenemiyor.');
    Helpers::redirect('/dashboard.php');
}

$pdo = Database::connection();

$productOrders = [];
$packageOrders = [];
$errors = [];

try {
    $productStmt = $pdo->prepare('SELECT po.*, pr.name AS product_name, pr.sku AS product_sku, cat.name AS category_name FROM product_orders po INNER JOIN products pr ON po.product_id = pr.id INNER JOIN categories cat ON pr.category_id = cat.id WHERE po.user_id = :user_id ORDER BY po.created_at DESC');
    $productStmt->execute(['user_id' => $user['id']]);
    $productOrders = $productStmt->fetchAll();

    $packageStmt = $pdo->prepare('SELECT po.*, p.name AS package_name FROM package_orders po INNER JOIN packages p ON po.package_id = p.id WHERE po.email = :email ORDER BY po.created_at DESC');
    $packageStmt->execute(['email' => $user['email']]);
    $packageOrders = $packageStmt->fetchAll();
} catch (\PDOException $exception) {
    $errors[] = 'Sipariş kayıtları yüklenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
}

include __DIR__ . '/templates/header.php';
?>
<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= Helpers::sanitize($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Ürün Siparişleri</h5>
                <span class="text-muted small">Toplam: <?= count($productOrders) ?></span>
            </div>
            <div class="card-body">
                <?php if ($productOrders): ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-striped">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Ürün</th>
                                <th>Kategori</th>
                                <th>Adet</th>
                                <th>Ödenen Tutar</th>
                                <th>Kaynak</th>
                                <th>Durum</th>
                                <th>Tarih</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($productOrders as $order): ?>
                                <tr>
                                    <td><?= (int)$order['id'] ?></td>
                                    <td>
                                        <strong><?= Helpers::sanitize($order['product_name']) ?></strong>
                                        <div class="text-muted small">SKU: <?= Helpers::sanitize(isset($order['product_sku']) ? $order['product_sku'] : '-') ?></div>
                                        <?php if (!empty($order['note'])): ?>
                                            <div class="text-muted small mt-1">Bayi Notu: <?= Helpers::sanitize($order['note']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($order['admin_note'])): ?>
                                            <div class="text-muted small">Yönetici Notu: <?= Helpers::sanitize($order['admin_note']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= Helpers::sanitize($order['category_name']) ?></td>
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
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Henüz ürün siparişi oluşturmadınız.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Paket Siparişleri</h5>
                <a href="<?= Helpers::url('register.php') ?>" class="btn btn-sm btn-outline-primary">Yeni Paket Talebi</a>
            </div>
            <div class="card-body">
                <?php if ($packageOrders): ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-striped">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Paket</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th>Tarih</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($packageOrders as $order): ?>
                                <tr>
                                    <td><?= (int)$order['id'] ?></td>
                                    <td>
                                        <strong><?= Helpers::sanitize($order['package_name']) ?></strong>
                                        <?php if (!empty($order['notes'])): ?>
                                            <div class="text-muted small mt-1">Not: <?= Helpers::sanitize($order['notes']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($order['admin_note'])): ?>
                                            <div class="text-muted small">Yönetici Notu: <?= Helpers::sanitize($order['admin_note']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$order['total_amount'])) ?></td>
                                    <td><span class="badge-status <?= Helpers::sanitize($order['status']) ?>"><?= strtoupper(Helpers::sanitize($order['status'])) ?></span></td>
                                    <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Henüz paket siparişi oluşturmadınız.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';
