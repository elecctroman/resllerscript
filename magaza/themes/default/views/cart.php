<?php
$items = isset($cart['items']) && is_array($cart['items']) ? $cart['items'] : array();
$total = isset($cart['total']) ? (float) $cart['total'] : 0;
?>
<section class="cart-view">
    <h1 class="h3 mb-4">Sepetiniz</h1>
    <?php if ($items): ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Ürün</th>
                    <th class="text-center">Adet</th>
                    <th class="text-end">Fiyat</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?php echo htmlspecialchars($item['image'] ?? theme_asset('img/placeholder-16x9.svg'), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="rounded" width="72" height="40">
                                <div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php if (!empty($item['sku'])): ?>
                                        <div class="text-muted small">SKU: <?php echo htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="text-center"><?php echo isset($item['quantity']) ? (int) $item['quantity'] : 1; ?></td>
                        <td class="text-end">₺<?php echo number_format(isset($item['price']) ? (float) $item['price'] : 0, 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="fw-semibold">Toplam:</span>
            <span class="fs-4 fw-bold text-primary">₺<?php echo number_format($total, 2, ',', '.'); ?></span>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
            <a href="/magaza/index.php" class="btn btn-outline-secondary">Alışverişe devam et</a>
            <a href="/magaza/checkout.php" class="btn btn-primary">Ödemeye geç</a>
        </div>
    <?php else: ?>
        <div class="alert alert-info">Sepetinizde ürün bulunmuyor.</div>
        <a href="/magaza/index.php" class="btn btn-primary">Ürünleri incele</a>
    <?php endif; ?>
</section>
