<?php

use App\Helpers;
use App\Auth;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;
$pageHeadline = $pageTitle ?? 'Panel';

$menuSections = [];

if ($user) {
    if (Auth::userHasPermission($user, 'access_admin_panel')) {
        $rawSections = [
            [
                'heading' => 'Genel',
                'items' => [
                    ['label' => 'Genel Bakış', 'href' => '/admin/dashboard.php', 'pattern' => '/admin/dashboard.php', 'icon' => 'bi-speedometer2', 'permission' => 'access_admin_panel'],
                    ['label' => 'Paketler', 'href' => '/admin/packages.php', 'pattern' => '/admin/packages.php', 'icon' => 'bi-box-seam', 'permission' => 'manage_products'],
                    ['label' => 'Siparişler', 'href' => '/admin/orders.php', 'pattern' => '/admin/orders.php', 'icon' => 'bi-receipt', 'permission' => 'manage_orders'],
                    ['label' => 'Bayiler', 'href' => '/admin/users.php', 'pattern' => '/admin/users.php', 'icon' => 'bi-people', 'permission' => 'manage_users'],
                    ['label' => 'Aktivite Kayıtları', 'href' => '/admin/activity-logs.php', 'pattern' => '/admin/activity-logs.php', 'icon' => 'bi-clipboard-data', 'permission' => 'view_audit_logs'],
                ],
            ],
            [
                'heading' => 'Ürün Yönetimi',
                'items' => [
                    ['label' => 'Ürünler & Kategoriler', 'href' => '/admin/products.php', 'pattern' => '/admin/products.php', 'icon' => 'bi-box', 'permission' => 'manage_products'],
                    ['label' => 'WooCommerce İçe Aktar', 'href' => '/admin/woocommerce-import.php', 'pattern' => '/admin/woocommerce-import.php', 'icon' => 'bi-filetype-csv', 'permission' => 'import_products'],
                ],
            ],
            [
                'heading' => 'Finans & Destek',
                'items' => [
                    ['label' => 'Bakiyeler', 'href' => '/admin/balances.php', 'pattern' => '/admin/balances.php', 'icon' => 'bi-cash-stack', 'permission' => 'manage_finance'],
                    ['label' => 'Destek', 'href' => '/admin/support.php', 'pattern' => '/admin/support.php', 'icon' => 'bi-life-preserver', 'permission' => 'manage_support'],
                ],
            ],
            [
                'heading' => 'Ayarlar',
                'items' => [
                    ['label' => 'Mail Ayarları', 'href' => '/admin/settings-mail.php', 'pattern' => '/admin/settings-mail.php', 'icon' => 'bi-envelope-gear', 'permission' => 'manage_settings'],
                ],
            ],
        ];

        foreach ($rawSections as $section) {
            $items = array_filter($section['items'], static function (array $item) use ($user): bool {
                return empty($item['permission']) || Auth::userHasPermission($user, $item['permission']);
            });

            if ($items) {
                $section['items'] = array_values($items);
                $menuSections[] = $section;
            }
        }
    } else {
        $menuSections = [
            [
                'heading' => 'Bayi Paneli',
                'items' => [
                    ['label' => 'Kontrol Paneli', 'href' => '/dashboard.php', 'pattern' => '/dashboard.php', 'icon' => 'bi-speedometer2'],
                    ['label' => 'Ürünler', 'href' => '/products.php', 'pattern' => '/products.php', 'icon' => 'bi-box'],
                    ['label' => 'Bakiyem', 'href' => '/balance.php', 'pattern' => '/balance.php', 'icon' => 'bi-wallet2'],
                    ['label' => 'Destek', 'href' => '/support.php', 'pattern' => '/support.php', 'icon' => 'bi-life-preserver'],
                ],
            ],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? Helpers::sanitize($pageTitle) . ' | ' : '' ?>Bayi Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <?php if ($user): ?>
        <aside class="app-sidebar">
            <div class="sidebar-brand">
                <a href="<?= Auth::userHasPermission($user, 'access_admin_panel') ? '/admin/dashboard.php' : '/dashboard.php' ?>">Bayi Yönetim Sistemi</a>
            </div>
            <div class="sidebar-user">
                <div class="sidebar-user-name"><?= Helpers::sanitize($user['name']) ?></div>
                <div class="sidebar-user-role text-uppercase"><?= Helpers::sanitize(Auth::roleLabel($user['role'])) ?></div>
                <div class="sidebar-user-balance">
                    Bakiye: <strong>$<?= number_format((float)$user['balance'], 2, '.', ',') ?></strong>
                </div>
            </div>
            <nav class="sidebar-nav">
                <?php foreach ($menuSections as $section): ?>
                    <div class="sidebar-section">
                        <div class="sidebar-section-title"><?= Helpers::sanitize($section['heading']) ?></div>
                        <ul class="list-unstyled">
                            <?php foreach ($section['items'] as $item): ?>
                                <li>
                                    <a href="<?= $item['href'] ?>" class="sidebar-link <?= Helpers::isActive($item['pattern']) ? 'active' : '' ?>">
                                        <?php if (!empty($item['icon'])): ?>
                                            <span class="sidebar-link-icon"><i class="<?= Helpers::sanitize($item['icon']) ?>"></i></span>
                                        <?php endif; ?>
                                        <span class="sidebar-link-text"><?= Helpers::sanitize($item['label']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer">
                <a href="/logout.php" class="btn btn-outline-light w-100">Çıkış Yap</a>
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
