<?php

use App\Auth;
use App\Helpers;
use App\Database;
use App\Lang;
use App\FeatureToggle;
use App\ResellerPolicy;
use App\Services\LanguageService;
use App\Services\AnnouncementService;
use App\Settings;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;
$pageHeadline = $pageTitle ?? 'Panel';

if (!isset($_SESSION['dismissed_announcements']) || !is_array($_SESSION['dismissed_announcements'])) {
    $_SESSION['dismissed_announcements'] = array();
}

if (isset($_GET['dismiss_announcement'])) {
    $dismissId = (int) $_GET['dismiss_announcement'];
    if ($dismissId > 0) {
        $_SESSION['dismissed_announcements'][$dismissId] = time();
        if (!headers_sent() && (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] === 'GET')) {
            Helpers::redirect(Helpers::urlWithQuery(array('dismiss_announcement' => null)));
        }
    }
}

Lang::boot();

$siteName = Helpers::siteName();
$siteTagline = Helpers::siteTagline();
$metaDescription = Helpers::seoDescription();
$metaKeywords = Helpers::seoKeywords();

$siteLogoSetting = Settings::get('site_logo');
$siteLogoUrl = '';
if ($siteLogoSetting !== null && trim((string) $siteLogoSetting) !== '') {
    $siteLogoCandidate = trim((string) $siteLogoSetting);
    if (preg_match('/^https?:\/\//i', $siteLogoCandidate)) {
        $siteLogoUrl = $siteLogoCandidate;
    } else {
        $siteLogoUrl = '/' . ltrim($siteLogoCandidate, '/');
    }
}
if ($siteLogoUrl === '') {
    $siteLogoUrl = '/assets/logo-default.svg';
}

$activeLocale = Lang::locale();
$defaultLocale = Lang::defaultLocale();

$languageOptions = array();
if (class_exists(LanguageService::class)) {
    foreach (LanguageService::languages(false) as $language) {
        if (!isset($language['code'])) {
            continue;
        }
        $code = strtolower((string) $language['code']);
        $languageOptions[$code] = array(
            'code' => $code,
            'label' => isset($language['native_name']) && $language['native_name'] !== ''
                ? (string) $language['native_name']
                : strtoupper($code),
            'name' => isset($language['name']) && $language['name'] !== ''
                ? (string) $language['name']
                : strtoupper($code),
        );
    }
}

if (!$languageOptions) {
    foreach (Lang::availableLocales() as $localeOption) {
        $code = strtolower((string) $localeOption);
        $languageOptions[$code] = array(
            'code' => $code,
            'label' => strtoupper($code),
            'name' => strtoupper($code),
        );
    }
}

if (!isset($languageOptions[$activeLocale])) {
    $languageOptions[$activeLocale] = array(
        'code' => $activeLocale,
        'label' => strtoupper($activeLocale),
        'name' => strtoupper($activeLocale),
    );
}

$languageOptions = array_values($languageOptions);
$languageOptionsForScript = array();
foreach ($languageOptions as $option) {
    $languageOptionsForScript[] = array(
        'code' => $option['code'],
        'label' => $option['label'] ?? strtoupper($option['code']),
        'name' => $option['name'] ?? ($option['label'] ?? strtoupper($option['code'])),
    );
}

$showLanguageSwitch = count($languageOptions) > 1;
$appCsrfToken = Helpers::csrfToken();
$activeTranslations = class_exists(LanguageService::class) ? LanguageService::catalog($activeLocale) : array();
$fallbackTranslations = class_exists(LanguageService::class) ? LanguageService::catalog($defaultLocale) : array();

$lowBalanceNotice = null;
if ($user) {
    $lowBalanceNotice = ResellerPolicy::lowBalanceNotice($user);
}

$currentPath = Helpers::currentPath();
$isAdminRole = $user ? Auth::isAdminRole($user['role']) : false;
$isAdminArea = $user && $isAdminRole && strpos($currentPath, '/admin/') === 0;
$menuBadges = array();

if ($user && $isAdminRole) {
    try {
        $sidebarPdo = Database::connection();
        if (Auth::userHasRole($user, array('super_admin', 'admin', 'support'))) {
            $menuBadges['/admin/orders.php'] = (int) $sidebarPdo
                ->query("SELECT COUNT(*) FROM package_orders WHERE status IN ('pending','paid')")
                ->fetchColumn();
            $menuBadges['/admin/product-orders.php'] = (int) $sidebarPdo
                ->query("SELECT COUNT(*) FROM product_orders WHERE status IN ('pending','processing')")
                ->fetchColumn();
        }
    } catch (\Throwable $sidebarException) {
        $menuBadges = array();
    }
}

