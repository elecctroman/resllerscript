<?php
require __DIR__ . '/bootstrap.php';

use App\Api\Controllers\OrdersController;
use App\Api\Controllers\ProductsController;
use App\Api\Controllers\ProfileController;
use App\Api\Controllers\RootController;
use App\Api\Controllers\TokenController;
use App\Api\Router;

$router = new Router();

$router->get('/', [RootController::class, 'index']);
$router->get('/v1', [RootController::class, 'version']);
$router->get('/v1/ping', [RootController::class, 'ping']);
$router->get('/v1/profile', [ProfileController::class, 'show']);
$router->get('/v1/products', [ProductsController::class, 'index']);
$router->get('/v1/orders', [OrdersController::class, 'index']);
$router->get('/v1/orders/{id}', [OrdersController::class, 'show']);
$router->post('/v1/orders', [OrdersController::class, 'create']);
$router->post('/v1/token-webhook', [TokenController::class, 'updateWebhook']);

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

if ($scriptDir !== '/' && $scriptDir !== '.') {
    if (strpos($requestUri, $scriptDir) === 0) {
        $requestUri = substr($requestUri, strlen($scriptDir));
    }
}

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $requestUri);
