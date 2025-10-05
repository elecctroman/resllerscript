<?php declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class ProviderApiClient
{
    private const DRIVER_DEFAULTS = array(
        'generic' => array(
            'products_endpoint' => '/api/products',
            'orders_endpoint' => '/api/orders',
            'status_endpoint' => '/api/user',
            'query_key' => 'apikey',
            'auth_scheme' => 'bearer',
        ),
        'netgsm' => array(
            'products_endpoint' => '/api/v2/catalog/products',
            'orders_endpoint' => '/api/v2/orders',
            'status_endpoint' => '/api/v2/account',
            'query_key' => 'api_key',
            'auth_scheme' => 'bearer',
        ),
        'turkpin' => array(
            'products_endpoint' => '/api/products',
            'orders_endpoint' => '/api/order',
            'status_endpoint' => '/api/profile',
            'query_key' => 'apikey',
            'auth_scheme' => 'bearer',
        ),
        'pinabi' => array(
            'products_endpoint' => '/api/v1/products',
            'orders_endpoint' => '/api/v1/orders',
            'status_endpoint' => '/api/v1/user',
            'query_key' => 'token',
            'auth_scheme' => 'token',
        ),
    );

    /**
     * @param array<string,mixed> $provider
     * @return array<string,mixed>
     */
    public static function fetchProducts(array $provider): array
    {
        $endpoint = self::resolveEndpoint($provider, 'products_endpoint', '/api/products');

        try {
            $client = self::client($provider);
            $response = $client->get($endpoint, self::requestOptions($provider));
        } catch (GuzzleException $exception) {
            return array(
                'success' => false,
                'error' => $exception->getMessage(),
            );
        }

        return self::decodeResponse($response->getStatusCode(), (string) $response->getBody());
    }

    /**
     * Sağlayıcı bağlantısını kontrol eder.
     *
     * @param array<string,mixed> $provider
     * @return array<string,mixed>
     */
    public static function testConnection(array $provider): array
    {
        $endpoint = self::resolveEndpoint($provider, 'status_endpoint', '/api/user');

        try {
            $client = self::client($provider);
            $response = $client->get($endpoint, self::requestOptions($provider));
        } catch (GuzzleException $exception) {
            return array(
                'success' => false,
                'error' => $exception->getMessage(),
            );
        }

        $decoded = self::decodeResponse($response->getStatusCode(), (string) $response->getBody());

        if (empty($decoded['success'])) {
            $error = 'Sağlayıcı kimlik doğrulaması başarısız.';
            $body = isset($decoded['body']) && is_array($decoded['body']) ? $decoded['body'] : array();

            if (isset($body['message']) && is_string($body['message']) && $body['message'] !== '') {
                $error = $body['message'];
            } elseif (isset($body['error']) && is_string($body['error']) && $body['error'] !== '') {
                $error = $body['error'];
            }

            return array(
                'success' => false,
                'error' => $error,
                'status_code' => $decoded['status_code'] ?? null,
                'body' => $decoded['body'] ?? null,
            );
        }

        $body = isset($decoded['body']) && is_array($decoded['body']) ? $decoded['body'] : array();
        $message = 'Bağlantı başarılı.';
        if (isset($body['message']) && is_string($body['message']) && $body['message'] !== '') {
            $message = $body['message'];
        }

        return array(
            'success' => true,
            'message' => $message,
            'status_code' => $decoded['status_code'] ?? null,
            'body' => $decoded['body'] ?? null,
            'data' => $decoded['data'] ?? array(),
        );
    }

    /**
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function createOrder(array $provider, array $payload): array
    {
        $endpoint = self::resolveEndpoint($provider, 'orders_endpoint', '/api/orders');

        try {
            $client = self::client($provider);
            $options = self::requestOptions($provider, array(
                'headers' => array('Content-Type' => 'application/json'),
                'json' => self::transformOrderPayload($provider, $payload),
            ));
            $response = $client->post($endpoint, $options);
        } catch (GuzzleException $exception) {
            return array(
                'success' => false,
                'error' => $exception->getMessage(),
            );
        }

        return self::decodeResponse($response->getStatusCode(), (string) $response->getBody());
    }

    /**
     * @param array<string,mixed> $provider
     * @param string $endpointKey
     * @param string $fallback
     * @return string
     */
    private static function resolveEndpoint(array $provider, string $endpointKey, string $fallback): string
    {
        if (isset($provider['settings']) && is_array($provider['settings']) && !empty($provider['settings'][$endpointKey])) {
            $value = (string) $provider['settings'][$endpointKey];
            if ($value !== '') {
                return $value;
            }
        }

        $driver = self::driver($provider);
        if (isset(self::DRIVER_DEFAULTS[$driver][$endpointKey])) {
            return (string) self::DRIVER_DEFAULTS[$driver][$endpointKey];
        }

        return $fallback;
    }

    /**
     * @param array<string,mixed> $provider
     * @return Client
     */
    private static function client(array $provider): Client
    {
        $baseUrl = isset($provider['base_url']) ? (string) $provider['base_url'] : '';
        $baseUrl = rtrim($baseUrl, '/') . '/';

        return new Client(array(
            'base_uri' => $baseUrl,
            'timeout' => 20,
        ));
    }

    /**
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private static function requestOptions(array $provider, array $overrides = array()): array
    {
        $options = array(
            'headers' => self::headers($provider),
        );

        $query = self::queryParameters($provider);
        if ($query) {
            $options['query'] = $query;
        }

        if (isset($overrides['headers'])) {
            $options['headers'] = array_merge($options['headers'], $overrides['headers']);
            unset($overrides['headers']);
        }

        if (isset($overrides['query'])) {
            $options['query'] = isset($options['query'])
                ? array_merge($options['query'], $overrides['query'])
                : $overrides['query'];
            unset($overrides['query']);
        }

        foreach ($overrides as $key => $value) {
            $options[$key] = $value;
        }

        return $options;
    }

    /**
     * @param array<string,mixed> $provider
     * @return array<string,string>
     */
    private static function headers(array $provider): array
    {
        $apiKey = self::apiKey($provider);
        $driver = self::driver($provider);
        $scheme = self::DRIVER_DEFAULTS[$driver]['auth_scheme'] ?? 'bearer';

        $headers = array('Accept' => 'application/json');

        if ($apiKey === '') {
            return $headers;
        }

        switch ($scheme) {
            case 'token':
                $headers['Authorization'] = 'Token ' . $apiKey;
                break;
            case 'basic':
                $headers['Authorization'] = 'Basic ' . base64_encode($apiKey);
                break;
            case 'query-only':
                break;
            default:
                $headers['Authorization'] = 'Bearer ' . $apiKey;
                break;
        }

        $headers['X-API-Key'] = $apiKey;
        $headers['X-Auth-Token'] = $apiKey;

        return $headers;
    }

    /**
     * @param array<string,mixed> $provider
     * @return array<string,string>
     */
    private static function queryParameters(array $provider): array
    {
        $apiKey = self::apiKey($provider);
        if ($apiKey === '') {
            return array();
        }

        $driver = self::driver($provider);
        $queryKey = self::DRIVER_DEFAULTS[$driver]['query_key'] ?? 'apikey';
        $params = array($queryKey => $apiKey);

        // Ek uyumluluk için yaygın anahtar adlarını ekleyelim.
        if ($queryKey !== 'apikey') {
            $params['apikey'] = $apiKey;
        }
        if ($queryKey !== 'api_key') {
            $params['api_key'] = $apiKey;
        }
        if ($queryKey !== 'token') {
            $params['token'] = $apiKey;
        }

        return $params;
    }

    /**
     * @param array<string,mixed> $provider
     * @return string
     */
    private static function apiKey(array $provider): string
    {
        return isset($provider['api_key']) ? (string) $provider['api_key'] : '';
    }

    /**
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function transformOrderPayload(array $provider, array $payload): array
    {
        $driver = self::driver($provider);
        $transformed = $payload;

        $productId = '';
        if (isset($transformed['product_id'])) {
            $productId = (string) $transformed['product_id'];
        } elseif (isset($transformed['productId'])) {
            $productId = (string) $transformed['productId'];
        }

        if ($productId !== '') {
            $transformed['product_id'] = $productId;
            $transformed['productId'] = $productId;
            $transformed['item_id'] = $transformed['item_id'] ?? $productId;
            $transformed['stock_code'] = $transformed['stock_code'] ?? $productId;
        }

        if (isset($transformed['note']) && is_string($transformed['note']) && $transformed['note'] !== '') {
            $transformed['description'] = $transformed['description'] ?? $transformed['note'];
            $transformed['customer_note'] = $transformed['customer_note'] ?? $transformed['note'];
        }

        switch ($driver) {
            case 'netgsm':
                $transformed['quantity'] = isset($transformed['quantity']) ? (int) $transformed['quantity'] : 1;
                break;
            case 'turkpin':
                $transformed['count'] = isset($transformed['count']) ? (int) $transformed['count'] : 1;
                break;
            case 'pinabi':
                $transformed['amount'] = isset($transformed['amount']) ? (int) $transformed['amount'] : 1;
                break;
        }

        return $transformed;
    }

    /**
     * @param int $statusCode
     * @param string $body
     * @return array<string,mixed>
     */
    private static function decodeResponse(int $statusCode, string $body): array
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return array(
                'success' => false,
                'error' => 'Sağlayıcı beklenmeyen bir yanıt döndürdü.',
                'status_code' => $statusCode,
                'raw' => $body,
            );
        }

        $success = $statusCode >= 200 && $statusCode < 300;

        if (isset($decoded['success'])) {
            $success = (bool) $decoded['success'];
        } elseif (isset($decoded['status'])) {
            $status = strtolower((string) $decoded['status']);
            $success = in_array($status, array('success', 'ok', 'completed', 'active', '200'), true);
        } elseif (isset($decoded['code']) && is_numeric($decoded['code'])) {
            $success = (int) $decoded['code'] >= 200 && (int) $decoded['code'] < 400;
        } elseif (isset($decoded['error']) && $decoded['error']) {
            $success = false;
        }

        return array(
            'success' => $success,
            'status_code' => $statusCode,
            'body' => $decoded,
            'data' => self::extractData($decoded),
        );
    }

    /**
     * @param array<string,mixed> $decoded
     * @return array<string,mixed>
     */
    private static function extractData(array $decoded): array
    {
        $candidates = array('data', 'result', 'results', 'items', 'products', 'payload', 'response', 'order');

        foreach ($candidates as $candidate) {
            if (isset($decoded[$candidate]) && is_array($decoded[$candidate])) {
                return $decoded[$candidate];
            }
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            foreach ($candidates as $candidate) {
                if (isset($decoded['data'][$candidate]) && is_array($decoded['data'][$candidate])) {
                    return $decoded['data'][$candidate];
                }
            }

            return $decoded['data'];
        }

        return array();
    }

    /**
     * @param array<string,mixed> $provider
     * @return string
     */
    private static function driver(array $provider): string
    {
        if (isset($provider['driver']) && is_string($provider['driver']) && $provider['driver'] !== '') {
            $driver = strtolower(trim($provider['driver']));
            if (isset(self::DRIVER_DEFAULTS[$driver])) {
                return $driver;
            }
        }

        if (isset($provider['settings']['driver']) && is_string($provider['settings']['driver'])) {
            $driver = strtolower(trim($provider['settings']['driver']));
            if (isset(self::DRIVER_DEFAULTS[$driver])) {
                return $driver;
            }
        }

        if (isset($provider['code']) && is_string($provider['code'])) {
            $code = strtolower(trim($provider['code']));
            if (isset(self::DRIVER_DEFAULTS[$code])) {
                return $code;
            }
        }

        return 'generic';
    }
}