$menuSections = array();

if ($user) {
    if ($isAdminRole) {
        $adminSections = array(
            array(
                'heading' => 'Genel Yönetim',
                'items' => array(
                    array('label' => 'Genel Bakış', 'href' => '/admin/dashboard.php', 'pattern' => '/admin/dashboard.php', 'icon' => 'bi-speedometer2', 'roles' => Auth::adminRoles()),
                    array('label' => 'Raporlar', 'href' => '/admin/reports.php', 'pattern' => '/admin/reports.php', 'icon' => 'bi-graph-up', 'roles' => array('super_admin', 'admin', 'finance')),
                    array('label' => 'Paketler', 'href' => '/admin/packages.php', 'pattern' => '/admin/packages.php', 'icon' => 'bi-box-seam', 'roles' => array('super_admin', 'admin')),
                    array('label' => 'Bayiler', 'href' => '/admin/users.php', 'pattern' => '/admin/users.php', 'icon' => 'bi-people', 'roles' => array('super_admin', 'admin')),
                    array('label' => 'Aktivite Kayıtları', 'href' => '/admin/activity-logs.php', 'pattern' => '/admin/activity-logs.php', 'icon' => 'bi-clipboard-data', 'roles' => array('super_admin', 'admin')),
                ),
            ),
            array(
                'heading' => 'Sipariş Yönetimi',
                'items' => array(
                    array('label' => 'Paket Siparişleri', 'href' => '/admin/orders.php', 'pattern' => '/admin/orders.php', 'icon' => 'bi-receipt', 'roles' => array('super_admin', 'admin', 'support'), 'badge' => $menuBadges['/admin/orders.php'] ?? 0),
                    array('label' => 'Ürün Siparişleri', 'href' => '/admin/product-orders.php', 'pattern' => '/admin/product-orders.php', 'icon' => 'bi-basket', 'roles' => array('super_admin', 'admin', 'support'), 'badge' => $menuBadges['/admin/product-orders.php'] ?? 0),
                ),
            ),
            array(
                'heading' => 'Ürün & Stok',
                'items' => array(
                    array('label' => 'Ürünler', 'href' => '/admin/products.php', 'pattern' => '/admin/products.php', 'icon' => 'bi-box', 'roles' => array('super_admin', 'admin', 'content')),
                    array('label' => 'Stok Yönetimi', 'href' => '/admin/product-stock.php', 'pattern' => '/admin/product-stock.php', 'icon' => 'bi-archive', 'roles' => array('super_admin', 'admin', 'content')),
                    array('label' => 'Sağlayıcılar', 'href' => '/admin/providers.php', 'pattern' => '/admin/providers.php', 'icon' => 'bi-plug', 'roles' => array('super_admin', 'admin')),
                    array('label' => 'Kategoriler', 'href' => '/admin/categories.php', 'pattern' => '/admin/categories.php', 'icon' => 'bi-diagram-3', 'roles' => array('super_admin', 'admin', 'content')),
                ),
            ),
            array(
                'heading' => 'İçerik Yönetimi',
                'items' => array(
                    array('label' => 'Blog Yazıları', 'href' => '/admin/blog-posts.php', 'pattern' => '/admin/blog-posts.php', 'icon' => 'bi-journal-text', 'roles' => array('super_admin', 'admin', 'content')),
                    array('label' => 'Blog Kategorileri', 'href' => '/admin/blog-categories.php', 'pattern' => '/admin/blog-categories.php', 'icon' => 'bi-tags', 'roles' => array('super_admin', 'admin', 'content')),
                    array('label' => 'Talimatlar', 'href' => '/admin/instructions.php', 'pattern' => '/admin/instructions.php', 'icon' => 'bi-card-checklist', 'roles' => array('super_admin', 'admin', 'content')),
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
                    array('label' => 'Ödeme Methodları', 'href' => '/admin/settings-payments.php', 'pattern' => '/admin/settings-payments.php', 'icon' => 'bi-credit-card', 'roles' => array('super_admin', 'admin', 'finance')),
                    array('label' => 'Dil Yönetimi', 'href' => '/admin/languages.php', 'pattern' => '/admin/languages.php', 'icon' => 'bi-translate', 'roles' => array('super_admin', 'admin')),
                    array('label' => 'Telegram Ayarları', 'href' => '/admin/settings-telegram.php', 'pattern' => '/admin/settings-telegram.php', 'icon' => 'bi-telegram', 'roles' => array('super_admin', 'admin')),
                ),
            ),
        );

        foreach ($adminSections as $section) {
            $items = array();
            foreach ($section['items'] as $item) {
                $allowedRoles = $item['roles'] ?? Auth::adminRoles();
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
            array('label' => 'Bayi Analitiği', 'href' => '/analytics.php', 'pattern' => '/analytics.php', 'icon' => 'bi-graph-up'),
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

        $resellerItems[] = array('label' => 'Talimatlar', 'href' => '/instructions.php', 'pattern' => '/instructions.php', 'icon' => 'bi-journal-check');

        if (Helpers::featureEnabled('premium_modules')) {
            $resellerItems[] = array('label' => 'Premium Modüller', 'href' => '/premium-modules.php', 'pattern' => '/premium-modules.php', 'icon' => 'bi-gem');
        }

        $resellerItems[] = array('label' => 'Profilim', 'href' => '/profile.php', 'pattern' => '/profile.php', 'icon' => 'bi-person');

        $menuSections[] = array(
            'heading' => 'Bayi Paneli',
            'items' => $resellerItems,
        );
    }
}

$dismissedAnnouncementIds = array_map('intval', array_keys($_SESSION['dismissed_announcements'] ?? array()));
$activeAnnouncements = array();
if ($user && !$isAdminRole) {
    $activeAnnouncements = AnnouncementService::activeForUser($user, 4, $dismissedAnnouncementIds);
}

$userDisplayName = '';
if ($user) {
    $userDisplayName = isset($user['name']) && $user['name'] !== ''
        ? (string) $user['name']
        : (isset($user['email']) ? (string) $user['email'] : '');
}

$nameSource = $userDisplayName !== '' ? $userDisplayName : ($user['email'] ?? 'U');
if (function_exists('mb_substr')) {
    $avatarInitial = mb_strtoupper(mb_substr($nameSource, 0, 1, 'UTF-8'), 'UTF-8');
} else {
    $avatarInitial = strtoupper(substr($nameSource, 0, 1));
}
$userRoleLabel = $user ? Auth::roleLabel($user['role']) : '';

$userAvatarUrl = '';
if ($user) {
    $avatarKeys = array('avatar_url', 'avatar', 'profile_photo', 'profile_photo_url');
    foreach ($avatarKeys as $avatarKey) {
        if (!empty($user[$avatarKey])) {
            $candidate = (string) $user[$avatarKey];
            if (preg_match('/^https?:\/\//i', $candidate)) {
                $userAvatarUrl = $candidate;
            } else {
                $userAvatarUrl = '/' . ltrim($candidate, '/');
            }
            break;
        }
    }
}

$userBalanceValue = isset($user['balance']) ? (float) $user['balance'] : 0.0;
$userBalanceHtml = Helpers::formatCurrencyHtml($userBalanceValue);
$showBalanceInfo = $user && ($isAdminRole || Helpers::featureEnabled('balance'));

$resellerFlatItems = array();
if ($user && !$isAdminRole) {
    foreach ($menuSections as $section) {
        $sectionItems = $section['items'] ?? array();
        foreach ($sectionItems as $sectionItem) {
            $resellerFlatItems[] = $sectionItem;
        }
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
    <meta property="og:title" content="<?= Helpers::sanitize($pageHeadline) ?>">
    <meta property="og:description" content="<?= Helpers::sanitize($metaDescription) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <script>
        window.App = window.App || {};
        window.App.locale = <?= json_encode($activeLocale, JSON_UNESCAPED_UNICODE) ?>;
        window.App.defaultLocale = <?= json_encode($defaultLocale, JSON_UNESCAPED_UNICODE) ?>;
        window.App.csrfToken = <?= json_encode($appCsrfToken, JSON_UNESCAPED_UNICODE) ?>;
        window.App.languages = <?= json_encode($languageOptionsForScript, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.App.translations = <?= json_encode($activeTranslations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.App.translationFallback = <?= json_encode($fallbackTranslations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
</head>
<body>
<div class="app-shell">
    <?php if ($user): ?>
        <header class="app-navbar navbar navbar-expand-lg navbar-dark" id="appNavbar" role="banner">
            <div class="container-fluid app-navbar-container">
                <div class="navbar-layout d-flex align-items-center justify-content-between flex-wrap gap-3 w-100">
                    <div class="navbar-left d-flex align-items-center flex-shrink-0 order-1">
                        <a class="navbar-brand d-flex align-items-center" href="<?= $isAdminArea ? '/admin/dashboard.php' : '/dashboard.php' ?>">
                            <?php if ($siteLogoUrl): ?>
                                <img src="<?= Helpers::sanitize($siteLogoUrl) ?>" alt="<?= Helpers::sanitize($siteName) ?>" class="navbar-brand-logo">
                            <?php endif; ?>
                            <span class="visually-hidden"><?= Helpers::sanitize($siteName) ?></span>
                        </a>
                    </div>
                    <button class="navbar-toggler d-lg-none ms-auto order-2" type="button" data-bs-toggle="collapse" data-bs-target="#appNavbarNav" aria-controls="appNavbarNav" aria-expanded="false" aria-label="<?= Helpers::sanitize('Menüyü Aç') ?>">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse order-3 order-lg-2 flex-lg-grow-1 w-100" id="appNavbarNav" role="navigation" aria-label="<?= Helpers::sanitize('Ana menü') ?>">
                        <div class="navbar-collapse-inner d-flex flex-column flex-lg-row align-items-lg-center w-100 gap-4">
                            <div class="navbar-center flex-grow-1 order-2 order-lg-2">
                            <?php if ($menuSections): ?>
                                <div class="navbar-menu-scroll" tabindex="0">
                                    <ul class="navbar-nav align-items-lg-center justify-content-lg-center" role="menubar" aria-label="<?= Helpers::sanitize('Birincil menü') ?>">
                                        <?php if ($isAdminRole): ?>
                                            <?php foreach ($menuSections as $section): ?>
                                                <?php
                                                $sectionItems = $section['items'] ?? array();
                                                $hasMultiple = count($sectionItems) > 1;
                                                $sectionActive = false;
                                                foreach ($sectionItems as $sectionItem) {
                                                    if (Helpers::isActive($sectionItem['pattern'])) {
                                                        $sectionActive = true;
                                                        break;
                                                    }
                                                }
                                                ?>
                                                <?php if ($hasMultiple): ?>
                                                    <li class="nav-item dropdown" role="none">
                                                        <a class="nav-link dropdown-toggle<?= $sectionActive ? ' active' : '' ?>" href="#" id="dropdown-<?= md5($section['heading']) ?>" role="menuitem" data-bs-toggle="dropdown" data-bs-display="static" aria-haspopup="true" aria-expanded="false">
                                                            <span class="nav-link-text"><?= Helpers::sanitize($section['heading']) ?></span>
                                                        </a>
                                                        <ul class="dropdown-menu" aria-labelledby="dropdown-<?= md5($section['heading']) ?>" role="menu" aria-label="<?= Helpers::sanitize($section['heading']) ?>">
                                                            <?php foreach ($sectionItems as $navItem): ?>
                                                                <?php $itemActive = Helpers::isActive($navItem['pattern']); ?>
                                                                <li role="none">
                                                                    <a class="dropdown-item d-flex align-items-center gap-2<?= $itemActive ? ' active' : '' ?>" href="<?= $navItem['href'] ?>" role="menuitem"<?= $itemActive ? ' aria-current="page"' : '' ?>>
                                                                        <?php if (!empty($navItem['icon'])): ?>
                                                                            <i class="<?= Helpers::sanitize($navItem['icon']) ?>"></i>
                                                                        <?php endif; ?>
                                                                        <span><?= Helpers::sanitize($navItem['label']) ?></span>
                                                                        <?php if (!empty($navItem['badge']) && (int) $navItem['badge'] > 0): ?>
                                                                            <span class="menu-badge ms-auto"><?= (int) $navItem['badge'] ?></span>
                                                                        <?php endif; ?>
                                                                    </a>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </li>
                                                <?php else: ?>
                                                    <?php $navItem = reset($sectionItems); ?>
                                                    <?php if ($navItem): ?>
                                                        <?php $itemActive = Helpers::isActive($navItem['pattern']); ?>
                                                        <li class="nav-item" role="none">
                                                            <a class="nav-link d-flex align-items-center gap-2<?= $itemActive ? ' active' : '' ?>" href="<?= $navItem['href'] ?>" role="menuitem"<?= $itemActive ? ' aria-current="page"' : '' ?>>
                                                                <?php if (!empty($navItem['icon'])): ?>
                                                                    <i class="<?= Helpers::sanitize($navItem['icon']) ?>"></i>
                                                                <?php endif; ?>
                                                                <span class="nav-link-text"><?= Helpers::sanitize($navItem['label']) ?></span>
                                                                <?php if (!empty($navItem['badge']) && (int) $navItem['badge'] > 0): ?>
                                                                    <span class="menu-badge ms-lg-1"><?= (int) $navItem['badge'] ?></span>
                                                                <?php endif; ?>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <?php foreach ($resellerFlatItems as $navItem): ?>
                                                <?php $itemActive = Helpers::isActive($navItem['pattern']); ?>
                                                <li class="nav-item" role="none">
                                                    <a class="nav-link d-flex align-items-center gap-2<?= $itemActive ? ' active' : '' ?>" href="<?= $navItem['href'] ?>" role="menuitem"<?= $itemActive ? ' aria-current="page"' : '' ?>>
                                                        <?php if (!empty($navItem['icon'])): ?>
                                                            <i class="<?= Helpers::sanitize($navItem['icon']) ?>"></i>
                                                        <?php endif; ?>
                                                        <span class="nav-link-text"><?= Helpers::sanitize($navItem['label']) ?></span>
                                                        <?php if (!empty($navItem['badge']) && (int) $navItem['badge'] > 0): ?>
                                                            <span class="menu-badge ms-lg-1"><?= (int) $navItem['badge'] ?></span>
                                                        <?php endif; ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="navbar-right order-1 order-lg-3 d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-3 ms-lg-auto ps-lg-4">
                            <?php if ($showLanguageSwitch): ?>
                                <div class="nav-language">
                                    <label class="form-label text-white-50 mb-1" for="appLanguageSelect"><?= Helpers::sanitize('Dil') ?></label>
                                    <select class="form-select form-select-sm" id="appLanguageSelect" data-initial-locale="<?= Helpers::sanitize($activeLocale) ?>">
                                        <?php foreach ($languageOptions as $option): ?>
                                            <option value="<?= Helpers::sanitize($option['code']) ?>" <?= $option['code'] === $activeLocale ? 'selected' : '' ?>>
                                                <?= Helpers::sanitize($option['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <?php if ($showBalanceInfo): ?>
                                <div class="navbar-balance text-white-50 text-lg-end">
                                    <div class="navbar-balance-label text-uppercase small"><?= Helpers::sanitize('Bakiye') ?></div>
                                    <div class="navbar-balance-amount fw-semibold text-white"><?= $userBalanceHtml ?></div>
                                </div>
                            <?php endif; ?>
                            <div class="dropdown navbar-profile-dropdown">
                                <button class="btn btn-navbar-profile dropdown-toggle" type="button" id="navbarProfileDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false">
                                    <?php if ($userAvatarUrl !== ''): ?>
                                        <img src="<?= Helpers::sanitize($userAvatarUrl) ?>" alt="<?= Helpers::sanitize($userDisplayName) ?>" class="navbar-avatar-image rounded-circle" width="36" height="36">
                                    <?php else: ?>
                                        <span class="navbar-avatar-initial"><?= Helpers::sanitize($avatarInitial) ?></span>
                                    <?php endif; ?>
                                    <span class="fw-semibold d-none d-sm-inline"><?= Helpers::sanitize($userDisplayName) ?></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-2" aria-labelledby="navbarProfileDropdown" role="menu">
                                    <li class="px-3 pt-2 pb-1 text-white-50 small"><?= Helpers::sanitize('Hoş geldin') ?> <?= Helpers::sanitize($userDisplayName) ?>!</li>
                                    <li class="px-3 pb-2 text-white-50 small"><?= Helpers::sanitize($userRoleLabel) ?></li>
                                    <?php if ($showBalanceInfo): ?>
                                        <li><hr class="dropdown-divider my-2"></li>
                                        <li class="px-3 pb-2 text-white-50 small"><?= Helpers::sanitize('Kredi') ?>: <span class="text-white fw-semibold"><?= $userBalanceHtml ?></span></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider my-2"></li>
                                    <li><a class="dropdown-item" href="/profile.php"><i class="bi bi-person-circle me-2"></i><?= Helpers::sanitize('Profil') ?></a></li>
                                    <li><a class="dropdown-item" href="<?= $isAdminRole ? '/admin/balances.php' : '/balance.php' ?>"><i class="bi bi-receipt me-2"></i><?= Helpers::sanitize('Mali Geçmiş') ?></a></li>
                                    <?php if (Helpers::featureEnabled('balance')): ?>
                                        <li><a class="dropdown-item" href="<?= Helpers::featureEnabled('balance') ? '/balance.php' : '#' ?>"><i class="bi bi-plus-circle me-2"></i><?= Helpers::sanitize('Kredi Ekle') ?></a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider my-2"></li>
                                    <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= Helpers::sanitize('Çıkış Yap') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </header>
    <?php endif; ?>
    <div class="app-main d-flex flex-column flex-grow-1">
        <?php if ($user): ?>
            <header class="app-topbar">
                <div class="app-topbar-inner d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <h1 class="h4 mb-1"><?= Helpers::sanitize($pageHeadline) ?></h1>
                        <p class="text-muted mb-0 small"><?= date('d F Y') ?></p>
                    </div>
                </div>
            </header>
        <?php else: ?>
            <header class="app-topbar app-topbar--guest shadow-sm">
                <div class="container-xxl d-flex align-items-center justify-content-between">
                    <a class="navbar-brand fw-semibold" href="/"><?= Helpers::sanitize($siteName) ?></a>
                    <?php if ($siteTagline): ?>
                        <span class="text-muted small d-none d-sm-inline"><?= Helpers::sanitize($siteTagline) ?></span>
                    <?php endif; ?>
                </div>
            </header>
        <?php endif; ?>
        <main class="app-content flex-grow-1">
            <div class="app-content-inner py-4">
                <div class="app-container">
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
                            Minimum bakiye: <?= Helpers::formatCurrencyHtml($lowBalanceNotice['threshold']) ?>
                        </span>
                    </div>
                    <p class="mb-2">
                        Bakiyeniz minimum tutarın altına düştüğü için hesabınız <?= Helpers::sanitize((string) $lowBalanceNotice['grace_days']) ?> gün içinde (<?= Helpers::sanitize($lowBalanceNotice['deadline']) ?>) yeterli bakiye yüklenmezse otomatik olarak pasife alınacaktır.
                    </p>
                    <?php if ($lowBalanceNotice['deficit'] > 0): ?>
                        <p class="mb-0">
                            Eksik tutar: <strong><?= Helpers::formatCurrencyHtml($lowBalanceNotice['deficit']) ?></strong>. Bayiliğinizi korumak için bakiyenizi en kısa sürede tamamlayın.
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
        <?php if (!empty($activeAnnouncements)): ?>
            <div class="announcement-stack mb-4">
                <?php foreach ($activeAnnouncements as $announcement): ?>
                    <?php $announcementId = (int) $announcement['id']; ?>
                    <div class="card border-0 shadow-sm announcement-card<?= !empty($announcement['pinned']) ? ' announcement-card--pinned' : '' ?> mb-3 p-0 overflow-hidden">
                        <div class="announcement-card__accent"></div>
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="badge bg-primary-subtle text-primary fw-semibold">Duyuru</span>
                                        <?php if (!empty($announcement['pinned'])): ?>
                                            <span class="badge bg-danger-subtle text-danger fw-semibold">Öne Çıkan</span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="h5 mb-2 fw-semibold text-dark"><?= Helpers::sanitize($announcement['title']) ?></h3>
                                    <div class="text-muted small mb-3">
                                        <?php if (!empty($announcement['starts_at'])): ?>
                                            <span class="me-3"><i class="bi bi-play-fill me-1"></i><?= date('d.m.Y H:i', strtotime($announcement['starts_at'])) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($announcement['ends_at'])): ?>
                                            <span><i class="bi bi-flag me-1"></i><?= date('d.m.Y H:i', strtotime($announcement['ends_at'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-0 announcement-card__body"><?= nl2br(Helpers::sanitize($announcement['body'])) ?></p>
                                </div>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= Helpers::sanitize(Helpers::urlWithQuery(array('dismiss_announcement' => $announcementId))) ?>">
                                    <i class="bi bi-x me-1"></i> Gizle
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
