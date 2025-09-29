<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Database;
use App\Reporting;

Auth::requireRoles(array('super_admin', 'admin', 'finance'));

$user = $_SESSION['user'];
$pdo = Database::connection();
$pageTitle = 'Yönetici Paneli';

$totalResellers = (int)$pdo->query("SELECT COUNT(*) AS total FROM users WHERE role = 'reseller'")->fetchColumn();
$pendingPackageOrders = (int)$pdo->query("SELECT COUNT(*) FROM package_orders WHERE status = 'pending'")->fetchColumn();
$pendingProductOrders = (int)$pdo->query("SELECT COUNT(*) FROM product_orders WHERE status IN ('pending','processing')")->fetchColumn();
$activePackages = (int)$pdo->query('SELECT COUNT(*) FROM packages WHERE is_active = 1')->fetchColumn();
$openTickets = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status != 'closed'")->fetchColumn();

$monthlyOverview = Reporting::monthlyOverview(6);
$monthlyRows = $monthlyOverview['months'];
$monthlySummary = $monthlyOverview['summary'];

$chartLabels = array();
$chartOrderCounts = array();
$chartRevenue = array();
$chartCredits = array();
$chartDebits = array();

foreach ($monthlyRows as $month) {
    $chartLabels[] = $month['label'];
    $chartOrderCounts[] = (int)$month['package_orders'] + (int)$month['product_orders'];
    $chartRevenue[] = round((float)$month['revenue'], 2);
    $chartCredits[] = round((float)$month['balance_credits'], 2);
    $chartDebits[] = round((float)$month['balance_debits'], 2);
}

$GLOBALS['pageScripts'] = isset($GLOBALS['pageScripts']) && is_array($GLOBALS['pageScripts']) ? $GLOBALS['pageScripts'] : array();
$GLOBALS['pageScripts'][] = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';

$chartConfig = json_encode(array(
    'labels' => $chartLabels,
    'orders' => $chartOrderCounts,
    'revenue' => $chartRevenue,
    'credits' => $chartCredits,
    'debits' => $chartDebits,
), JSON_UNESCAPED_SLASHES);

