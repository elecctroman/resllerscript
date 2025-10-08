<?php
$menuItems = array(
    array('label' => 'Ana Sayfa', 'href' => '/magaza/index.php'),
    array('label' => 'Kategoriler', 'href' => '/magaza/category.php'),
    array('label' => 'Sepet', 'href' => '/magaza/cart.php'),
    array('label' => 'Destek', 'href' => '/support.php'),
);
$logo = theme_asset('img/placeholder-16x9.svg');
$currentPath = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
?>
<header class="storefront-header border-bottom">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary px-3">
        <div class="container-fluid px-0">
            <a class="navbar-brand d-flex align-items-center" href="/magaza/index.php">
                <img src="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>" alt="Site Logosu" class="storefront-logo me-2">
                <span class="visually-hidden">Mağaza</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#storefrontNavbar" aria-controls="storefrontNavbar" aria-expanded="false" aria-label="Menüyü Aç">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="storefrontNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-lg-2">
                    <?php foreach ($menuItems as $item): ?>
                        <?php
                        $isActive = $currentPath === $item['href'];
                        ?>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $isActive ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <a href="/bayi/login.php" class="btn btn-outline-light btn-sm">Bayi Girişi</a>
                    <a href="/admin/login.php" class="btn btn-light btn-sm text-primary">Admin</a>
                </div>
            </div>
        </div>
    </nav>
</header>
