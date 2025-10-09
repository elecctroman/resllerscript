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

$homeUrl      = store_url('');
$searchUrl    = store_url('arama');
$cartUrl      = store_url('cart');
$logoutAction = store_url('account/logout');
$logoutToken  = Helpers::csrfToken();

$sessionUser = Auth::currentUser();
$isLoggedIn  = Auth::check();
$displayName = 'Misafir';
if ($sessionUser) {
    if (!empty($sessionUser['name'])) {
        $displayName = (string) $sessionUser['name'];
    } elseif (!empty($sessionUser['email'])) {
        $displayName = (string) $sessionUser['email'];
    }
}

$accountUrl = store_url('account');
$ordersUrl  = store_url('account/orders');
$loginUrl   = store_url('account/login');
$registerUrl = store_url('account/register');

$isAdmin    = Auth::hasRole('admin');
$isReseller = Auth::hasRole('reseller');

$megaMenuGroups = store_mega_menu();
$hasMegaMenu    = !empty($megaMenuGroups);

$cartCount = store_cart_count();
?>
<header class="store-header site-header" data-store-header>
    <div class="store-topbar">
        <div class="container-xxl d-flex align-items-center gap-3">
            <a class="brand-link" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>" class="brand-logo" loading="lazy">
                <?php else: ?>
                    <span class="brand-text"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </a>

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
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Türkçe</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted">Türkçe (Varsayılan)</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><span class="dropdown-item disabled">English (yakında)</span></li>
                    </ul>
                </div>
                <a class="btn btn-icon position-relative" href="<?= htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Sepetim">
                    <i class="bi bi-bag"></i>
                    <span class="cart-badge" data-cart-count><?= (int) $cartCount ?></span>
                </a>
                <?php if ($isLoggedIn): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="fw-semibold">Hesabım</span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                            <li class="px-3 py-2 text-muted small">Hoş geldin, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>!</li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>">Profil</a></li>
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
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Giriş Yap</a>
                        <a class="btn btn-primary" href="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>">Kayıt Ol</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="store-actions-mobile d-lg-none ms-auto d-flex align-items-center gap-2">
                <a class="btn btn-outline-secondary position-relative" href="<?= htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Sepetim">
                    <i class="bi bi-bag"></i>
                    <span class="cart-badge" data-cart-count><?= (int) $cartCount ?></span>
                </a>
                <button class="btn btn-outline-secondary" type="button" data-mobile-menu-trigger aria-haspopup="true" aria-expanded="false" aria-controls="storeMobileMenuPanel" aria-label="Menüyü aç">
                    <i class="bi bi-list"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="nav-layer">
    <?php if ($hasMegaMenu): ?>
        <nav class="store-nav" aria-label="Kategori menüsü">
            <div class="container-xxl">
                <ul class="store-nav__list" role="menubar">
                    <?php foreach ($megaMenuGroups as $index => $group): ?>
                        <?php
                        $groupId   = (int) $group['id'];
                        $groupSlug = isset($group['slug']) ? (string) $group['slug'] : '';
                        $groupName = isset($group['name']) ? (string) $group['name'] : 'Kategori';
                        $items     = isset($group['items']) && is_array($group['items']) ? $group['items'] : array();
                        $primaryUrl = '';
                        foreach ($items as $itemCandidate) {
                            if (isset($itemCandidate['url']) && $itemCandidate['url'] !== '') {
                                $primaryUrl = (string) $itemCandidate['url'];
                                break;
                            }
                        }
                        $controlId = 'navDropdown-' . $groupId . '-' . $index;
                        ?>
                        <li class="store-nav__item nav-item has-mega" role="none">
                            <button class="store-nav__button" type="button" data-menu-trigger aria-haspopup="true" aria-expanded="false" aria-controls="<?= htmlspecialchars($controlId, ENT_QUOTES, 'UTF-8') ?>">
                                <span><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></span>
                                <i class="bi bi-chevron-down" aria-hidden="true"></i>
                            </button>
                            <div class="store-nav__dropdown mega-menu" id="<?= htmlspecialchars($controlId, ENT_QUOTES, 'UTF-8') ?>" role="menu" aria-hidden="true">
                                <div class="store-nav__dropdown-inner">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $itemLabel = isset($item['label']) ? (string) $item['label'] : '';
                                        $itemUrl   = isset($item['url']) ? (string) $item['url'] : '';
                                        if ($itemLabel === '' || $itemUrl === '') {
                                            continue;
                                        }
                                        $iconKey   = isset($item['icon']) ? (string) $item['icon'] : '';
                                        $imagePath = isset($item['image']) ? (string) $item['image'] : '';
                                        $imageUrl  = $imagePath !== '' ? store_media_url($imagePath, '') : '';
                                        $initialRaw = function_exists('mb_substr') ? mb_substr($itemLabel, 0, 1, 'UTF-8') : substr($itemLabel, 0, 1);
                                        $initial   = $initialRaw !== null ? (function_exists('mb_strtoupper') ? mb_strtoupper($initialRaw, 'UTF-8') : strtoupper((string) $initialRaw)) : '';
                                        ?>
                                        <a class="store-nav__link" href="<?= htmlspecialchars($itemUrl, ENT_QUOTES, 'UTF-8') ?>" role="menuitem">
                                            <?php if ($imageUrl !== ''): ?>
                                                <span class="store-nav__media"><img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') ?>" loading="lazy"></span>
                                            <?php else: ?>
                                                <span class="store-nav__icon cat-icon cat-icon--<?= htmlspecialchars($iconKey, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                            <span class="store-nav__text"><?= htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($primaryUrl !== ''): ?>
                                    <div class="store-nav__footer">
                                        <a class="store-nav__cta" href="<?= htmlspecialchars($primaryUrl, ENT_QUOTES, 'UTF-8') ?>">Tüm ürünleri görüntüle</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </nav>
    <?php endif; ?>
    </div>

    <?php if ($hasMegaMenu): ?>
        <template id="storeMobileMenuTemplate">
            <div class="mobile-menu__content">
                <div class="mobile-menu__account d-grid gap-2 mb-3">
                    <?php if ($isLoggedIn): ?>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>">Hesabım</a>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($ordersUrl, ENT_QUOTES, 'UTF-8') ?>">Siparişlerim</a>
                        <?php if ($isAdmin): ?>
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(admin_base_url(), ENT_QUOTES, 'UTF-8') ?>">Admin Paneli</a>
                        <?php endif; ?>
                        <?php if ($isReseller): ?>
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(reseller_base_url(), ENT_QUOTES, 'UTF-8') ?>">Bayi Paneli</a>
                        <?php endif; ?>
                        <form method="post" action="<?= htmlspecialchars($logoutAction, ENT_QUOTES, 'UTF-8') ?>" class="d-grid">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($logoutToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-danger">Çıkış Yap</button>
                        </form>
                    <?php else: ?>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Giriş Yap</a>
                        <a class="btn btn-primary" href="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>">Kayıt Ol</a>
                    <?php endif; ?>
                </div>
                <div class="mobile-menu__groups">
                    <?php foreach ($megaMenuGroups as $index => $group): ?>
                        <?php
                        $groupId   = (int) $group['id'];
                        $groupName = isset($group['name']) ? (string) $group['name'] : 'Kategori';
                        $items     = isset($group['items']) && is_array($group['items']) ? $group['items'] : array();
                        ?>
                        <div class="mobile-menu__group">
                            <button class="mobile-menu__group-toggle" type="button" data-mobile-group-toggle aria-expanded="false">
                                <span><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></span>
                                <i class="bi bi-chevron-down" aria-hidden="true"></i>
                            </button>
                            <div class="mobile-menu__group-body" hidden>
                                <div class="d-grid gap-2">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $itemLabel = isset($item['label']) ? (string) $item['label'] : '';
                                        $itemUrl   = isset($item['url']) ? (string) $item['url'] : '';
                                        if ($itemLabel === '' || $itemUrl === '') {
                                            continue;
                                        }
                                        ?>
                                        <a class="store-nav__mobile-link" href="<?= htmlspecialchars($itemUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') ?></a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </template>
    <?php endif; ?>
</header>
