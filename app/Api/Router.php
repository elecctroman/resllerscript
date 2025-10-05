<?php declare(strict_types=1);

namespace App\Api;

use Closure;
use InvalidArgumentException;

final class Router
{
    /**
     * @var array<int,array{methods:array<int,string>,pattern:string,regex:string,handler:mixed,variables:array<int,string>}>
     */
    private array $routes = array();

    /**
     * Register a route.
     *
     * @param string|array<int,string> $methods
     * @param string $pattern
     * @param mixed  $handler
     * @return void
     */
    public function map($methods, string $pattern, $handler): void
    {
        $methodList = is_array($methods) ? $methods : array($methods);
        if (!$methodList) {
            throw new InvalidArgumentException('At least one HTTP method must be provided.');
        }

        $normalizedMethods = array();
        foreach ($methodList as $method) {
            $method = strtoupper((string)$method);
            if ($method === '') {
                continue;
            }
            if (!in_array($method, $normalizedMethods, true)) {
                $normalizedMethods[] = $method;
            }
        }

        if (!$normalizedMethods) {
            throw new InvalidArgumentException('Invalid HTTP methods supplied.');
        }

        $pattern = '/' . ltrim($pattern, '/');
        $pattern = $pattern !== '/' ? rtrim($pattern, '/') : '/';

        $variables = array();
        if (preg_match_all('#\{([a-zA-Z_][a-zA-Z0-9_-]*)\}#', $pattern, $matches)) {
            $variables = $matches[1];
        }

        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_-]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '/?$#';

        $this->routes[] = array(
            'methods' => $normalizedMethods,
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
            'variables' => $variables,
        );
    }

    /**
     * Convenience helper for GET routes.
     *
     * @param string $pattern
     * @param mixed  $handler
     * @return void
     */
    public function get(string $pattern, $handler): void
    {
        $this->map(array('GET', 'HEAD'), $pattern, $handler);
    }

    /**
     * Convenience helper for POST routes.
     *
     * @param string $pattern
     * @param mixed  $handler
     * @return void
     */
    public function post(string $pattern, $handler): void
    {
        $this->map('POST', $pattern, $handler);
    }

    /**
     * Dispatch the current request.
     *
     * @param string $method
     * @param string $path
     * @return mixed
     */
    public function dispatch(string $method, string $path)
    {
        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');
        $path = $path !== '/' ? rtrim($path, '/') : '/';

        $allowedForPath = array();
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $allowedForPath = array_merge($allowedForPath, $route['methods']);

            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            $parameters = array();
            foreach ($route['variables'] as $variable) {
                if (isset($matches[$variable])) {
                    $parameters[] = $matches[$variable];
                }
            }

            return $this->invoke($route['handler'], $parameters);
        }

        if ($method === 'OPTIONS' && $allowedForPath) {
            $allow = array_unique(array_merge($allowedForPath, array('OPTIONS')));
            header('Allow: ' . implode(', ', $allow));
            http_response_code(204);
            exit;
        }

        if ($allowedForPath) {
            header('Allow: ' . implode(', ', array_unique($allowedForPath)));
            json_response(array('success' => false, 'error' => 'İstek yöntemi desteklenmiyor.'), 405);
        }

        json_response(array('success' => false, 'error' => 'Aradığınız API uç noktası bulunamadı.'), 404);
    }

    /**
     * @param mixed $handler
     * @param array<int,string> $parameters
     * @return mixed
     */
    private function invoke($handler, array $parameters)
    {
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            $target = $handler[0];
            $method = (string)$handler[1];

            if (is_string($target)) {
                if (!class_exists($target)) {
                    throw new InvalidArgumentException(sprintf('Controller %s bulunamadı.', $target));
                }
                $target = new $target();
            }

            if (!method_exists($target, $method)) {
                throw new InvalidArgumentException(sprintf('Metot %s::%s bulunamadı.', get_class($target), $method));
            }

            return call_user_func_array(array($target, $method), $parameters);
        }

        if ($handler instanceof Closure || is_callable($handler)) {
            return call_user_func_array($handler, $parameters);
        }

        throw new InvalidArgumentException('Geçersiz rota işleyicisi tanımlandı.');
    }
}
