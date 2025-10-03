<?php declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

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
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'track_redirects' => true,
            ],
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'LotusIntegration/1.0 (+local)',
            ],
        ]);
    }

    /**
     * Düşük seviyeli istek + retry/backoff (yalnızca 5xx ve ağ hataları)
     */
    private function request(string $method, string $path, ?array $json = null, int $redirectDepth = 0): array
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

                $uri = strpos($path, '://') !== false ? $path : ltrim($path, '/');
                $res = $this->http->request($method, $uri, $opts);
                $status = $res->getStatusCode();
                if ($status >= 300 && $status < 400) {
                    $location = $res->getHeaderLine('Location');
                    if ($location !== '' && $redirectDepth < 5) {
                        $this->logger->info('Lotus yönlendirmesi algılandı, yeni hedef: ' . $location);
                        $nextPath = strpos($location, '://') !== false ? $location : ltrim($location, '/');
                        return $this->request($method, $nextPath, $json, $redirectDepth + 1);
                    }

                    $hint = 'Sağlayıcı API yönlendirme yanıtı döndürdü. API URL ayarınızı "https://alanadiniz.com" formatında girin ve güvenlik kısıtlamalarını kontrol edin.';
                    if ($location !== '') {
                        $hint .= ' (Yönlendirme: ' . $location . ')';
                    }
                    throw new RuntimeException($hint);
                }

                $body = (string) $res->getBody();
                $data = json_decode($body, true);
                if (!is_array($data)) {
                    $contentType = $res->getHeaderLine('Content-Type');
                    $history = $res->getHeader('X-Guzzle-Redirect-History');
                    $redirectInfo = !empty($history) ? implode(' -> ', $history) : 'yok';
                    $snippet = function_exists('mb_substr') ? mb_substr($body, 0, 200) : substr($body, 0, 200);
                    $preview = trim((string) $snippet);

                    if ($redirectDepth < 5 && stripos($body, '301 Moved Permanently') !== false) {
                        $nextPath = rtrim($uri, '/') . '/';
                        if ($nextPath !== $uri) {
                            $this->logger->info('Lotus 301 gövdesi algılandı, trailing slash ile yeniden denenecek: ' . $nextPath);
                            return $this->request($method, $nextPath, $json, $redirectDepth + 1);
                        }
                    }

                    $this->logger->error(sprintf(
                        'Geçersiz JSON alındı (HTTP %d, content-type=%s, redirect=%s): %s',
                        $status,
                        $contentType !== '' ? $contentType : 'bilinmiyor',
                        $redirectInfo,
                        $preview
                    ));

                    throw new RuntimeException('Sağlayıcıdan beklenmeyen yanıt alındı.');
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
