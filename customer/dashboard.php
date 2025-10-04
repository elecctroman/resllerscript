<?php
require __DIR__ . '/../bootstrap.php';

use App\Customers\CustomerAuth;
use App\Customers\OrderService;
use App\Customers\WalletService;
use App\Database;
use App\Helpers;

$customer = CustomerAuth::ensureCustomer();

$pdo = Database::connection();

$summaryStmt = $pdo->prepare('SELECT COUNT(*) AS total_orders, SUM(total_price) AS total_spent FROM customer_orders WHERE customer_id = :customer');
$summaryStmt->execute(array(':customer' => $customer['id']));
$summary = $summaryStmt->fetch() ?: array('total_orders' => 0, 'total_spent' => 0);

$ticketStmt = $pdo->prepare('SELECT COUNT(*) FROM customer_tickets WHERE customer_id = :customer AND status = "acik"');
$ticketStmt->execute(array(':customer' => $customer['id']));
$openTickets = (int)$ticketStmt->fetchColumn();

$recentOrders = OrderService::listForCustomer((int)$customer['id'], 5);
$walletHistory = WalletService::history((int)$customer['id'], 5);

$pageTitle = 'Dashboard';
require __DIR__ . '/../templates/customer-header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card customer-card p-4">
            <div class="customer-metric-title">Bakiye</div>
            <div class="customer-metric-value">
                <?= number_format((float)$customer['balance'], 2, ',', '.') ?> <?= Helpers::sanitize($customer['currency'] ?? 'TRY') ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card customer-card p-4">
            <div class="customer-metric-title">Toplam Sipariş</div>
            <div class="customer-metric-value"><?= (int)($summary['total_orders'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card customer-card p-4">
            <div class="customer-metric-title">Toplam Harcama</div>
            <div class="customer-metric-value">
                <?= number_format((float)($summary['total_spent'] ?? 0), 2, ',', '.') ?> <?= Helpers::sanitize($customer['currency'] ?? 'TRY') ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card customer-card p-4">
            <div class="customer-metric-title">Açık Destek</div>
            <div class="customer-metric-value"><?= $openTickets ?></div>
        </div>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card customer-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Son Siparişler</h5>
                <a href="/customer/orders.php" class="btn btn-link btn-sm">Tümü</a>
            </div>
            <div class="card-body">
                <?php if ($recentOrders): ?>
                    <div class="table-responsive">
                        <table class="table customer-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Ürün</th>
                                    <th>Durum</th>
                                    <th>Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>#<?= (int)$order['id'] ?></td>
                                    <td><?= Helpers::sanitize($order['product_name']) ?></td>
                                    <td><span class="badge text-bg-secondary text-capitalize"><?= Helpers::sanitize($order['status']) ?></span></td>
                                    <td><?= number_format((float)$order['total_price'], 2, ',', '.') ?> <?= Helpers::sanitize($customer['currency'] ?? 'TRY') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Henüz sipariş oluşturmadınız.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card customer-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Cüzdan Hareketleri</h5>
                <a href="/customer/wallet.php" class="btn btn-link btn-sm">Detay</a>
            </div>
            <div class="card-body">
                <?php if ($walletHistory): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($walletHistory as $log): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-capitalize"><?= Helpers::sanitize($log['type']) ?></div>
                                    <small class="text-muted"><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($log['created_at']))) ?></small>
                                </div>
                                <span class="fw-semibold<?= $log['type'] === 'ekleme' ? ' text-success' : ' text-danger' ?>">
                                    <?= $log['type'] === 'ekleme' ? '+' : '-' ?><?= number_format((float)$log['amount'], 2, ',', '.') ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0">Cüzdan hareketiniz bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card customer-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Hızlı İşlemler</h5>
            </div>
            <div class="card-body d-grid gap-2">
                <a href="/customer/new-order.php" class="btn btn-primary"><i class="bi bi-cart-plus me-2"></i>Yeni Sipariş Oluştur</a>
                <a href="/customer/wallet.php#topup" class="btn btn-outline-primary"><i class="bi bi-wallet2 me-2"></i>Bakiye Yükle</a>
                <a href="/customer/support.php" class="btn btn-outline-secondary"><i class="bi bi-life-preserver me-2"></i>Destek Talebi Aç</a>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../templates/customer-footer.php';
