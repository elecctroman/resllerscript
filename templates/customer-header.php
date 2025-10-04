<?php
use App\Helpers;
use App\Lang;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

Lang::boot();

$siteName = Helpers::siteName();
$pageTitle = isset($pageTitle) ? $pageTitle : 'Müşteri Paneli';
$customer = isset($_SESSION['customer']) ? $_SESSION['customer'] : null;
$theme = isset($_COOKIE['customer_theme']) && $_COOKIE['customer_theme'] === 'dark' ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="<?= Lang::htmlLocale() ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Helpers::sanitize($pageTitle) ?> | <?= Helpers::sanitize($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/customer.css">
</head>
<body class="customer-app customer-app-<?= $theme ?>">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="/customer/dashboard.php"><?= Helpers::sanitize($siteName) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#customerSidebar" aria-controls="customerSidebar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-light btn-sm" id="customerThemeToggle" type="button"><i class="bi bi-moon-stars"></i></button>
            <?php if ($customer): ?>
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i><?= Helpers::sanitize($customer['name']) ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="/customer/profile.php"><i class="bi bi-person-lines-fill me-2"></i>Profil</a>
                        <a class="dropdown-item" href="/customer/wallet.php"><i class="bi bi-wallet2 me-2"></i>Cüzdan</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="/customer/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Çıkış</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
<div class="offcanvas offcanvas-start text-bg-<?= $theme === 'dark' ? 'dark' : 'light' ?>" tabindex="-1" id="customerSidebar">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Menü</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <nav class="customer-sidebar">
            <a href="/customer/dashboard.php" class="customer-sidebar-link<?= Helpers::currentPath() === '/customer/dashboard.php' ? ' active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="/customer/orders.php" class="customer-sidebar-link<?= Helpers::currentPath() === '/customer/orders.php' ? ' active' : '' ?>"><i class="bi bi-receipt me-2"></i>Siparişler</a>
            <a href="/customer/new-order.php" class="customer-sidebar-link<?= Helpers::currentPath() === '/customer/new-order.php' ? ' active' : '' ?>"><i class="bi bi-cart-plus me-2"></i>Yeni Sipariş</a>
            <a href="/customer/profile.php" class="customer-sidebar-link<?= Helpers::currentPath() === '/customer/profile.php' ? ' active' : '' ?>"><i class="bi bi-person me-2"></i>Profil</a>
            <a href="/customer/wallet.php" class="customer-sidebar-link<?= Helpers::currentPath() === '/customer/wallet.php' ? ' active' : '' ?>"><i class="bi bi-wallet2 me-2"></i>Cüzdan</a>
            <a href="/customer/support.php" class="customer-sidebar-link<?= Helpers::currentPath() === '/customer/support.php' ? ' active' : '' ?>"><i class="bi bi-life-preserver me-2"></i>Destek</a>
            <a href="/customer/logout.php" class="customer-sidebar-link text-danger"><i class="bi bi-box-arrow-right me-2"></i>Çıkış</a>
        </nav>
    </div>
</div>
<main class="customer-main container-fluid">
    <div class="customer-main-inner">
