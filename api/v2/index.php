<?php declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Api\Controllers\BalanceController;
use App\Api\Controllers\OrderController;
use App\Api\Controllers\ProductController;
use App\Api\Controllers\UserController;
use App\Api\Http\Request;
use App\Api\Routing\Router;

$request = Request::fromGlobals('/api/v2');
$router = new Router('/api/v2');

$router->get('/', function () {
    return array(
        'data' => array(
            'name' => 'Reseller REST API',
            'version' => '2.0.0',
        ),
    );
});

$router->get('/products', function (Request $request) {
    $token = authenticate_token();
    require_scope($token, 'products:read');
    $request->setToken($token);

    $controller = new ProductController();
    return $controller->index($request);
});

$router->post('/order/create', function (Request $request) {
    $token = authenticate_token();
    require_scope($token, 'orders:write');
    $request->setToken($token);

    $controller = new OrderController();
    return $controller->create($request);
});

$router->get('/order/status', function (Request $request) {
    $token = authenticate_token();
    require_scope($token, 'orders:read');
    $request->setToken($token);

    $controller = new OrderController();
    return $controller->status($request);
});

$router->get('/balance', function (Request $request) {
    $token = authenticate_token();
    require_scope($token, 'balance:read');
    $request->setToken($token);

    $controller = new BalanceController();
    return $controller->show($request);
});

$router->get('/user/info', function (Request $request) {
    $token = authenticate_token();
    require_scope($token, 'user:read');
    $request->setToken($token);

    $controller = new UserController();
    return $controller->info($request);
});

$router->dispatch($request);
