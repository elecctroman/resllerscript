<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;
use App\Database;
use App\Reports\ReportService;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if ($user['role'] !== 'admin') {
    Helpers::redirect('/dashboard.php');
}

$pdo = Database::connection();
$pageTitle = 'Yönetici Paneli';

$monthlyOrderSummary = ReportService::getMonthlyOrderSummary($pdo, 6);
$monthlyBalanceSummary = ReportService::getMonthlyBalanceSummary($pdo, 6);

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
                    <div class="col-sm-6 col-xl-3">
                        <a href="/admin/packages.php" class="btn btn-outline-primary w-100">Paketleri Yönet</a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="/admin/orders.php" class="btn btn-outline-primary w-100">Siparişler</a>
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
    <div class="col-12 col-xxl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Aylık Sipariş ve Gelir</h5>
            </div>
            <div class="card-body">
                <canvas id="ordersRevenueChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-xxl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Aylık Bakiye Hareketleri</h5>
            </div>
            <div class="card-body">
                <canvas id="balanceChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const orderSummary = <?= json_encode($monthlyOrderSummary, JSON_UNESCAPED_UNICODE); ?>;
    const balanceSummary = <?= json_encode($monthlyBalanceSummary, JSON_UNESCAPED_UNICODE); ?>;

    const ordersRevenueCtx = document.getElementById('ordersRevenueChart');
    if (ordersRevenueCtx && typeof Chart !== 'undefined') {
        new Chart(ordersRevenueCtx, {
            type: 'bar',
            data: {
                labels: orderSummary.labels,
                datasets: [
                    {
                        label: 'Sipariş Adedi',
                        data: orderSummary.orders,
                        backgroundColor: 'rgba(13, 110, 253, 0.5)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1,
                        yAxisID: 'y-orders'
                    },
                    {
                        type: 'line',
                        label: 'Gelir ($)',
                        data: orderSummary.revenue,
                        borderColor: 'rgba(25, 135, 84, 1)',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        borderWidth: 3,
                        tension: 0.3,
                        yAxisID: 'y-revenue'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    'y-orders': {
                        position: 'left',
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    'y-revenue': {
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    }

    const balanceCtx = document.getElementById('balanceChart');
    if (balanceCtx && typeof Chart !== 'undefined') {
        new Chart(balanceCtx, {
            type: 'line',
            data: {
                labels: balanceSummary.labels,
                datasets: [
                    {
                        label: 'Yatırılan ($)',
                        data: balanceSummary.credits,
                        borderColor: 'rgba(13, 202, 240, 1)',
                        backgroundColor: 'rgba(13, 202, 240, 0.15)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Harcanan ($)',
                        data: balanceSummary.debits,
                        borderColor: 'rgba(220, 53, 69, 1)',
                        backgroundColor: 'rgba(220, 53, 69, 0.15)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Net ($)',
                        data: balanceSummary.net,
                        borderColor: 'rgba(25, 135, 84, 1)',
                        backgroundColor: 'rgba(25, 135, 84, 0.05)',
                        tension: 0.3,
                        borderDash: [5, 5],
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
</script>
<?php include __DIR__ . '/../templates/footer.php';
