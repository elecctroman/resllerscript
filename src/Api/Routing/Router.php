<?php declare(strict_types=1);

namespace App\Api\Routing;

use App\Api\Exceptions\ApiException;
use App\Api\Http\ApiResponse;
use App\Api\Http\Request;
use Throwable;

/**
 * Basit rota tanımlama ve dağıtım mekanizması.
 */
final class Router
{
    private string $basePath;
    /** @var array<int,array{method:string,path:string,handler:callable}> */
    private array $routes = array();

    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath;
    }

    public function get(string $path, callable $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): self
    {
        return $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable $handler): self
    {
        return $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): self
    {
        return $this->add('DELETE', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): self
    {
        $normalized = $this->normalizePath($path);
        $this->routes[] = array(
            'method' => strtoupper($method),
            'path' => $normalized,
            'handler' => $handler,
        );

        return $this;
    }

    public function dispatch(Request $request): void
    {
        $requestPath = $this->normalizePath($request->getPath());
        $method = strtoupper($request->getMethod());

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if ($route['path'] !== $requestPath) {
                continue;
            }

            $this->invoke($route['handler'], $request);
            return;
        }

        json_response(array(
            'success' => false,
            'error' => 'İstenen uç nokta bulunamadı.',
            'meta' => array(
                'path' => $request->getFullPath(),
            ),
        ), 404);
    }

    private function invoke(callable $handler, Request $request): void
    {
        try {
            $result = $handler($request);
        } catch (ApiException $apiException) {
            $payload = array(
                'success' => false,
                'error' => $apiException->getMessage(),
            );

            $errors = $apiException->getErrors();
            if ($errors !== array()) {
                $payload['errors'] = $errors;
            }

            json_response($payload, $apiException->getStatusCode());
            return;
        } catch (Throwable $throwable) {
            error_log('[API] Unhandled exception: ' . $throwable->getMessage());
            json_response(array(
                'success' => false,
                'error' => 'Beklenmeyen bir hata oluştu.',
            ), 500);
            return;
        }

        if ($result instanceof ApiResponse) {
            json_response($result->getPayload(), $result->getStatus());
            return;
        }

        if (is_array($result)) {
            $status = 200;
            if (isset($result['_status'])) {
                $status = (int) $result['_status'];
                unset($result['_status']);
            }

            json_response($result, $status);
            return;
        }

        if ($result === null) {
            return;
        }

        json_response(array('data' => $result), 200);
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . ltrim($path, '/');
        if ($normalized !== '/' && str_ends_with($normalized, '/')) {
            $normalized = rtrim($normalized, '/');
        }

        if ($normalized === '') {
            return '/';
        }

        return $normalized;
    }
}
