<?php

use App\Auth;
use App\Helpers;
use App\Database;
use App\Lang;
use App\FeatureToggle;
use App\ResellerPolicy;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$pageHeadline = isset($pageTitle) ? $pageTitle : 'Panel';

Lang::boot();

$siteName = Helpers::siteName();
$siteTagline = Helpers::siteTagline();
$metaDescription = Helpers::seoDescription();
$metaKeywords = Helpers::seoKeywords();

$lowBalanceNotice = null;
if ($user) {
    $lowBalanceNotice = ResellerPolicy::lowBalanceNotice($user);
}

if (!isset($GLOBALS['app_lang_buffer_started'])) {
    $GLOBALS['app_lang_buffer_started'] = true;
    ob_start(function ($buffer) {
        return Lang::filterOutput($buffer);
    });
}

$menuSections = array();
$menuBadges = array();
$currentPath = Helpers::currentPath();
$isAdminArea = false;
$isAdminRole = $user ? Auth::isAdminRole($user['role']) : false;

if ($isAdminRole) {
    $isAdminArea = strpos($currentPath, '/admin/') === 0;

    try {
        $sidebarPdo = Database::connection();

        if (Auth::userHasRole($user, array('super_admin', 'admin', 'support'))) {
            $menuBadges['/admin/orders.php'] = (int)$sidebarPdo
                ->query("SELECT COUNT(*) FROM package_orders WHERE status IN ('pending','paid')")
                ->fetchColumn();
            $menuBadges['/admin/product-orders.php'] = (int)$sidebarPdo
                ->query("SELECT COUNT(*) FROM product_orders WHERE status IN ('pending','processing')")
                ->fetchColumn();
        }
    } catch (\Throwable $sidebarException) {
        $menuBadges = array();
    }
}