$inlineScripts = isset($GLOBALS['pageInlineScripts']) && is_array($GLOBALS['pageInlineScripts']) ? $GLOBALS['pageInlineScripts'] : array();
$inlineScripts[] = 'document.addEventListener("DOMContentLoaded", function () {\n    var chartTarget = document.getElementById("adminOverviewChart");\n    if (!chartTarget) {\n        return;\n    }\n\n    var payload = ' . $chartConfig . ';\n    new Chart(chartTarget, {\n        type: "line",\n        data: {\n            labels: payload.labels,\n            datasets: [\n                {\n                    label: "Toplam Sipariş",\n                    data: payload.orders,\n                    borderColor: "#0d6efd",\n                    backgroundColor: "rgba(13,110,253,0.15)",\n                    tension: 0.3,\n                    fill: true,\n                    yAxisID: "y"\n                },\n                {\n                    label: "Gelir (USD)",\n                    data: payload.revenue,\n                    borderColor: "#198754",\n                    backgroundColor: "rgba(25,135,84,0.15)",\n                    tension: 0.3,\n                    fill: true,\n                    yAxisID: "y1"\n                },\n                {\n                    label: "Bakiye Kredileri",\n                    data: payload.credits,\n                    borderColor: "#0dcaf0",\n                    backgroundColor: "rgba(13,202,240,0.15)",\n                    tension: 0.3,\n                    fill: true,\n                    hidden: true,\n                    yAxisID: "y1"\n                },\n                {\n                    label: "Bakiye Borçları",\n                    data: payload.debits,\n                    borderColor: "#dc3545",\n                    backgroundColor: "rgba(220,53,69,0.15)",\n                    tension: 0.3,\n                    fill: true,\n                    hidden: true,\n                    yAxisID: "y1"\n                }\n            ]\n        },\n        options: {\n            responsive: true,\n            interaction: {\n                mode: "index",\n                intersect: false\n            },\n            stacked: false,\n            plugins: {\n                legend: { position: "bottom" }\n            },\n            scales: {\n                y: {\n                    type: "linear",\n                    display: true,\n                    position: "left",\n                    beginAtZero: true\n                },\n                y1: {\n                    type: "linear",\n                    display: true,\n                    position: "right",\n                    beginAtZero: true,\n                    grid: { drawOnChartArea: false }\n                }\n            }\n        }\n    });\n});';
$GLOBALS['pageInlineScripts'] = $inlineScripts;

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="mb-1"><?= Helpers::sanitize('Merhaba') ?>, <?= Helpers::sanitize($user['name']) ?></h4>
                    <p class="text-muted mb-0"><?= Helpers::sanitize('Sistemi buradan yönetebilir, bayilerinizi ve siparişleri takip edebilirsiniz.') ?></p>
                </div>
                <span class="badge bg-success rounded-pill fs-6"><?= Helpers::sanitize('Toplam Bakiye') ?>: <?= Helpers::sanitize(Helpers::formatCurrency((float)$user['balance'])) ?></span>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-uppercase text-muted">Toplam Bayi</h6>
                <h3 class="mb-0"><?= $totalResellers ?></h3>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-uppercase text-muted">Bekleyen Sipariş</h6>
                <h3 class="mb-0"><?= $pendingPackageOrders + $pendingProductOrders ?></h3>
                <small class="text-muted">Paket: <?= $pendingPackageOrders ?> | Ürün: <?= $pendingProductOrders ?></small>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-uppercase text-muted">Aktif Paket</h6>
                <h3 class="mb-0"><?= $activePackages ?></h3>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-uppercase text-muted">Açık Destek</h6>
                <h3 class="mb-0"><?= $openTickets ?></h3>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Aylık Performans</h5>
                <small class="text-muted">Son 6 ay</small>
            </div>
            <div class="card-body">
                <canvas id="adminOverviewChart" height="160"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Genel Özet</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-uppercase text-muted">Siparişler</h6>
                            <p class="fs-4 mb-1"><?= $monthlySummary['package_orders'] + $monthlySummary['product_orders'] ?></p>
                            <small class="text-muted">Paket: <?= $monthlySummary['package_orders'] ?> · Ürün: <?= $monthlySummary['product_orders'] ?></small>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-uppercase text-muted">Gelir</h6>
                            <p class="fs-4 mb-1"><?= Helpers::sanitize(Helpers::formatCurrency($monthlySummary['revenue'])) ?></p>
                            <small class="text-muted">Tamamlanan siparişler</small>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-uppercase text-muted">Bakiye Kredileri</h6>
                            <p class="fs-4 mb-1"><?= Helpers::sanitize(Helpers::formatCurrency($monthlySummary['balance_credits'])) ?></p>
                            <small class="text-muted">Son 6 aydaki toplam krediler</small>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-uppercase text-muted">Bakiye Borçları</h6>
                            <p class="fs-4 mb-1"><?= Helpers::sanitize(Helpers::formatCurrency($monthlySummary['balance_debits'])) ?></p>
                            <small class="text-muted">Son 6 aydaki toplam borçlar</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Aylık Detaylar</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Ay</th>
                            <th class="text-end">Sipariş</th>
                            <th class="text-end">Gelir</th>
                            <th class="text-end">Kredi</th>
                            <th class="text-end">Borç</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($monthlyRows as $month): ?>
                        <tr>
                            <td><?= Helpers::sanitize($month['label']) ?></td>
                            <td class="text-end">
                                <?= (int)$month['package_orders'] + (int)$month['product_orders'] ?>
                            </td>
                            <td class="text-end">
                                <?= Helpers::sanitize(Helpers::formatCurrency((float)$month['revenue'])) ?>
                            </td>
                            <td class="text-end">
                                <?= Helpers::sanitize(Helpers::formatCurrency((float)$month['balance_credits'])) ?>
                            </td>
                            <td class="text-end">
                                <?= Helpers::sanitize(Helpers::formatCurrency((float)$month['balance_debits'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
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
                        <a href="/admin/orders.php" class="btn btn-outline-primary w-100">Paket Siparişleri</a>
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
                    <div class="col-sm-6 col-xl-3">
                        <a href="/admin/reports.php" class="btn btn-outline-primary w-100">Raporlar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
