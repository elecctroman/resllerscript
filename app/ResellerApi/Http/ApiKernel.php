<?php declare(strict_types=1);

namespace App\ResellerApi\Http;

use App\ResellerApi\Exceptions\ApiException;
use App\ResellerApi\Services\ApiGateway;
use App\ResellerApi\Http\Controllers\ApiKeysController;
use App\ResellerApi\Http\Controllers\AuthController;
use App\ResellerApi\Http\Controllers\DataController;
use App\ResellerApi\Http\Controllers\ResellerController;

final class ApiKernel
{
    private ApiGateway $gateway;

    public function __construct(ApiGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function handle(Request $request): Response
    {
        $start = microtime(true);
        try {
            $response = $this->dispatch($request);
        } catch (ApiException $exception) {
            $payload = array(
                'success' => false,
                'error_code' => $exception->getCodeName(),
                'message' => $exception->getMessage(),
                'timestamp' => gmdate('c'),
            );
            $response = Response::json($payload, $exception->getHttpStatus());
        } catch (\Throwable $exception) {
            $payload = array(
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Beklenmeyen bir hata oluştu.',
                'timestamp' => gmdate('c'),
            );
            $response = Response::json($payload, 500);
        }

        $duration = (int) round((microtime(true) - $start) * 1000);
        $this->gateway->log($request, $response, $duration);

        return $response;
    }

    private function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = rtrim($request->path(), '/');
        if ($path === '') {
            $path = '/';
        }

        switch (true) {
            case $method === 'POST' && $path === '/api/v1/auth/login':
                return (new AuthController($this->gateway))->login($request);
            case $method === 'GET' && $path === '/api/v1/reseller/profile':
                return (new ResellerController($this->gateway))->profile($request);
            case $method === 'POST' && $path === '/api/v1/api-keys/create':
                return (new ApiKeysController($this->gateway))->create($request);
            case $method === 'GET' && $path === '/api/v1/api-keys/list':
                return (new ApiKeysController($this->gateway))->index($request);
            case $method === 'POST' && $path === '/api/v1/api-keys/revoke':
                return (new ApiKeysController($this->gateway))->revoke($request);
            case $method === 'GET' && $path === '/api/v1/products':
                return (new DataController($this->gateway))->products($request);
            case $method === 'POST' && $path === '/api/v1/order/create':
                return (new DataController($this->gateway))->createOrder($request);
            case $method === 'GET' && $path === '/api/v1/order/status':
                return (new DataController($this->gateway))->orderStatus($request);
            case $method === 'GET' && $path === '/api/v1/balance':
                return (new DataController($this->gateway))->balance($request);
            case $method === 'GET' && $path === '/api/v1/user/info':
                return (new DataController($this->gateway))->userInfo($request);
            default:
                throw ApiException::notFound('İstenen uç nokta bulunamadı.');
        }
    }
}
