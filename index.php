<?php
require_once __DIR__ . '/store/bootstrap.php';

use App\Helpers;

$method = $_SERVER['REQUEST_METHOD'];
$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = is_string($uriPath) ? $uriPath : '/';
$path = '/' . ltrim($path, '/');
if ($path !== '/' && substr($path, -1) === '/') {
    $path = rtrim($path, '/');
}

if ($path === '/index.php') {
    $path = '/';
}

$segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
$lang = null;
if ($segments && preg_match('/^[a-z]{2}$/', $segments[0])) {
    $lang = strtolower($segments[0]);
    array_shift($segments);
    $_GET['lang'] = $lang;
}

$first = $segments[0] ?? '';
$second = $segments[1] ?? '';

if ($first === 'api') {
    $apiEndpoint = $segments[1] ?? '';
    if ($apiEndpoint === 'search') {
        require __DIR__ . '/store/pages/api/search.php';
        return;
    }
    http_response_code(404);
    require __DIR__ . '/store/pages/errors/404.php';
    return;
}

if ($first === '') {
    require __DIR__ . '/store/pages/index.php';
    return;
}

if ($first === 'account') {
    $accountPage = $second ?? '';
    if ($accountPage === 'login') {
        require __DIR__ . '/store/pages/account/login.php';
        return;
    }
    if ($accountPage === 'register') {
        require __DIR__ . '/store/pages/account/register.php';
        return;
    }
    if ($accountPage === 'orders') {
        require __DIR__ . '/store/pages/account/orders.php';
        return;
    }
    if ($accountPage === 'logout') {
        if ($method === 'POST') {
            require __DIR__ . '/store/pages/account/logout.php';
        } else {
            Helpers::redirect('/account/login');
        }
        return;
    }
    require __DIR__ . '/store/pages/account/profile.php';
    return;
}

if ($first === 'cart') {
    $cartAction = $second ?? '';
    if ($cartAction === 'add' && $method === 'POST') {
        require __DIR__ . '/store/pages/cart-add.php';
        return;
    }
    if ($cartAction === 'update' && $method === 'POST') {
        require __DIR__ . '/store/pages/cart-update.php';
        return;
    }
    if ($cartAction === 'remove' && $method === 'POST') {
        require __DIR__ . '/store/pages/cart-remove.php';
        return;
    }

    require __DIR__ . '/store/pages/cart.php';
    return;
}

if ($first === 'checkout') {
    require __DIR__ . '/store/pages/checkout.php';
    return;
}

if ($first === 'order') {
    require __DIR__ . '/store/pages/order.php';
    return;
}

if ($first === 'arama') {
    if ($second !== '') {
        $_GET['q'] = rawurldecode($second);
    }
    require __DIR__ . '/store/pages/category.php';
    return;
}

if ($first === 'kategori') {
    if (isset($segments[2]) && ctype_digit($segments[2])) {
        $_GET['id'] = (int) $segments[2];
        $_GET['slug'] = $second;
    } elseif ($second !== '' && ctype_digit($second)) {
        $_GET['id'] = (int) $second;
    } else {
        $_GET['slug'] = $second;
    }
    require __DIR__ . '/store/pages/category.php';
    return;
}

if ($first === 'urun') {
    if (isset($segments[2]) && ctype_digit($segments[2])) {
        $_GET['id'] = (int) $segments[2];
        $_GET['slug'] = $second;
    } elseif ($second !== '' && ctype_digit($second)) {
        $_GET['id'] = (int) $second;
    } else {
        $_GET['slug'] = $second;
    }
    require __DIR__ . '/store/pages/product.php';
    return;
}

if ($first === 'search' || $first === 'category') {
    if ($second !== '') {
        $_GET['slug'] = $second;
    }
    require __DIR__ . '/store/pages/category.php';
    return;
}

if ($first === 'product') {
    if ($second !== '') {
        $_GET['slug'] = $second;
    }
    require __DIR__ . '/store/pages/product.php';
    return;
}

http_response_code(404);
require __DIR__ . '/store/pages/errors/404.php';
