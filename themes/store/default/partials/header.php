<?php
use App\Auth;

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
$searchAction = store_url('category.php');
$loginUrl = isset($viewData['loginUrl']) ? (string) $viewData['loginUrl'] : store_url('bayi/login.php');
$registerUrl = isset($viewData['registerUrl']) ? (string) $viewData['registerUrl'] : store_url('register.php');
$accountUrl = isset($viewData['accountUrl']) ? (string) $viewData['accountUrl'] : store_url('profile.php');
$ordersUrl = isset($viewData['ordersUrl']) ? (string) $viewData['ordersUrl'] : store_url('orders.php');
$logoutUrl = isset($viewData['logoutUrl']) ? (string) $viewData['logoutUrl'] : store_url('logout.php');

$sessionUser = class_exists(Auth::class) ? Auth::currentUser() : null;
$isLoggedIn = is_array($sessionUser);

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
        $avatarInitial = strtoupper($firstChar);
    }
}

$isAdmin = is_admin();
$isReseller = is_reseller();

$headerCategories = array();
if (isset($viewData['headerCategories']) && is_array($viewData['headerCategories'])) {
    foreach ($viewData['headerCategories'] as $category) {
        if (!is_array($category)) {
            continue;
        }
        $name = isset($category['name']) ? trim((string) $category['name']) : '';
        if ($name === '') {
            continue;
        }
        $url = isset($category['url']) ? (string) $category['url'] : store_url('category.php');
        $icon = isset($category['icon']) ? (string) $category['icon'] : '';
        $headerCategories[] = array(
            'name' => $name,
            'url' => $url,
            'icon' => $icon,
        );
    }
}

if (!$headerCategories) {
    $defaultCategories = array('PUBG', 'Valorant', 'Windows', 'Semrush', 'Adobe', 'Freepik', 'Canva', 'Shutterstock', 'Elementor');
    foreach ($defaultCategories as $categoryName) {
        $headerCategories[] = array(
            'name' => $categoryName,
            'url' => store_url('category.php?q=' . rawurlencode($categoryName)),
            'icon' => '',
        );
    }
}

