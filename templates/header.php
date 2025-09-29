<?php

use App\Helpers;
use App\Database;
use App\Lang;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$pageHeadline = isset($pageTitle) ? $pageTitle : 'Panel';

Lang::boot();

if (!isset($GLOBALS['app_lang_buffer_started'])) {
    $GLOBALS['app_lang_buffer_started'] = true;
    ob_start(function ($buffer) {
        return Lang::filterOutput($buffer);
    });
}

$menuSections = [];
$menuBadges = [];

if ($user && $user['role'] === 'admin') {
    try {
        $sidebarPdo = Database::connection();
        $activePackageOrders = (int)$sidebarPdo->query("SELECT COUNT(*) FROM package_orders WHERE status IN ('pending','paid')")
            ->fetchColumn();
        $activeProductOrders = (int)$sidebarPdo->query("SELECT COUNT(*) FROM product_orders WHERE status IN ('pending','processing')")
            ->fetchColumn();

        $menuBadges['/admin/orders.php'] = $activePackageOrders;
        $menuBadges['/admin/product-orders.php'] = $activeProductOrders;
    } catch (\Throwable $sidebarException) {
        $menuBadges = [];
    }
}

if ($user) {
    if ($user['role'] === 'admin') {
        $menuSections = [
            [
                'heading' => 'Genel',
                'items' => [
                    ['label' => 'Genel Bakış', 'href' => '/admin/dashboard.php', 'pattern' => '/admin/dashboard.php', 'icon' => 'bi-speedometer2'],
                    ['label' => 'Paketler', 'href' => '/admin/packages.php', 'pattern' => '/admin/packages.php', 'icon' => 'bi-box-seam'],
                    ['label' => 'Siparişler', 'href' => '/admin/orders.php', 'pattern' => '/admin/orders.php', 'icon' => 'bi-receipt', 'badge' => isset($menuBadges['/admin/orders.php']) ? (int)$menuBadges['/admin/orders.php'] : 0],
                    ['label' => 'Ürün Siparişleri', 'href' => '/admin/product-orders.php', 'pattern' => '/admin/product-orders.php', 'icon' => 'bi-basket', 'badge' => isset($menuBadges['/admin/product-orders.php']) ? (int)$menuBadges['/admin/product-orders.php'] : 0],
                    ['label' => 'Bayiler', 'href' => '/admin/users.php', 'pattern' => '/admin/users.php', 'icon' => 'bi-people'],
                ],
            ],
            [
                'heading' => 'Ürün Yönetimi',
                'items' => [
                    ['label' => 'Ürünler & Kategoriler', 'href' => '/admin/products.php', 'pattern' => '/admin/products.php', 'icon' => 'bi-box'],
                    ['label' => 'WooCommerce İçe Aktar', 'href' => '/admin/woocommerce-import.php', 'pattern' => '/admin/woocommerce-import.php', 'icon' => 'bi-filetype-csv'],
                ],
            ],
            [
                'heading' => 'Finans & Destek',
                'items' => [
                    ['label' => 'Bakiyeler', 'href' => '/admin/balances.php', 'pattern' => '/admin/balances.php', 'icon' => 'bi-cash-stack'],
                    ['label' => 'Destek', 'href' => '/admin/support.php', 'pattern' => '/admin/support.php', 'icon' => 'bi-life-preserver'],
                ],
            ],
            [
                'heading' => 'Ayarlar',
                'items' => [
                    ['label' => 'Mail Ayarları', 'href' => '/admin/settings-mail.php', 'pattern' => '/admin/settings-mail.php', 'icon' => 'bi-envelope-gear'],
                    ['label' => 'Telegram Entegrasyonu', 'href' => '/admin/settings-telegram.php', 'pattern' => '/admin/settings-telegram.php', 'icon' => 'bi-telegram'],
                    ['label' => 'Ödeme Methodları', 'href' => '/admin/settings-payments.php', 'pattern' => '/admin/settings-payments.php', 'icon' => 'bi-credit-card'],
                ],
            ],
        ];
    } else {
        $menuSections = [
            [
                'heading' => 'Bayi Paneli',
                'items' => [
                    ['label' => 'Kontrol Paneli', 'href' => '/dashboard.php', 'pattern' => '/dashboard.php', 'icon' => 'bi-speedometer2'],
                    ['label' => 'Ürünler', 'href' => '/products.php', 'pattern' => '/products.php', 'icon' => 'bi-box'],
                    ['label' => 'Siparişlerim', 'href' => '/orders.php', 'pattern' => '/orders.php', 'icon' => 'bi-receipt'],
                    ['label' => 'Bakiyem', 'href' => '/balance.php', 'pattern' => '/balance.php', 'icon' => 'bi-wallet2'],
                    ['label' => 'Destek', 'href' => '/support.php', 'pattern' => '/support.php', 'icon' => 'bi-life-preserver'],
                    ['label' => 'Profilim', 'href' => '/profile.php', 'pattern' => '/profile.php', 'icon' => 'bi-person'],
                ],
            ],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= Lang::htmlLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? Helpers::sanitize($pageTitle) . ' | ' : '' ?><?= Helpers::sanitize('Bayi Yönetim Sistemi') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <?php if ($user): ?>
        <aside class="app-sidebar">
            <div class="sidebar-brand">
                <a href="<?= $user['role'] === 'admin' ? '/admin/dashboard.php' : '/dashboard.php' ?>"><?= Helpers::sanitize('Bayi Yönetim Sistemi') ?></a>
            </div>
            <div class="sidebar-user">
                <div class="sidebar-user-name"><?= Helpers::sanitize($user['name']) ?></div>
                <div class="sidebar-user-role text-uppercase"><?= Helpers::sanitize($user['role'] === 'admin' ? 'Yönetici' : 'Bayi') ?></div>
                <div class="sidebar-user-balance">
                    <?= Helpers::sanitize('Bakiye') ?>:
                    <strong><?= Helpers::formatCurrency((float)$user['balance']) ?></strong>
                </div>
            </div>
            <nav class="sidebar-nav">
                <?php foreach ($menuSections as $section): ?>
                    <div class="sidebar-section">
                        <div class="sidebar-section-title"><?= Helpers::sanitize($section['heading']) ?></div>
                        <ul class="list-unstyled">
                            <?php foreach ($section['items'] as $item): ?>
                                <li>
                                    <?php $badge = isset($item['badge']) ? (int)$item['badge'] : 0; ?>
                                    <a href="<?= $item['href'] ?>" class="sidebar-link <?= Helpers::isActive($item['pattern']) ? 'active' : '' ?>">
                                        <?php if (!empty($item['icon'])): ?>
                                            <span class="sidebar-link-icon"><i class="<?= Helpers::sanitize($item['icon']) ?>"></i></span>
                                        <?php endif; ?>
                                        <span class="sidebar-link-text"><?= Helpers::sanitize($item['label']) ?></span>
                                        <?php if ($badge > 0): ?>
                                            <span class="sidebar-link-badge"><?= $badge ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer">
                <a href="/logout.php" class="btn btn-outline-light w-100"><?= Helpers::sanitize('Çıkış Yap') ?></a>
            </div>
        </aside>
    <?php endif; ?>
    <div class="app-main d-flex flex-column flex-grow-1">
        <?php if ($user): ?>
            <header class="app-topbar">
                <div>
                    <h1 class="h4 mb-1"><?= Helpers::sanitize($pageHeadline) ?></h1>
                    <p class="text-muted mb-0"><?= date('d F Y') ?></p>
                </div>
            </header>
        <?php endif; ?>
        <main class="app-content flex-grow-1 container-fluid">
