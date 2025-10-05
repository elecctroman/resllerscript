<?php declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class ProviderApiClient
{
    /**
     * @param array<string,mixed> $provider
     * @return array<string,mixed>
     */
    public static function fetchProducts(array $provider): array
    {
        $endpoint = self::setting($provider, 'products_endpoint', '/api/products');

        try {
            $client = self::client($provider);
            $response = $client->get($endpoint, array(
                'headers' => self::headers($provider),
                'query' => array('apikey' => self::apiKey($provider)),
            ));
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
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function createOrder(array $provider, array $payload): array
    {
        $endpoint = self::setting($provider, 'orders_endpoint', '/api/orders');

        try {
            $client = self::client($provider);
            $response = $client->post($endpoint, array(
                'headers' => array_merge(self::headers($provider), array('Content-Type' => 'application/json')),
                'query' => array('apikey' => self::apiKey($provider)),
                'json' => $payload,
            ));
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
     * @param string $default
     * @return string
     */
    private static function setting(array $provider, string $endpointKey, string $default): string
    {
        if (isset($provider['settings']) && is_array($provider['settings']) && isset($provider['settings'][$endpointKey])) {
            $value = (string) $provider['settings'][$endpointKey];
            if ($value !== '') {
                return $value;
            }
        }

        return $default;
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
     * @return array<string,string>
     */
    private static function headers(array $provider): array
    {
        $apiKey = self::apiKey($provider);

        return array(
            'Accept' => 'application/json',
            'X-API-Key' => $apiKey,
        );
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

        $success = isset($decoded['success']) ? (bool) $decoded['success'] : ($statusCode >= 200 && $statusCode < 300);

        return array(
            'success' => $success,
            'status_code' => $statusCode,
            'body' => $decoded,
            'data' => isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : array(),
        );
    }
}
