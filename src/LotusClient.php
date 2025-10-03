<?php declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class LotusClient
{
    private Client $http;
    private string $apiKey;
    private Logger $logger;

    public function __construct(string $baseUrl, string $apiKey, int $timeoutMs, int $connectTimeoutMs, Logger $logger)
    {
        $this->apiKey = $apiKey;
        $this->logger = $logger;
        $this->http = new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'timeout' => $timeoutMs / 1000,
            'connect_timeout' => $connectTimeoutMs / 1000,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'LotusIntegration/1.0 (+local)',
            ],
        ]);
    }

    /**
     * Düşük seviyeli istek + retry/backoff (yalnızca 5xx ve ağ hataları)
     */
    private function request(string $method, string $path, ?array $json = null): array
    {
        $attempts = 0;
        $backoffs = [500, 1000, 2000];

        while (true) {
            $attempts++;
            try {
                $opts = [
                    'headers' => ['X-API-Key' => $this->apiKey],
                ];
                if ($json !== null) {
                    $opts['json'] = $json;
                }

                $res = $this->http->request($method, ltrim($path, '/'), $opts);
                $body = (string) $res->getBody();
                $data = json_decode($body, true);
                if (!is_array($data)) {
                    throw new \RuntimeException('Geçersiz JSON: ' . $body);
                }
                return $data;
            } catch (GuzzleException $e) {
                $code = $e->getCode();
                $this->logger->error('HTTP hata (attempt=' . $attempts . ', code=' . $code . '): ' . $e->getMessage());

                $isClientError = $code >= 400 && $code < 500 && $code !== 429;
                if ($attempts >= 3 || $isClientError) {
                    throw $e;
                }

                $delay = $backoffs[min($attempts - 1, count($backoffs) - 1)] * 1000;
                usleep($delay);
            }
        }
    }

    public function getUser(): array
    {
        return $this->request('GET', 'api/user');
    }

    public function getProducts(): array
    {
        return $this->request('GET', 'api/products');
    }

    public function createOrder(int $productId, ?string $note = null): array
    {
        $payload = ['product_id' => $productId];
        if ($note !== null && $note !== '') {
            $payload['note'] = $note;
        }

        return $this->request('POST', 'api/orders', $payload);
    }

    public function listOrders(): array
    {
        return $this->request('GET', 'api/orders');
    }

    public function getOrder(int $orderId): array
    {
        return $this->request('GET', 'api/orders/' . $orderId);
    }
}
