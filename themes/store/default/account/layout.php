<?php
use App\Helpers;
use App\Lang;
use App\Settings;

if (!isset($user) && isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
}

Lang::boot();

$siteName = Helpers::siteName();
$siteTagline = Helpers::siteTagline();
$pageTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle : 'Müşteri Paneli';
$pageDescription = isset($pageDescription) && $pageDescription !== ''
    ? (string) $pageDescription
    : 'Hesabınızı yönetin, siparişlerinizi görüntüleyin ve destek talepleri oluşturun.';
$activeMenu = isset($activeMenu) ? (string)$activeMenu : '';
$content = isset($content) ? (string)$content : '';

$nameSource = isset($user['name']) && $user['name'] !== '' ? $user['name'] : (isset($user['email']) ? $user['email'] : '');
if ($nameSource === '') {
    $nameSource = 'M';
}

if (function_exists('mb_substr')) {
    $avatarInitial = mb_strtoupper(mb_substr($nameSource, 0, 1, 'UTF-8'), 'UTF-8');
} else {
    $avatarInitial = strtoupper(substr($nameSource, 0, 1));
}

$pageScripts = isset($GLOBALS['pageScripts']) && is_array($GLOBALS['pageScripts']) ? $GLOBALS['pageScripts'] : array();
$pageInlineScripts = isset($GLOBALS['pageInlineScripts']) && is_array($GLOBALS['pageInlineScripts']) ? $GLOBALS['pageInlineScripts'] : array();

$menuItems = array(
    array('key' => 'profile', 'label' => 'Kullanıcı Bilgilerim', 'href' => '/account/index.php', 'icon' => 'ri-user-line'),
    array('key' => 'orders', 'label' => 'Siparişlerim', 'href' => '/account/orders.php', 'icon' => 'ri-shopping-basket-line'),
    array('key' => 'support', 'label' => 'Destek Taleplerim', 'href' => '/account/support.php', 'icon' => 'ri-hand-heart-line'),
    array('key' => 'password', 'label' => 'Şifre Değişikliği', 'href' => '/account/password.php', 'icon' => 'ri-lock-unlock-line'),
);

$menuItems[] = array('key' => 'logout', 'label' => 'Çıkış Yap', 'href' => '/logout.php', 'icon' => 'ri-logout-circle-line');

?>
<!DOCTYPE html>
<html lang="<?= Lang::htmlLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Helpers::sanitize($pageTitle) ?> | <?= Helpers::sanitize($siteName) ?></title>
    <meta name="description" content="<?= Helpers::sanitize(Helpers::seoDescription()) ?>">
    <meta name="keywords" content="<?= Helpers::sanitize(Helpers::seoKeywords()) ?>">
    <link rel="shortcut icon" href="<?= Helpers::sanitize(Settings::get('site_favicon') ?? '/assets/favicon.ico') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/account.css">
</head>
<body class="account-app">
<header class="account-header shadow-sm">
    <div class="container">
        <div class="account-header__inner">
            <div class="account-brand">
                <a href="/" class="account-brand__logo" aria-label="<?= Helpers::sanitize($siteName) ?>">
                    <?= Helpers::sanitize($siteName) ?>
                </a>
                <?php if ($siteTagline): ?>
                    <span class="account-brand__tagline"><?= Helpers::sanitize($siteTagline) ?></span>
                <?php endif; ?>
            </div>
            <div class="account-user">
                <span class="account-user__avatar"><?= Helpers::sanitize($avatarInitial) ?></span>
                <div class="account-user__meta">
                    <span class="account-user__name"><?= Helpers::sanitize($user['name'] ?? 'Misafir') ?></span>
                    <span class="account-user__email"><?= Helpers::sanitize($user['email'] ?? '') ?></span>
                </div>
            </div>
        </div>
    </div>
</header>
<main class="account-main py-5">
    <div class="container">
        <div class="row g-4 account-shell">
            <aside class="col-xl-3 col-lg-4 order-2 order-lg-1">
                <div class="card border-0 shadow-sm account-profile">
                    <div class="card-body text-center">
                        <div class="account-profile__avatar mx-auto mb-3">
                            <span><?= Helpers::sanitize($avatarInitial) ?></span>
                        </div>
                        <h5 class="account-profile__name mb-1"><?= Helpers::sanitize($user['name'] ?? '') ?></h5>
                        <p class="account-profile__email mb-0"><?= Helpers::sanitize($user['email'] ?? '') ?></p>
                    </div>
                </div>
                <nav class="card border-0 shadow-sm account-menu mt-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Menü</span>
                        <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-account-menu-toggle aria-expanded="false" aria-controls="accountMenuItems">
                            <i class="ri-menu-3-line"></i>
                        </button>
                    </div>
                    <ul class="list-unstyled mb-0" id="accountMenuItems">
                        <?php foreach ($menuItems as $item): ?>
                            <?php $isActive = $activeMenu === $item['key']; ?>
                            <li>
                                <a href="<?= $item['href'] ?>" class="account-menu__link <?= $isActive ? 'active' : '' ?>" <?= $item['key'] === 'logout' ? 'data-account-logout' : '' ?>>
                                    <i class="<?= Helpers::sanitize($item['icon']) ?> account-menu__icon"></i>
                                    <span><?= Helpers::sanitize($item['label']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </aside>
            <section class="col-xl-9 col-lg-8 order-1 order-lg-2">
                <div class="account-content card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
                            <div>
                                <h4 class="mb-0 account-content__title"><?= Helpers::sanitize($pageTitle) ?></h4>
                                <p class="mb-0 text-muted"><?= Helpers::sanitize($pageDescription) ?></p>
                            </div>
                            <span class="badge rounded-pill account-balance">Bakiye: <?= Helpers::formatCurrencyHtml((float)($user['balance'] ?? 0)) ?></span>
                        </div>
                        <div class="account-flash-messages">
                            <?php $flashSuccess = Helpers::getFlash('success'); ?>
                            <?php $flashError = Helpers::getFlash('error'); ?>
                            <?php $flashWarning = Helpers::getFlash('warning'); ?>
                            <?php if (!empty($flashSuccess)): ?>
                                <div class="alert alert-success"><?= Helpers::sanitize($flashSuccess) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($flashError)): ?>
                                <div class="alert alert-danger"><?= Helpers::sanitize($flashError) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($flashWarning)): ?>
                                <div class="alert alert-warning"><?= Helpers::sanitize($flashWarning) ?></div>
                            <?php endif; ?>
                        </div>
                        <?= $content ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
<footer class="account-footer py-4 text-center">
    <small>© <?= date('Y') ?> <?= Helpers::sanitize($siteName) ?></small>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var toggles = document.querySelectorAll('[data-account-menu-toggle]');
        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                var target = document.getElementById('accountMenuItems');
                if (!target) { return; }
                var expanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                target.classList.toggle('open');
            });
        });

        document.querySelectorAll('[data-account-logout]').forEach(function (logoutLink) {
            logoutLink.addEventListener('click', function (event) {
                var confirmLogout = confirm('Hesabınızdan çıkış yapmak istediğinize emin misiniz?');
                if (!confirmLogout) {
                    event.preventDefault();
                }
            });
        });
    });
</script>
<?php foreach ($pageScripts as $script): ?>
    <script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
<?php foreach ($pageInlineScripts as $inlineScript): ?>
    <script><?= $inlineScript ?></script>
<?php endforeach; ?>
</body>
</html>
