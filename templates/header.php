<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use App\Helpers;

$user = $_SESSION['user'] ?? null;
$pageHeadline = $pageTitle ?? 'Panel';

$menuItems = [];

if ($user) {
    if ($user['role'] === 'admin') {
        $menuItems = [
            ['label' => 'Genel Bakış', 'href' => '/admin/dashboard.php', 'pattern' => '/admin/dashboard.php'],
            ['label' => 'Paketler', 'href' => '/admin/packages.php', 'pattern' => '/admin/packages.php'],
            ['label' => 'Siparişler', 'href' => '/admin/orders.php', 'pattern' => '/admin/orders.php'],
            ['label' => 'Ürünler', 'href' => '/admin/products.php', 'pattern' => '/admin/products.php'],
            ['label' => 'Bayiler', 'href' => '/admin/users.php', 'pattern' => '/admin/users.php'],
            ['label' => 'Bakiyeler', 'href' => '/admin/balances.php', 'pattern' => '/admin/balances.php'],
            ['label' => 'Destek', 'href' => '/admin/support.php', 'pattern' => '/admin/support.php'],
            ['label' => 'Mail Ayarları', 'href' => '/admin/settings-mail.php', 'pattern' => '/admin/settings-mail.php'],
        ];
    } else {
        $menuItems = [
            ['label' => 'Kontrol Paneli', 'href' => '/dashboard.php', 'pattern' => '/dashboard.php'],
            ['label' => 'Ürünler', 'href' => '/products.php', 'pattern' => '/products.php'],
            ['label' => 'Bakiyem', 'href' => '/balance.php', 'pattern' => '/balance.php'],
            ['label' => 'Destek', 'href' => '/support.php', 'pattern' => '/support.php'],
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
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <?php if ($user): ?>
        <aside class="app-sidebar">
            <div class="sidebar-brand">
                <a href="<?= $user['role'] === 'admin' ? '/admin/dashboard.php' : '/dashboard.php' ?>">Bayi Yönetim Sistemi</a>
            </div>
            <div class="sidebar-user">
                <div class="sidebar-user-name"><?= Helpers::sanitize($user['name']) ?></div>
                <div class="sidebar-user-role text-uppercase"><?= $user['role'] === 'admin' ? 'Yönetici' : 'Bayi' ?></div>
                <div class="sidebar-user-balance">
                    Bakiye: <strong>$<?= number_format((float)$user['balance'], 2, '.', ',') ?></strong>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul class="list-unstyled">
                    <?php foreach ($menuItems as $item): ?>
                        <li>
                            <a href="<?= $item['href'] ?>" class="sidebar-link <?= Helpers::isActive($item['pattern']) ? 'active' : '' ?>">
                                <?= Helpers::sanitize($item['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
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
