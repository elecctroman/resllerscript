<?php
use App\Auth;

$viewData = array();
if (isset($storeViewContext) && is_object($storeViewContext) && isset($storeViewContext->data) && is_array($storeViewContext->data)) {
    $viewData = $storeViewContext->data;
}

$storeBaseUrl = envStr('STORE_BASE_URL', '/magaza');
$storeBaseUrl = trim($storeBaseUrl) === '' ? '/magaza' : rtrim($storeBaseUrl, '/');
if ($storeBaseUrl === '/') {
    $storeBaseUrl = '';
}

$homeUrl = ($storeBaseUrl !== '' ? $storeBaseUrl : '/magaza') . '/index.php';
$searchAction = ($storeBaseUrl !== '' ? $storeBaseUrl : '/magaza') . '/ara';

$appBaseUrl = envStr('APP_BASE_URL', '');
$appBaseUrl = $appBaseUrl !== null ? trim($appBaseUrl) : '';
$appBaseUrl = $appBaseUrl !== '' ? rtrim($appBaseUrl, '/') : '';

$adminBaseUrl = envStr('ADMIN_BASE_URL', '');
if ($adminBaseUrl === '' && $appBaseUrl !== '') {
    $adminBaseUrl = $appBaseUrl . '/admin';
}
if ($adminBaseUrl === '') {
    $adminBaseUrl = '/admin';
}

$bayiBaseUrl = envStr('BAYI_BASE_URL', '');
if ($bayiBaseUrl === '' && $appBaseUrl !== '') {
    $bayiBaseUrl = $appBaseUrl . '/bayi';
}
if ($bayiBaseUrl === '') {
    $bayiBaseUrl = '/bayi';
}

$loginUrl = isset($viewData['loginUrl']) ? (string) $viewData['loginUrl'] : $bayiBaseUrl . '/login.php';
$registerUrl = isset($viewData['registerUrl']) ? (string) $viewData['registerUrl'] : '/register.php';
$accountUrl = isset($viewData['accountUrl']) ? (string) $viewData['accountUrl'] : '/profile.php';
$ordersUrl = isset($viewData['ordersUrl']) ? (string) $viewData['ordersUrl'] : '/orders.php';
$logoutUrl = isset($viewData['logoutUrl']) ? (string) $viewData['logoutUrl'] : '/logout.php';

$rawCategories = array(
    array('name' => 'PUBG', 'url' => ($storeBaseUrl !== '' ? $storeBaseUrl : '/magaza') . '/category.php?slug=pubg', 'icon' => 'pubg'),
    array('name' => 'Valorant', 'url' => ($storeBaseUrl !== '' ? $storeBaseUrl : '/magaza') . '/category.php?slug=valorant', 'icon' => 'valorant'),
    array('name' => 'Windows', 'url' => ($storeBaseUrl !== '' ? $storeBaseUrl : '/magaza') . '/category.php?slug=windows', 'icon' => 'windows'),
    array('name' => 'Semrush', 'url' => ($storeBaseUrl !== '' ? $storeBaseUrl : '/magaza') . '/category.php?slug=semrush', 'icon' => 'semrush'),
    array('name' => 'Adobe', 'url' => ($storeBaseUrl !== '' ? $storeBaseUrl : '/magaza') . '/category.php?slug=adobe', 'icon' => 'adobe'),
    array('name' => 'Freepik', 'url' => ($storeBaseUrl !== '' ? $storeBaseUrl : '/magaza') . '/category.php?slug=freepik', 'icon' => 'freepik'),
    array('name' => 'Canva', 'url' => ($storeBaseUrl !== '' ? $storeBaseUrl : '/magaza') . '/category.php?slug=canva', 'icon' => 'canva'),
    array('name' => 'Shutterstock', 'url' => ($storeBaseUrl !== '' ? $storeBaseUrl : '/magaza') . '/category.php?slug=shutterstock', 'icon' => 'shutterstock'),
    array('name' => 'Elementor', 'url' => ($storeBaseUrl !== '' ? $storeBaseUrl : '/magaza') . '/category.php?slug=elementor', 'icon' => 'elementor'),
);

if (isset($viewData['headerCategories']) && is_array($viewData['headerCategories']) && $viewData['headerCategories']) {
    $rawCategories = $viewData['headerCategories'];
}

$categories = array();
foreach ($rawCategories as $category) {
    if (!is_array($category)) {
        continue;
    }
    $name = isset($category['name']) ? trim((string) $category['name']) : '';
    $url = isset($category['url']) ? (string) $category['url'] : '';
    $icon = isset($category['icon']) ? (string) $category['icon'] : '';
    if ($name === '') {
        continue;
    }
    if ($url === '') {
        $url = ($storeBaseUrl !== '' ? $storeBaseUrl : '/magaza') . '/category.php?slug=' . rawurlencode(strtolower(str_replace(' ', '-', $name)));
    }
    $categories[] = array(
        'name' => $name,
        'url' => $url,
        'icon' => $icon,
    );
}

$sessionUser = null;
if (class_exists(Auth::class)) {
    $sessionUser = Auth::currentUser();
}

$isLoggedIn = is_array($sessionUser);

