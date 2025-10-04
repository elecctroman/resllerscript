<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Services\AnalyticsService;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];
if (Auth::isAdminRole($user['role'])) {
    Helpers::redirect('/admin/dashboard.php');
}

$pageTitle = 'Bayi Analitiği';
$analytics = AnalyticsService::buildForUser((int)$user['id'], (string)$user['email']);

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">30 Günlük Sipariş & Gelir Trendleri</h5>
            </div>
            <div class="card-body">
                <canvas id="chart-orders" height="240"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Bakiye Erime Projeksiyonu</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-1">Ortalama günlük harcama: <strong><?= Helpers::formatCurrency($analytics['balance_projection']['average_daily_spend']) ?></strong></p>
                <p class="text-muted mb-3">Mevcut bakiye: <strong><?= Helpers::formatCurrency($analytics['balance_projection']['balance']) ?></strong></p>
                <?php if (!empty($analytics['balance_projection']['days_remaining'])): ?>
                    <div class="alert alert-warning mb-3">
                        <span class="fw-semibold">Tahmini kalan süre:</span>
                        <?= Helpers::sanitize((string)$analytics['balance_projection']['days_remaining']) ?> gün
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-3">Henüz anlamlı bir bakiye projeksiyonu üretmek için yeterli veri bulunmuyor.</div>
                <?php endif; ?>
                <p class="small text-muted mb-0">Harcamalarınızı kontrol altında tutmak için otomatik bakiye yükleme talimatı oluşturabilirsiniz.</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Ürün Bazlı Performans</h5>
                <span class="text-muted small">İlk 10 ürün</span>
            </div>
            <div class="card-body">
                <?php if ($analytics['top_products']): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Ürün</th>
                                    <th class="text-end">Sipariş</th>
                                    <th class="text-end">Gelir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analytics['top_products'] as $item): ?>
                                    <tr>
                                        <td><?= Helpers::sanitize($item['name']) ?></td>
                                        <td class="text-end"><?= Helpers::sanitize((string)$item['orders']) ?></td>
                                        <td class="text-end"><?= Helpers::formatCurrency($item['revenue']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Henüz sipariş verilmeyen ürünler bulunuyor.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Paket Sipariş Trendleri</h5>
            </div>
            <div class="card-body">
                <?php if ($analytics['package_orders']): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($analytics['package_orders'] as $row): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= Helpers::sanitize($row['order_day']) ?></span>
                                <span class="fw-semibold text-primary"><?= Helpers::formatCurrency((float)$row['revenue']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0">Son 30 günde paket siparişi bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(() => {
    const trend = <?= json_encode($analytics['order_trend']) ?>;
    const profitTrend = <?= json_encode($analytics['profit_trend']) ?>;
    const ctx = document.getElementById('chart-orders');
    if (!ctx) { return; }

    const labels = trend.map(item => item.day);
    const ordersData = trend.map(item => item.orders);
    const revenueData = trend.map(item => item.revenue);
    const profitData = profitTrend.map(item => item.profit);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Sipariş',
                    data: ordersData,
                    yAxisID: 'orders',
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,0.15)',
                    tension: 0.3,
                    fill: true,
                },
                {
                    label: 'Gelir',
                    data: revenueData,
                    yAxisID: 'revenue',
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25,135,84,0.1)',
                    tension: 0.3,
                    fill: true,
                },
                {
                    label: 'Kârlılık',
                    data: profitData,
                    yAxisID: 'revenue',
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255,193,7,0.1)',
                    tension: 0.3,
                    fill: false,
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            stacked: false,
            scales: {
                orders: {
                    type: 'linear',
                    position: 'left',
                    ticks: { precision: 0 },
                },
                revenue: {
                    type: 'linear',
                    position: 'right',
                    grid: { drawOnChartArea: false },
                }
            }
        }
    });
})();
</script>
<?php include __DIR__ . '/templates/footer.php';