if ($user) {
    if ($isAdminRole && $isAdminArea) {
        $adminSections = array(
            array(
                'heading' => 'Genel',
                'items' => array(
                    array('label' => 'Genel Bakış', 'href' => '/admin/dashboard.php', 'pattern' => '/admin/dashboard.php', 'icon' => 'bi-speedometer2', 'roles' => Auth::adminRoles()),
                    array('label' => 'Raporlar', 'href' => '/admin/reports.php', 'pattern' => '/admin/reports.php', 'icon' => 'bi-graph-up', 'roles' => array('super_admin', 'admin', 'finance')),
                    array('label' => 'Paketler', 'href' => '/admin/packages.php', 'pattern' => '/admin/packages.php', 'icon' => 'bi-box-seam', 'roles' => array('super_admin', 'admin')),
                    array('label' => 'Siparişler', 'href' => '/admin/orders.php', 'pattern' => '/admin/orders.php', 'icon' => 'bi-receipt', 'roles' => array('super_admin', 'admin', 'support'), 'badge' => isset($menuBadges['/admin/orders.php']) ? (int)$menuBadges['/admin/orders.php'] : 0),
                    array('label' => 'Ürün Siparişleri', 'href' => '/admin/product-orders.php', 'pattern' => '/admin/product-orders.php', 'icon' => 'bi-basket', 'roles' => array('super_admin', 'admin', 'support'), 'badge' => isset($menuBadges['/admin/product-orders.php']) ? (int)$menuBadges['/admin/product-orders.php'] : 0),
                    array('label' => 'Bayiler', 'href' => '/admin/users.php', 'pattern' => '/admin/users.php', 'icon' => 'bi-people', 'roles' => array('super_admin', 'admin')),
                ),
            ),
            array(
                'heading' => 'Ürün Yönetimi',
                'items' => array(
                    array('label' => 'Ürünler', 'href' => '/admin/products.php', 'pattern' => '/admin/products.php', 'icon' => 'bi-box', 'roles' => array('super_admin', 'admin', 'content')),
                    array('label' => 'Kategoriler', 'href' => '/admin/categories.php', 'pattern' => '/admin/categories.php', 'icon' => 'bi-diagram-3', 'roles' => array('super_admin', 'admin', 'content')),
                ),
            ),
            array(
                'heading' => 'WooCommerce',
                'items' => array(
                    array('label' => 'İçe Aktar', 'href' => '/admin/woocommerce-import.php', 'pattern' => '/admin/woocommerce-import.php', 'icon' => 'bi-file-arrow-up', 'roles' => array('super_admin', 'admin', 'content')),
                    array('label' => 'Dışa Aktar', 'href' => '/admin/woocommerce-export.php', 'pattern' => '/admin/woocommerce-export.php', 'icon' => 'bi-file-arrow-down', 'roles' => array('super_admin', 'admin', 'content')),
                ),
            ),
            array(
                'heading' => 'Finans & Destek',
                'items' => array(
                    array('label' => 'Bakiyeler', 'href' => '/admin/balances.php', 'pattern' => '/admin/balances.php', 'icon' => 'bi-cash-stack', 'roles' => array('super_admin', 'admin', 'finance')),
                    array('label' => 'Destek', 'href' => '/admin/support.php', 'pattern' => '/admin/support.php', 'icon' => 'bi-life-preserver', 'roles' => array('super_admin', 'admin', 'support')),
                ),
            ),
            array(
                'heading' => 'Premium',
                'items' => array(
                    array('label' => 'Premium Modüller', 'href' => '/admin/premium-modules.php', 'pattern' => '/admin/premium-modules.php', 'icon' => 'bi-gem', 'roles' => array('super_admin', 'admin')),
                    array('label' => 'Satın Almalar', 'href' => '/admin/premium-module-purchases.php', 'pattern' => '/admin/premium-module-purchases.php', 'icon' => 'bi-receipt-cutoff', 'roles' => array('super_admin', 'admin', 'finance')),
                ),
            ),
            array(
                'heading' => 'Ayarlar',
                'items' => array(
                    array('label' => 'Genel Ayarlar', 'href' => '/admin/settings-general.php', 'pattern' => '/admin/settings-general.php', 'icon' => 'bi-gear', 'roles' => array('super_admin', 'admin')),
                    array('label' => 'Telegram Entegrasyonu', 'href' => '/admin/settings-telegram.php', 'pattern' => '/admin/settings-telegram.php', 'icon' => 'bi-telegram', 'roles' => array('super_admin', 'admin')),
                    array('label' => 'Ödeme Methodları', 'href' => '/admin/settings-payments.php', 'pattern' => '/admin/settings-payments.php', 'icon' => 'bi-credit-card', 'roles' => array('super_admin', 'admin', 'finance')),
                ),
            ),
            array(
                'heading' => 'Denetim',
                'items' => array(
                    array('label' => 'Aktivite Kayıtları', 'href' => '/admin/activity-logs.php', 'pattern' => '/admin/activity-logs.php', 'icon' => 'bi-clipboard-data', 'roles' => array('super_admin', 'admin')),
                ),
            ),
        );

        $menuSections = array();
        foreach ($adminSections as $section) {
            $items = array();
            foreach ($section['items'] as $item) {
                $allowedRoles = isset($item['roles']) ? $item['roles'] : Auth::adminRoles();
                if (Auth::userHasRole($user, $allowedRoles)) {
                    $items[] = $item;
                }
            }

            if ($items) {
                $section['items'] = $items;
                $menuSections[] = $section;
            }
        }
    } else {
        $resellerItems = array(
            array('label' => 'Kontrol Paneli', 'href' => '/dashboard.php', 'pattern' => '/dashboard.php', 'icon' => 'bi-speedometer2'),
        );

        if (Helpers::featureEnabled('products')) {
            $resellerItems[] = array('label' => 'Ürünler', 'href' => '/products.php', 'pattern' => '/products.php', 'icon' => 'bi-box');
        }

        if (Helpers::featureEnabled('orders')) {
            $resellerItems[] = array('label' => 'Siparişlerim', 'href' => '/orders.php', 'pattern' => '/orders.php', 'icon' => 'bi-receipt');
        }

        if (Helpers::featureEnabled('balance')) {
            $resellerItems[] = array('label' => 'Bakiyem', 'href' => '/balance.php', 'pattern' => '/balance.php', 'icon' => 'bi-wallet2');
        }

        if (Helpers::featureEnabled('support')) {
            $resellerItems[] = array('label' => 'Destek', 'href' => '/support.php', 'pattern' => '/support.php', 'icon' => 'bi-life-preserver');
        }

        if (Helpers::featureEnabled('premium_modules')) {
            $resellerItems[] = array('label' => 'Premium Modüller', 'href' => '/premium-modules.php', 'pattern' => '/premium-modules.php', 'icon' => 'bi-gem');
        }

        $resellerItems[] = array('label' => 'Profilim', 'href' => '/profile.php', 'pattern' => '/profile.php', 'icon' => 'bi-person');

        $menuSections = array(
            array(
                'heading' => 'Bayi Paneli',
                'items' => $resellerItems,
            ),
        );
    }
}
?>
<!DOCTYPE html>
<html lang="<?= Lang::htmlLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? Helpers::sanitize($pageTitle) . ' | ' : '' ?><?= Helpers::sanitize($siteName) ?></title>
    <meta name="description" content="<?= Helpers::sanitize($metaDescription) ?>">
    <meta name="keywords" content="<?= Helpers::sanitize($metaKeywords) ?>">
    <meta property="og:site_name" content="<?= Helpers::sanitize($siteName) ?>">
    <meta property="og:title" content="<?= Helpers::sanitize(isset($pageTitle) ? $pageTitle : $siteName) ?>">
    <meta property="og:description" content="<?= Helpers::sanitize($metaDescription) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <?php if ($user): ?>
        <aside class="app-sidebar" id="appSidebar">
            <div class="sidebar-brand">
                <a href="<?= $isAdminArea ? '/admin/dashboard.php' : '/dashboard.php' ?>"><?= Helpers::sanitize($siteName) ?></a>
                <?php if ($siteTagline): ?>
                    <div class="sidebar-brand-tagline text-muted small"><?= Helpers::sanitize($siteTagline) ?></div>
                <?php endif; ?>
            </div>
            <div class="sidebar-user">
                <div class="sidebar-user-name"><?= Helpers::sanitize($user['name']) ?></div>
                <div class="sidebar-user-role text-uppercase"><?= Helpers::sanitize(Auth::roleLabel($user['role'])) ?></div>
                <?php if (Helpers::featureEnabled('balance')): ?>
                    <div class="sidebar-user-balance">
                        <?= Helpers::sanitize('Bakiye') ?>:
                        <strong><?= Helpers::formatCurrency((float)$user['balance']) ?></strong>
                    </div>
                <?php endif; ?>
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
        <div class="sidebar-backdrop d-lg-none" data-sidebar-close></div>
    <?php endif; ?>
    <div class="app-main d-flex flex-column flex-grow-1">
        <?php if ($user): ?>
            <header class="app-topbar d-flex align-items-center justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-3 flex-grow-1">
                    <button class="btn btn-light border-0 d-lg-none sidebar-toggle" type="button" data-sidebar-toggle aria-controls="appSidebar" aria-expanded="false" aria-label="Menüyü aç">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h4 mb-1 mb-lg-0"><?= Helpers::sanitize($pageHeadline) ?></h1>
                        <p class="text-muted mb-0 small"><?= date('d F Y') ?></p>
                    </div>
                </div>
                <?php if ($isAdminRole && !$isAdminArea): ?>
                    <div class="d-flex align-items-center gap-2">
                        <a href="/admin/dashboard.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-speedometer2 me-1"></i> <?= Helpers::sanitize('Yönetim Paneli') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </header>
        <?php endif; ?>
        <main class="app-content flex-grow-1 container-fluid">
            <?php if ($lowBalanceNotice): ?>
                <div class="alert alert-warning shadow-sm border-0 rounded-3 p-4 mb-4 d-flex flex-column flex-lg-row align-items-lg-center gap-3">
                    <div class="low-balance-icon text-warning display-6">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-2 fw-semibold">Bakiyeniz minimum seviyenin altında</h5>
                        <?php
                        $remainingLabel = $lowBalanceNotice['remaining_days'] > 0
                            ? $lowBalanceNotice['remaining_days'] . ' gün'
                            : ($lowBalanceNotice['remaining_hours'] > 0 ? $lowBalanceNotice['remaining_hours'] . ' saat' : 'Son saatler');
                        ?>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge bg-warning-subtle text-warning px-3 py-2">
                                Kalan süre: <?= Helpers::sanitize($remainingLabel) ?>
                            </span>
                            <span class="badge bg-light text-dark px-3 py-2">
                                Son tarih: <?= Helpers::sanitize($lowBalanceNotice['deadline']) ?>
                            </span>
                            <span class="badge bg-light text-dark px-3 py-2">
                                Minimum bakiye: <?= Helpers::sanitize(Helpers::formatCurrency($lowBalanceNotice['threshold'])) ?>
                            </span>
                        </div>
                        <p class="mb-2">
                            Bakiyeniz minimum tutarın altına düştüğü için hesabınız <?= Helpers::sanitize((string)$lowBalanceNotice['grace_days']) ?> gün içinde
                            (<?= Helpers::sanitize($lowBalanceNotice['deadline']) ?>) yeterli bakiye yüklenmezse otomatik olarak pasife alınacaktır.
                        </p>
                        <?php if ($lowBalanceNotice['deficit'] > 0): ?>
                            <p class="mb-0">
                                Eksik tutar: <strong><?= Helpers::sanitize(Helpers::formatCurrency($lowBalanceNotice['deficit'])) ?></strong>.
                                Bayiliğinizi korumak için bakiyenizi en kısa sürede tamamlayın.
                            </p>
                        <?php else: ?>
                            <p class="mb-0">Bayiliğinize devam etmek için minimum bakiye tutarını yüklemeniz gerekmektedir.</p>
                        <?php endif; ?>
                    </div>
                    <?php if (Helpers::featureEnabled('balance')): ?>
                        <div class="flex-shrink-0">
                            <a href="/balance.php" class="btn btn-warning fw-semibold">
                                <i class="bi bi-wallet2 me-2"></i> Bakiye Yükle
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
