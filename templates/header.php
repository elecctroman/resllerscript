<?php

use App\Auth;
use App\Helpers;
use App\Database;
use App\Lang;
use App\FeatureToggle;
use App\ResellerPolicy;
use App\Services\LanguageService;
use App\Services\AnnouncementService;

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
$hasTopbarActions = $showLanguageSwitch;

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
            array(
                'heading' => 'Denetim',
                'items' => array(
                    array('label' => 'Aktivite Kayıtları', 'href' => '/admin/activity-logs.php', 'pattern' => '/admin/activity-logs.php', 'icon' => 'bi-clipboard-data', 'roles' => array('super_admin', 'admin')),
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

$nameSource = $user['name'] ?? ($user['email'] ?? 'U');
if (function_exists('mb_substr')) {
    $avatarInitial = mb_strtoupper(mb_substr($nameSource, 0, 1, 'UTF-8'), 'UTF-8');
} else {
    $avatarInitial = strtoupper(substr($nameSource, 0, 1));
}
$userRoleLabel = $user ? Auth::roleLabel($user['role']) : '';
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
        <aside class="app-sidebar" id="appSidebar">
            <div class="sidebar-brand">
                <a href="<?= $isAdminArea ? '/admin/dashboard.php' : '/dashboard.php' ?>"><?= Helpers::sanitize($siteName) ?></a>
                <?php if ($siteTagline): ?>
                    <div class="sidebar-brand-tagline text-muted small"><?= Helpers::sanitize($siteTagline) ?></div>
                <?php endif; ?>
            </div>
            <div class="sidebar-user d-flex align-items-center gap-3">
                <span class="avatar-circle avatar-circle--sidebar fw-semibold flex-shrink-0"><?= Helpers::sanitize($avatarInitial) ?></span>
                <div class="flex-grow-1">
                    <div class="sidebar-user-name fw-semibold mb-1"><?= Helpers::sanitize($user['name']) ?></div>
                    <div class="sidebar-user-role text-uppercase small mb-2"><?= Helpers::sanitize($userRoleLabel) ?></div>
                    <?php if (Helpers::featureEnabled('balance')): ?>
                        <div class="sidebar-user-balance small text-white-75">
                            <?= Helpers::sanitize('Bakiye') ?>:
                            <strong><?= Helpers::formatCurrencyHtml((float) $user['balance']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <nav class="sidebar-nav" id="sidebarNav">
                <?php foreach ($menuSections as $section): ?>
                    <div class="sidebar-section">
                        <div class="sidebar-section-heading text-uppercase text-white-75 small fw-semibold">
                            <?= Helpers::sanitize($section['heading']) ?>
                        </div>
                        <ul class="list-unstyled mb-0 sidebar-section-list">
                            <?php foreach ($section['items'] as $navItem): ?>
                                <?php $itemActive = Helpers::isActive($navItem['pattern']); ?>
                                <li>
                                    <a href="<?= $navItem['href'] ?>" class="sidebar-link <?= $itemActive ? 'active' : '' ?>">
                                        <?php if (!empty($navItem['icon'])): ?>
                                            <span class="sidebar-link-icon"><i class="<?= Helpers::sanitize($navItem['icon']) ?>"></i></span>
                                        <?php endif; ?>
                                        <span class="sidebar-link-text"><?= Helpers::sanitize($navItem['label']) ?></span>
                                        <?php if (!empty($navItem['badge']) && (int) $navItem['badge'] > 0): ?>
                                            <span class="sidebar-link-badge"><?= (int) $navItem['badge'] ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer mt-auto">
                <a href="/logout.php" class="btn btn-outline-light w-100"><i class="bi bi-box-arrow-right me-2"></i><?= Helpers::sanitize('Çıkış Yap') ?></a>
            </div>
        </aside>
        <div class="sidebar-backdrop d-lg-none" data-sidebar-close></div>
    <?php endif; ?>
    <div class="app-main d-flex flex-column flex-grow-1">
        <?php if ($user): ?>
            <header class="app-topbar d-flex align-items-center justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-3">
                    <button class="sidebar-toggle btn btn-light border-0 d-lg-none" type="button" data-sidebar-toggle aria-controls="appSidebar" aria-expanded="false" aria-label="<?= Helpers::sanitize('Menüyü Aç') ?>">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h4 mb-1"><?= Helpers::sanitize($pageHeadline) ?></h1>
                        <p class="text-muted mb-0 small"><?= date('d F Y') ?></p>
                    </div>
                </div>
                <?php if ($hasTopbarActions): ?>
                    <div class="app-topbar-actions d-flex align-items-center flex-wrap gap-3 ms-lg-auto">
                        <?php if ($showLanguageSwitch): ?>
                            <div class="app-language-switcher">
                                <label class="form-label form-label-sm text-muted mb-1" for="appLanguageSelect"><?= Helpers::sanitize('Dil') ?></label>
                                <select class="form-select form-select-sm" id="appLanguageSelect" data-initial-locale="<?= Helpers::sanitize($activeLocale) ?>">
                                    <?php foreach ($languageOptions as $option): ?>
                                        <option value="<?= Helpers::sanitize($option['code']) ?>" <?= $option['code'] === $activeLocale ? 'selected' : '' ?>>
                                            <?= Helpers::sanitize($option['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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
