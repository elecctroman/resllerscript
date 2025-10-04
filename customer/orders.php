<?php
require __DIR__ . '/../bootstrap.php';

use App\Customers\CustomerAuth;
use App\Customers\OrderService;
use App\Helpers;

$customer = CustomerAuth::ensureCustomer();
$orders = OrderService::listForCustomer((int)$customer['id'], 100);
$pageTitle = 'Siparişler';
require __DIR__ . '/../templates/customer-header.php';
?>
<div class="card customer-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Sipariş Geçmişi</h5>
        <a href="/customer/new-order.php" class="btn btn-primary btn-sm"><i class="bi bi-cart-plus me-2"></i>Yeni Sipariş</a>
    </div>
    <div class="card-body">
        <?php if ($orders): ?>
            <div class="table-responsive">
                <table class="table customer-table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ürün</th>
                            <th>Adet</th>
                            <th>Tutar</th>
                            <th>Durum</th>
                            <th>Oluşturulma</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= (int)$order['id'] ?></td>
                            <td><?= Helpers::sanitize($order['product_name']) ?></td>
                            <td><?= (int)$order['quantity'] ?></td>
                            <td><?= number_format((float)$order['total_price'], 2, ',', '.') ?> <?= Helpers::sanitize($customer['currency'] ?? 'TRY') ?></td>
                            <td><span class="badge text-bg-secondary text-capitalize"><?= Helpers::sanitize($order['status']) ?></span></td>
                            <td><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($order['created_at']))) ?></td>
                        </tr>
                        <?php if (!empty($order['license_key'])): ?>
                            <tr class="table-light">
                                <td colspan="6">
                                    <div class="fw-semibold mb-1">Teslimat İçeriği</div>
                                    <pre class="mb-0 small"><?= Helpers::sanitize($order['license_key']) ?></pre>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">Henüz sipariş oluşturulmamış.</p>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../templates/customer-footer.php';
