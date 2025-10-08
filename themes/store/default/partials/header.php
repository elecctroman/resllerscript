<?php

use App\Auth;
use App\Helpers;

$viewData = array();
if (isset($storeViewContext) && is_object($storeViewContext) && isset($storeViewContext->data) && is_array($storeViewContext->data)) {
    $viewData = $storeViewContext->data;
}

$siteName = trim((string) get_setting('site_name', 'OyunHesap.com.tr'));
if ($siteName === '') {
    $siteName = 'OyunHesap.com.tr';
}

$logoSetting = get_setting('site_logo');
$logoUrl = '';
if ($logoSetting) {
    $candidate = (string) $logoSetting;
    if (preg_match('/^https?:/i', $candidate)) {
        $logoUrl = $candidate;
    } else {
        $logoUrl = store_url(ltrim($candidate, '/'));
    }
}

$homeUrl = store_url('');
$searchUrl = store_url('arama');
$logoutAction = store_url('account/logout');
$logoutToken = Helpers::csrfToken();

$sessionUser = Auth::currentUser();
$isLoggedIn = Auth::check();

$displayName = 'Misafir';
if ($sessionUser) {
    if (!empty($sessionUser['name'])) {
        $displayName = (string) $sessionUser['name'];
    } elseif (!empty($sessionUser['email'])) {
        $displayName = (string) $sessionUser['email'];
    }
}

$avatarInitial = strtoupper(substr($displayName, 0, 1));
if (function_exists('mb_substr')) {
    $firstChar = mb_substr($displayName, 0, 1, 'UTF-8');
    if ($firstChar !== false && $firstChar !== '') {
        $avatarInitial = mb_strtoupper($firstChar, 'UTF-8');
    }
}

$accountUrl = store_url('account');
$ordersUrl = store_url('account/orders');
$loginUrl = store_url('account/login');
$registerUrl = store_url('account/register');

$isAdmin = Auth::hasRole('admin');
$isReseller = Auth::hasRole('reseller');

$megaMenuGroups = store_mega_menu();
$hasMegaMenu = !empty($megaMenuGroups);

