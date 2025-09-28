<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;
use App\Database;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if ($user['role'] !== 'admin') {
    Helpers::redirect('/dashboard.php');
}

$pdo = Database::connection();
$pageTitle = 'Yönetici Paneli';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Merhaba, <?= Helpers::sanitize($user['name']) ?></h4>
                    <p class="text-muted mb-0">Sistemi buradan yönetebilir, bayilerinizi ve siparişleri takip edebilirsiniz.</p>
                </div>
                <span class="badge bg-success rounded-pill fs-6">Toplam Bakiye: $<?= number_format((float)$user['balance'], 2, '.', ',') ?></span>
            </div>
        </div>
    </div>
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
                    $pendingPackageOrders = $pdo->query("SELECT COUNT(*) FROM package_orders WHERE status = 'pending'")->fetchColumn();
                    $pendingProductOrders = $pdo->query("SELECT COUNT(*) FROM product_orders WHERE status IN ('pending','processing')")->fetchColumn();
                    echo (int)$pendingPackageOrders + (int)$pendingProductOrders;
                    ?>
                </h3>
                <small class="text-muted">Paket: <?= (int)$pendingPackageOrders ?> | Ürün: <?= (int)$pendingProductOrders ?></small>
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
                    <div class="col-sm-6 col-xl-3">
                        <a href="/admin/packages.php" class="btn btn-outline-primary w-100">Paketleri Yönet</a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="/admin/orders.php" class="btn btn-outline-primary w-100">Siparişler</a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="/admin/product-orders.php" class="btn btn-outline-primary w-100">Ürün Siparişleri</a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="/admin/users.php" class="btn btn-outline-primary w-100">Bayiler</a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="/admin/products.php" class="btn btn-outline-primary w-100">Ürünler &amp; Kategoriler</a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="/admin/balances.php" class="btn btn-outline-primary w-100">Bakiyeler</a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="/admin/woocommerce-import.php" class="btn btn-outline-primary w-100">WooCommerce CSV</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
