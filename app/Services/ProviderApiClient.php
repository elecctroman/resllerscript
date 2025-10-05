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

            $value = (string) $provider['settings'][$endpointKey];
            if ($value !== '') {
                return $value;
            }
        }


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



        return array(
            'success' => $success,
            'status_code' => $statusCode,
            'body' => $decoded,

}
