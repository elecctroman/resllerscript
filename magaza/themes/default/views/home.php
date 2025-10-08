<?php
$products = isset($products) && is_array($products) ? $products : array();
?>
<section class="storefront-hero py-5 text-center text-lg-start">
    <div class="row align-items-center gy-4">
        <div class="col-lg-6">
            <h1 class="display-5 fw-bold mb-3">Dijital ürünlerinizi saniyeler içinde satın alın</h1>
            <p class="lead text-muted">Binlerce lisans, abonelik ve anahtar seçeneğini güvenle keşfedin. Otomatik teslimat desteği ile müşterilerinize bekletmeden ulaştırın.</p>
            <div class="d-flex flex-wrap gap-3 mt-4">
                <a href="/magaza/category.php" class="btn btn-primary btn-lg">Ürünleri keşfet</a>
                <a href="/bayi/login.php" class="btn btn-outline-primary btn-lg">Bayi ol</a>
            </div>
        </div>
        <div class="col-lg-6 text-center">
            <img src="<?php echo htmlspecialchars(theme_asset('img/placeholder-16x9.svg'), ENT_QUOTES, 'UTF-8'); ?>" alt="Mağaza" class="img-fluid rounded-4 shadow-sm">
        </div>
    </div>
</section>

<section class="storefront-products py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h2 class="h4 mb-0">Öne çıkan ürünler</h2>
        <a href="/magaza/category.php" class="btn btn-link p-0">Tüm ürünleri gör</a>
    </div>
    <div class="row g-4 catalog-grid">
        <?php if ($products): ?>
            <?php foreach ($products as $product): ?>
                <div class="col-sm-6 col-md-4 col-lg-3 d-flex">
                    <?php store_include(theme_partial('product-card'), array('product' => $product)); ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">Şu anda listelenecek ürün bulunamadı.</div>
            </div>
        <?php endif; ?>
    </div>
</section>
