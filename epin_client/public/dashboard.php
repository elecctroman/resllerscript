<?php
require_once __DIR__ . '/../includes/bootstrap.php';
redirect_if_not_authenticated();
$user = current_user();
$title = 'Müşteri Paneli';
require __DIR__ . '/partials/header.php';
?>
<div class="row g-4">
    <div class="col-md-4">
        <div class="card dashboard-card text-center">
            <div class="card-body">
                <div class="icon-circle bg-primary-subtle text-primary mb-3"><i class="fa-solid fa-wallet"></i></div>
                <h5 class="card-title">Güncel Bakiye</h5>
                <p class="display-6" id="balance-amount">₺<?= sanitize(number_format((float)$user['balance'], 2)) ?></p>
                <a class="btn btn-outline-primary btn-sm" href="/epin_client/public/wallet.php">Bakiye Yükle</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-center">
            <div class="card-body">
                <div class="icon-circle bg-success-subtle text-success mb-3"><i class="fa-solid fa-cart-shopping"></i></div>
                <h5 class="card-title">Toplam Sipariş</h5>
                <p class="display-6" id="total-orders">-</p>
                <a class="btn btn-outline-success btn-sm" href="/epin_client/public/orders.php">Siparişler</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-center">
            <div class="card-body">
                <div class="icon-circle bg-warning-subtle text-warning mb-3"><i class="fa-solid fa-ticket"></i></div>
                <h5 class="card-title">Açık Ticket</h5>
                <p class="display-6" id="open-tickets">-</p>
                <a class="btn btn-outline-warning btn-sm" href="/epin_client/public/support.php">Destek</a>
            </div>
        </div>
    </div>
</div>
<section class="mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Hızlı Satın Alma</h2>
        <button class="btn btn-sm btn-outline-primary" id="quick-refresh"><i class="fa-solid fa-rotate"></i> Yenile</button>
    </div>
    <div class="row g-3" id="quick-products"></div>
</section>
<?php require __DIR__ . '/partials/footer.php';
