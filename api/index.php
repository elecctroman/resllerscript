<?php
require __DIR__ . '/bootstrap.php';

use App\Api\Controllers\ApiKeysController;
use App\Api\Controllers\AuthController;
use App\Api\Controllers\DataController;
use App\Api\Controllers\ResellerController;
use App\Api\Controllers\RootController;
use App\Api\Router;

$router = new Router();

$router->get('/', [RootController::class, 'index']);
$router->get('/v1', [RootController::class, 'version']);
$router->get('/v1/ping', [RootController::class, 'ping']);
$router->post('/v1/auth/login', [AuthController::class, 'login']);
$router->get('/v1/reseller/profile', [ResellerController::class, 'profile']);
$router->post('/v1/api-keys/create', [ApiKeysController::class, 'create']);
$router->get('/v1/api-keys/list', [ApiKeysController::class, 'index']);
$router->post('/v1/api-keys/revoke', [ApiKeysController::class, 'revoke']);
$router->get('/v1/data/products', [DataController::class, 'products']);
$router->get('/v1/data/profile', [DataController::class, 'profile']);
$router->get('/v1/data/orders', [DataController::class, 'orders']);
$router->post('/v1/data/orders', [DataController::class, 'createOrder']);

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

if ($scriptDir !== '/' && $scriptDir !== '.') {
    if (strpos($requestUri, $scriptDir) === 0) {
        $requestUri = substr($requestUri, strlen($scriptDir));
    }
}

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $requestUri);
