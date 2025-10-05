<?php
require_once __DIR__ . '/../includes/bootstrap.php';
redirect_if_not_authenticated();
$title = 'Siparişlerim';
require __DIR__ . '/partials/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1">Sipariş Geçmişi</h1>
        <p class="text-muted mb-0">Tüm satın alımlarınız ve teslim edilen PIN kodları</p>
    </div>
    <button class="btn btn-outline-primary" id="orders-refresh"><i class="fa-solid fa-rotate"></i> Yenile</button>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle" id="orders-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ürün</th>
                        <th>Tarih</th>
                        <th>Durum</th>
                        <th>E-PIN</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="text-center text-muted" id="orders-empty">Henüz siparişiniz bulunmuyor.</div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
