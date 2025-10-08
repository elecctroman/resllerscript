<?php
$order = isset($order) && is_array($order) ? $order : array();
?>
<section class="order-summary">
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <div class="mb-4">
                <span class="display-4 text-success">&#10003;</span>
            </div>
            <h1 class="h3 mb-3">Siparişiniz alındı!</h1>
            <p class="text-muted">Sipariş numaranız <strong>#<?php echo htmlspecialchars($order['reference'] ?? '000000', ENT_QUOTES, 'UTF-8'); ?></strong>. Detaylar e-posta adresinize gönderildi.</p>
            <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                <a href="<?php echo htmlspecialchars(store_url(''), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">Alışverişe devam et</a>
                <a href="/orders.php" class="btn btn-outline-primary">Siparişlerim</a>
            </div>
        </div>
    </div>
</section>
