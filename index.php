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

switch (true) {
    case $path === '/':
        require __DIR__ . '/store/pages/index.php';
        break;

    case $path === '/account/login':
        require __DIR__ . '/store/pages/account/login.php';
        break;

    case $path === '/account/register':
        require __DIR__ . '/store/pages/account/register.php';
        break;

    case $path === '/account':
        require __DIR__ . '/store/pages/account/profile.php';
        break;

    case $path === '/account/orders':
        require __DIR__ . '/store/pages/account/orders.php';
        break;

    case $path === '/account/logout' && $method === 'POST':
        require __DIR__ . '/store/pages/account/logout.php';
        break;

    case $path === '/account/logout':
        Helpers::redirect('/account/login');
        break;

    case $path === '/cart':
        require __DIR__ . '/store/pages/cart.php';
        break;

    case $path === '/checkout':
        require __DIR__ . '/store/pages/checkout.php';
        break;

    case $path === '/order':
        require __DIR__ . '/store/pages/order.php';
        break;

    case $path === '/search':
        require __DIR__ . '/store/pages/category.php';
        break;

    case $path === '/category':
        require __DIR__ . '/store/pages/category.php';
        break;

    case preg_match('#^/category/([^/]+)$#', $path, $matches):
        if (ctype_digit($matches[1])) {
            $_GET['id'] = (int) $matches[1];
        } else {
            $_GET['slug'] = $matches[1];
        }
        require __DIR__ . '/store/pages/category.php';
        break;

    case $path === '/product':
        require __DIR__ . '/store/pages/product.php';
        break;

    case preg_match('#^/product/([^/]+)$#', $path, $matches):
        if (ctype_digit($matches[1])) {
            $_GET['id'] = (int) $matches[1];
        } else {
            $_GET['slug'] = $matches[1];
        }
        require __DIR__ . '/store/pages/product.php';
        break;

    default:
        http_response_code(404);
        require __DIR__ . '/store/pages/errors/404.php';
        break;
}
