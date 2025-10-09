<?php

use App\Auth;
use App\Helpers;

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
$cartUrl      = store_url('cart');
$loginUrl     = store_url('account/login');
$registerUrl  = store_url('account/register');
$accountUrl   = store_url('account');
$ordersUrl    = store_url('account/orders');
$panelUrl     = store_url('panel/index');
$logoutAction = store_url('account/logout');
$logoutToken  = Helpers::csrfToken();

$sessionUser = Auth::currentUser();
$isLoggedIn  = Auth::check();
$displayName = 'Hesabım';
if ($sessionUser) {
    if (!empty($sessionUser['name'])) {
        $displayName = (string) $sessionUser['name'];
    } elseif (!empty($sessionUser['email'])) {
        $displayName = (string) $sessionUser['email'];
    }
}

$cartCount = store_cart_count();
$whatsAppSetting = (string) get_setting('whatsapp_url', '');
$whatsAppUrl = $whatsAppSetting !== '' ? $whatsAppSetting : 'https://wa.me/905555555555';

$categories = store_header_categories();
$menuLinks = array(
    array('label' => 'Ana Sayfa', 'url' => $homeUrl),
    array('label' => 'Özellikler', 'url' => store_url('ozellikler')),
    array('label' => 'Merak Edilenler', 'url' => store_url('merak-edilenler')),
    array('label' => 'Blog', 'url' => store_url('blog')),
    array('label' => 'İletişim', 'url' => store_url('iletisim')),
);
?>
<div class="dj-announcement" role="note">
    <div class="dj-announcement__inner">
        <span class="dj-announcement__text">Türkiye’nin en büyük E-Pin satış platformu!</span>
        <a class="dj-announcement__link" href="<?= htmlspecialchars(store_url(''), ENT_QUOTES, 'UTF-8') ?>">ŞİMDİ SATIN AL</a>
    </div>
</div>
<header class="dj-head" role="banner" data-store-header>
    <div class="dj-bar">
        <div class="dj-bar__inner">
            <a class="dj-logo" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Ana sayfa">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                <?php else: ?>
                    <span><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </a>

            <nav class="dj-nav" aria-label="Ana menü">
                <div class="dj-dd" data-dj-dropdown>
                    <button class="dj-dd__btn" type="button" data-dj-trigger aria-haspopup="true" aria-expanded="false" aria-controls="djCategoriesMenu">
                        Kategoriler
                        <span class="dj-chev" aria-hidden="true"></span>
                    </button>
                    <div class="dj-dd__menu" id="djCategoriesMenu" role="menu" hidden>
                        <?php if ($categories): ?>
                            <?php foreach ($categories as $category): ?>
                                <a class="dj-dd__item" role="menuitem" href="<?= htmlspecialchars($category['url'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="dj-dd__empty">Kategori bulunamadı</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php foreach ($menuLinks as $link): ?>
                    <?php if ($link['label'] === 'Kategoriler'): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <a class="dj-link" href="<?= htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </nav>

            <div class="dj-actions">
                <a class="dj-btn dj-wa" href="<?= htmlspecialchars($whatsAppUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                    <i class="bi bi-whatsapp"></i>
                    <span>WhatsApp</span>
                </a>
                <?php if ($isLoggedIn): ?>
                    <a class="dj-btn account-btn" href="<?= htmlspecialchars($panelUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Hesabım">
                        <i class="bi bi-person"></i>
                        <span>Hesabım</span>
                    </a>
                <?php else: ?>
                    <div class="dj-auth">
                        <a class="dj-btn dj-primary" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Giriş Yap</a>
                        <a class="dj-btn dj-ghost" href="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>">Kayıt Ol</a>
                    </div>
                <?php endif; ?>
                <a class="dj-cart" href="<?= htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Sepet">
                    <i class="bi bi-bag"></i>
                    <span class="dj-badge" data-cart-count><?= (int) $cartCount ?></span>
                </a>
                <button class="dj-ham" type="button" data-dj-mobile-trigger aria-haspopup="true" aria-expanded="false" aria-controls="djMobilePanel" aria-label="Menüyü aç">
                    <i class="bi bi-list"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="dj-mobile" id="djMobilePanel" data-dj-mobile hidden aria-hidden="true">
        <div class="dj-mobile__header">
            <span class="dj-mobile__title">Menü</span>
            <button class="dj-mobile__close" type="button" data-dj-mobile-close aria-label="Menüyü kapat">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="dj-mobile__body">
            <?php if ($isLoggedIn): ?>
                <div class="dj-mobile__section">
                    <p class="dj-mobile__hello">Hoş geldin, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></p>
                    <a class="dj-mobile__link" href="<?= htmlspecialchars($panelUrl, ENT_QUOTES, 'UTF-8') ?>">Hesabım</a>
                    <a class="dj-mobile__link" href="<?= htmlspecialchars($ordersUrl, ENT_QUOTES, 'UTF-8') ?>">Siparişlerim</a>
                    <form method="post" action="<?= htmlspecialchars($logoutAction, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($logoutToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="dj-mobile__link dj-mobile__link--danger">Çıkış Yap</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="dj-mobile__section dj-mobile__auth">
                    <a class="dj-btn dj-primary w-100" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Giriş Yap</a>
                    <a class="dj-btn dj-ghost w-100" href="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>">Kayıt Ol</a>
                </div>
            <?php endif; ?>

            <div class="dj-mobile__section">
                <h2 class="dj-mobile__heading">Kategoriler</h2>
                <?php if ($categories): ?>
                    <?php foreach ($categories as $category): ?>
                        <a class="dj-mobile__link" href="<?= htmlspecialchars($category['url'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="dj-mobile__empty">Kategori bulunamadı</span>
                <?php endif; ?>
            </div>

            <div class="dj-mobile__section">
                <h2 class="dj-mobile__heading">Menü</h2>
                <?php foreach ($menuLinks as $link): ?>
                    <a class="dj-mobile__link" href="<?= htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="dj-mobile__backdrop" data-dj-mobile-backdrop hidden></div>
</header>