$isAdmin = !empty($_SESSION['is_admin']);
if (!$isAdmin && class_exists(Auth::class)) {
    $isAdmin = Auth::currentAdmin() ? true : false;
    if (!$isAdmin && $sessionUser && isset($sessionUser['role']) && method_exists(Auth::class, 'isAdminRole')) {
        $isAdmin = Auth::isAdminRole($sessionUser['role']);
    }
}

$isReseller = !empty($_SESSION['is_reseller']);
if (!$isReseller && class_exists(Auth::class)) {
    $isReseller = Auth::currentReseller() ? true : false;
    if (!$isReseller && $sessionUser && isset($sessionUser['role']) && $sessionUser['role'] === 'reseller') {
        $isReseller = true;
    }
}

$displayName = 'Misafir';
if ($sessionUser && isset($sessionUser['name']) && $sessionUser['name'] !== '') {
    $displayName = (string) $sessionUser['name'];
} elseif ($sessionUser && isset($sessionUser['email'])) {
    $displayName = (string) $sessionUser['email'];
}

$avatarInitial = strtoupper(substr($displayName, 0, 1));
if (function_exists('mb_substr')) {
    $firstChar = mb_substr($displayName, 0, 1, 'UTF-8');
    if ($firstChar !== null && $firstChar !== '') {
        $avatarInitial = strtoupper($firstChar);
    }
}

$brandText = isset($viewData['brandText']) ? (string) $viewData['brandText'] : 'OyunHesap.com.tr';
?>
<header class="storefront-header">
    <nav class="storefront-navbar navbar navbar-expand-lg navbar-dark bg-primary py-3">
        <div class="container-xxl align-items-center gap-3">
            <a class="brand d-flex align-items-center" href="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($brandText, ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <div class="flex-grow-1 d-lg-none">
                <form action="<?php echo htmlspecialchars($searchAction, ENT_QUOTES, 'UTF-8'); ?>" method="get" class="search-form mt-3 mb-2" role="search">
                    <span class="search-icon" aria-hidden="true"></span>
                    <input class="search-input" type="search" name="q" placeholder="PUBG" aria-label="Mağazada ara">
                </form>
            </div>
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
                                <li><a class="dropdown-item" href="<?php echo htmlspecialchars($adminBaseUrl, ENT_QUOTES, 'UTF-8'); ?>">Admin</a></li>
                            <?php endif; ?>
                            <?php if ($isReseller): ?>
                                <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bayiBaseUrl, ENT_QUOTES, 'UTF-8'); ?>">Bayi</a></li>
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
                        <div class="card card-panel mb-3">
                            <div class="card-body d-flex align-items-center gap-3">
                                <span class="user-avatar" aria-hidden="true"><?php echo htmlspecialchars($avatarInitial, ENT_QUOTES, 'UTF-8'); ?></span>
                                <div>
                                    <p class="mb-1 fw-semibold"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php if ($isAdmin): ?>
                                            <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars($adminBaseUrl, ENT_QUOTES, 'UTF-8'); ?>">Admin</a>
                                        <?php endif; ?>
                                        <?php if ($isReseller): ?>
                                            <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars($bayiBaseUrl, ENT_QUOTES, 'UTF-8'); ?>">Bayi</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0 pt-0 d-flex flex-column gap-2">
                                <a class="btn btn-outline" href="<?php echo htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8'); ?>">Hesabım</a>
                                <a class="btn btn-outline" href="<?php echo htmlspecialchars($ordersUrl, ENT_QUOTES, 'UTF-8'); ?>">Siparişlerim</a>
                                <a class="btn btn-danger" href="<?php echo htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8'); ?>">Çıkış</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2">
                            <a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline">Giriş Yap</a>
                            <a href="<?php echo htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">Kayıt Ol</a>
                            <a href="<?php echo htmlspecialchars($bayiBaseUrl . '/login.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline">Bayi Girişi</a>
                            <a href="<?php echo htmlspecialchars($bayiBaseUrl . '/login.php?tab=application', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline">Bayi Başvurusu</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <?php if ($categories): ?>
        <div class="category-bar">
            <div class="container-xxl">
                <div class="cat-chips" role="navigation" aria-label="Kategori kısayolları">
                    <?php foreach ($categories as $category): ?>
                        <?php
                        $chipName = htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8');
                        $chipUrl = htmlspecialchars($category['url'], ENT_QUOTES, 'UTF-8');
                        $iconSlug = isset($category['icon']) && $category['icon'] !== '' ? preg_replace('/[^a-z0-9_-]+/i', '', $category['icon']) : '';
                        $iconLabel = '•';
                        if ($chipName !== '') {
                            $initial = substr((string) $category['name'], 0, 1);
                            if (function_exists('mb_substr')) {
                                $initial = mb_substr((string) $category['name'], 0, 1, 'UTF-8');
                            }
                            $iconLabel = strtoupper($initial);
                        }
                        ?>
                        <a class="cat-chip" href="<?php echo $chipUrl; ?>">
                            <span class="chip-icon<?php echo $iconSlug !== '' ? ' icon-' . strtolower($iconSlug) : ''; ?>" aria-hidden="true"><?php echo $iconLabel; ?></span>
                            <span><?php echo $chipName; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</header>