?>
<header class="store-header" data-store-header>
    <div class="store-topbar shadow-sm">
        <div class="container-xxl d-flex align-items-center gap-3">
            <div class="store-brand d-flex align-items-center gap-2">
                <a class="brand-link" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($logoUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>" class="brand-logo" loading="lazy">
                    <?php else: ?>
                        <span class="brand-text"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </a>
                <?php if ($hasMegaMenu): ?>
                    <button class="btn btn-outline-light btn-sm d-none d-lg-inline-flex align-items-center gap-2" type="button" data-mega-toggle aria-expanded="false" aria-controls="megaMenuPanel">
                        <i class="bi bi-grid-3x3-gap"></i>
                        <span>Kategoriler</span>
                    </button>
                <?php endif; ?>
            </div>

            <div class="store-search flex-grow-1">
                <form action="<?= htmlspecialchars($searchUrl, ENT_QUOTES, 'UTF-8') ?>" method="get" class="store-search__form" role="search">
                    <span class="store-search__icon" aria-hidden="true"></span>
                    <input type="search" name="q" class="store-search__input" placeholder="Ürün veya kategori ara…" aria-label="Mağazada ara" data-search-suggest data-search-endpoint="<?= htmlspecialchars(store_url('api/search'), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="store-search__suggestions" role="listbox" hidden></div>
                </form>
            </div>

            <div class="store-actions d-none d-lg-flex align-items-center gap-2">
                <button class="btn btn-icon" type="button" aria-label="Bildirimler"><i class="bi bi-bell"></i></button>
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Türkçe</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted">Türkçe (Varsayılan)</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><span class="dropdown-item disabled">English (yakında)</span></li>
                    </ul>
                </div>
                <?php if ($isLoggedIn): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-light d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="user-avatar" aria-hidden="true"><?= htmlspecialchars($avatarInitial, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="d-none d-xl-inline user-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                            <li class="px-3 py-2 text-muted small">Hoş geldin, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>!</li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>">Hesabım</a></li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($ordersUrl, ENT_QUOTES, 'UTF-8') ?>">Siparişlerim</a></li>
                            <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item" href="<?= htmlspecialchars(admin_base_url(), ENT_QUOTES, 'UTF-8') ?>">Admin Paneli</a></li>
                            <?php endif; ?>
                            <?php if ($isReseller): ?>
                                <li><a class="dropdown-item" href="<?= htmlspecialchars(reseller_base_url(), ENT_QUOTES, 'UTF-8') ?>">Bayi Paneli</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="<?= htmlspecialchars($logoutAction, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($logoutToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="dropdown-item text-danger">Çıkış Yap</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-2">
                        <a class="btn btn-outline-light" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Giriş Yap</a>
                        <a class="btn btn-accent" href="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>">Kayıt Ol</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="store-actions-mobile d-lg-none ms-auto d-flex align-items-center gap-2">
                <?php if ($hasMegaMenu): ?>
                    <button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#megaMenuOffcanvas" aria-controls="megaMenuOffcanvas" aria-label="Kategorileri aç"><i class="bi bi-grid-3x3-gap"></i></button>
                <?php endif; ?>
                <button class="btn btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#storeMobileMenu" aria-expanded="false" aria-controls="storeMobileMenu">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
        </div>
    </div>

    <div class="collapse" id="storeMobileMenu">
        <div class="container-xxl py-3">
            <div class="d-grid gap-2">
                <?php if ($isLoggedIn): ?>
                    <a class="btn btn-outline-light" href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>">Hesabım</a>
                    <a class="btn btn-outline-light" href="<?= htmlspecialchars($ordersUrl, ENT_QUOTES, 'UTF-8') ?>">Siparişlerim</a>
                    <?php if ($isAdmin): ?>
                        <a class="btn btn-outline-light" href="<?= htmlspecialchars(admin_base_url(), ENT_QUOTES, 'UTF-8') ?>">Admin Paneli</a>
                    <?php endif; ?>
                    <?php if ($isReseller): ?>
                        <a class="btn btn-outline-light" href="<?= htmlspecialchars(reseller_base_url(), ENT_QUOTES, 'UTF-8') ?>">Bayi Paneli</a>
                    <?php endif; ?>
                    <form method="post" action="<?= htmlspecialchars($logoutAction, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($logoutToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-danger">Çıkış Yap</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-outline-light" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Giriş Yap</a>
                    <a class="btn btn-accent" href="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>">Kayıt Ol</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($hasMegaMenu): ?>
        <div class="store-mega" id="megaMenuPanel" hidden>
            <div class="container-xxl">
                <div class="store-mega__grid">
                    <?php foreach ($megaMenuGroups as $group): ?>
                        <section class="store-mega__column">
                            <h3 class="store-mega__title"><?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <ul class="store-mega__list" role="menu">
                                <?php foreach ($group['items'] as $item): ?>
                                    <li role="none">
                                        <a class="store-mega__link" role="menuitem" href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>">
                                            <span class="store-mega__icon cat-icon cat-icon--<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                            <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="megaMenuOffcanvas" aria-labelledby="megaMenuOffcanvasLabel">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title h5" id="megaMenuOffcanvasLabel">Kategoriler</h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Kapat"></button>
            </div>
            <div class="offcanvas-body">
                <div class="accordion" id="megaMenuAccordion">
                    <?php foreach ($megaMenuGroups as $index => $group): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="megaHeading<?= (int) $group['id'] ?>">
                                <button class="accordion-button<?= $index > 0 ? ' collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#megaCollapse<?= (int) $group['id'] ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="megaCollapse<?= (int) $group['id'] ?>">
                                    <?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            </h2>
                            <div id="megaCollapse<?= (int) $group['id'] ?>" class="accordion-collapse collapse<?= $index === 0 ? ' show' : '' ?>" data-bs-parent="#megaMenuAccordion">
                                <div class="accordion-body">
                                    <ul class="list-unstyled d-grid gap-2">
                                        <?php foreach ($group['items'] as $item): ?>
                                            <li>
                                                <a class="store-mega__link" href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <span class="store-mega__icon cat-icon cat-icon--<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                                    <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</header>