?>
<header class="storefront-header">
    <nav class="storefront-navbar navbar navbar-expand-lg navbar-dark bg-primary py-3">
        <div class="container-xxl align-items-center gap-3">
            <a class="brand d-flex align-items-center" href="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>" class="brand-logo" height="38">
                <?php else: ?>
                    <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#storefrontNavbar" aria-controls="storefrontNavbar" aria-expanded="false" aria-label="Menüyü Aç">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="search-wrapper d-none d-lg-flex flex-grow-1 mx-4">
                <form action="<?php echo htmlspecialchars($searchAction, ENT_QUOTES, 'UTF-8'); ?>" method="get" class="search-form" role="search">
                    <span class="search-icon" aria-hidden="true"></span>
                    <input class="search-input" type="search" name="q" placeholder="PUBG" aria-label="Mağazada ara">
                </form>
            </div>
            <div class="header-actions d-none d-lg-flex align-items-center ms-auto gap-3">
                <button type="button" class="btn btn-icon" aria-label="Bildirimler">
                    <span class="icon-bell" aria-hidden="true"></span>
                </button>
                <div class="dropdown">
                    <button class="btn btn-outline dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Türkçe</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted">Türkçe (Varsayılan)</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><span class="dropdown-item disabled">English (Yakında)</span></li>
                    </ul>
                </div>
                <?php if ($isLoggedIn): ?>
                    <div class="dropdown">
                        <button class="btn user-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="user-avatar" aria-hidden="true"><?php echo htmlspecialchars($avatarInitial, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="user-name d-none d-xl-inline"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end user-menu shadow-sm border-0 rounded-3">
                            <li class="px-3 py-2 text-muted small">Hoş geldin, <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>!</li>
                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8'); ?>">Hesabım</a></li>
                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($ordersUrl, ENT_QUOTES, 'UTF-8'); ?>">Siparişlerim</a></li>
                            <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item" href="<?php echo htmlspecialchars(admin_base_url(), ENT_QUOTES, 'UTF-8'); ?>">Admin</a></li>
                            <?php endif; ?>
                            <?php if ($isReseller): ?>
                                <li><a class="dropdown-item" href="<?php echo htmlspecialchars(reseller_base_url(), ENT_QUOTES, 'UTF-8'); ?>">Bayi</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8'); ?>">Çıkış</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-2">
                        <a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline">Giriş Yap</a>
                        <a href="<?php echo htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">Kayıt Ol</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="collapse navbar-collapse" id="storefrontNavbar">
                <div class="py-4 d-lg-none">
                    <form action="<?php echo htmlspecialchars($searchAction, ENT_QUOTES, 'UTF-8'); ?>" method="get" class="search-form mb-3" role="search">
                        <span class="search-icon" aria-hidden="true"></span>
                        <input class="search-input" type="search" name="q" placeholder="PUBG" aria-label="Mağazada ara">
                    </form>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <button type="button" class="btn btn-icon" aria-label="Bildirimler">
                            <span class="icon-bell" aria-hidden="true"></span>
                        </button>
                        <div class="dropdown flex-grow-1">
                            <button class="btn btn-outline dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">Türkçe</button>
                            <ul class="dropdown-menu">
                                <li><span class="dropdown-item-text text-muted">Türkçe (Varsayılan)</span></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><span class="dropdown-item disabled">English (Yakında)</span></li>
                            </ul>
                        </div>
                    </div>
                    <?php if ($isLoggedIn): ?>
                        <div class="list-group mb-3">
                            <a class="list-group-item list-group-item-action" href="<?php echo htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8'); ?>">Hesabım</a>
                            <a class="list-group-item list-group-item-action" href="<?php echo htmlspecialchars($ordersUrl, ENT_QUOTES, 'UTF-8'); ?>">Siparişlerim</a>
                            <?php if ($isAdmin): ?>
                                <a class="list-group-item list-group-item-action" href="<?php echo htmlspecialchars(admin_base_url(), ENT_QUOTES, 'UTF-8'); ?>">Admin</a>
                            <?php endif; ?>
                            <?php if ($isReseller): ?>
                                <a class="list-group-item list-group-item-action" href="<?php echo htmlspecialchars(reseller_base_url(), ENT_QUOTES, 'UTF-8'); ?>">Bayi</a>
                            <?php endif; ?>
                            <a class="list-group-item list-group-item-action text-danger" href="<?php echo htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8'); ?>">Çıkış</a>
                        </div>
                    <?php else: ?>
                        <div class="d-grid gap-2 mb-3">
                            <a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline">Giriş Yap</a>
                            <a href="<?php echo htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">Kayıt Ol</a>
                        </div>
                    <?php endif; ?>
                </div>
                <ul class="navbar-nav mx-lg-auto nav-categories">
                    <?php foreach ($headerCategories as $category): ?>
                        <?php
                        $name = (string) $category['name'];
                        $url = (string) $category['url'];
                        $icon = isset($category['icon']) ? (string) $category['icon'] : '';
                        $initial = substr($name, 0, 1);
                        if (function_exists('mb_substr')) {
                            $initial = mb_substr($name, 0, 1, 'UTF-8');
                        }
                        ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="chip-icon" aria-hidden="true">
                                    <?php echo $icon !== '' ? htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') : htmlspecialchars(strtoupper($initial), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-lg-none border-top pt-3">
                    <div class="d-flex align-items-center gap-3">
                        <button type="button" class="btn btn-icon" aria-label="Bildirimler">
                            <span class="icon-bell" aria-hidden="true"></span>
                        </button>
                        <span class="text-muted small">Bildirimler yakında aktif olacak.</span>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <div class="category-chips">
        <div class="cat-chips" role="navigation" aria-label="Popüler kategoriler">
            <?php foreach ($headerCategories as $category): ?>
                <?php
                $name = (string) $category['name'];
                $url = (string) $category['url'];
                $icon = isset($category['icon']) ? (string) $category['icon'] : '';
                $initial = substr($name, 0, 1);
                if (function_exists('mb_substr')) {
                    $initial = mb_substr($name, 0, 1, 'UTF-8');
                }
                ?>
                <a class="cat-chip" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="chip-icon" aria-hidden="true"><?php echo $icon !== '' ? htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') : htmlspecialchars(strtoupper($initial), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</header>
