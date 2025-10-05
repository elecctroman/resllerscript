<?php
$title = 'E-PIN Market - Ana Sayfa';
require __DIR__ . '/partials/header.php';
?>
<div class="row mb-5 align-items-center">
    <div class="col-lg-6">
        <h1 class="display-5 fw-bold mb-3">Dijital Ürünler ve E-PIN Çözümleri</h1>
        <p class="lead text-muted">Hızlı, güvenli ve anında teslimat. Favori oyunlarınız ve yazılımlarınız için kodları saniyeler içinde alın.</p>
        <?php if (!is_authenticated()): ?>
            <div class="d-flex gap-2">
                <a href="/epin_client/public/register.php" class="btn btn-primary btn-lg"><i class="fa-solid fa-user-plus me-2"></i>Hemen Kayıt Ol</a>
                <a href="/epin_client/public/login.php" class="btn btn-outline-light btn-lg"><i class="fa-solid fa-right-to-bracket me-2"></i>Giriş Yap</a>
            </div>
        <?php else: ?>
            <a href="/epin_client/public/dashboard.php" class="btn btn-primary btn-lg"><i class="fa-solid fa-gauge me-2"></i>Panelime Git</a>
        <?php endif; ?>
    </div>
    <div class="col-lg-6 text-center">
        <img src="https://cdn.pixabay.com/photo/2016/12/13/22/40/code-1905226_1280.png" alt="E-PIN" class="img-fluid rounded-4 shadow" />
    </div>
</div>
<section class="mb-5">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h4 mb-0"><i class="fa-solid fa-store me-2 text-primary"></i>Popüler Ürünler</h2>
        <?php if (is_authenticated()): ?>
            <button class="btn btn-sm btn-outline-primary" id="refresh-products"><i class="fa-solid fa-rotate"></i> Yenile</button>
        <?php endif; ?>
    </div>
    <div id="product-list" class="row g-4"></div>
</section>
<?php require __DIR__ . '/partials/footer.php';
