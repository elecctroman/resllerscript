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
            'allow_redirects' => false,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'LotusIntegration/1.0 (+local)',
            ],
        ]);
    }

    /**
     * Düşük seviyeli istek + retry/backoff (yalnızca 5xx ve ağ hataları)
     */
    private function request(string $method, string $path, ?array $json = null, bool $useQueryParam = false): array
    {
        $attempts = 0;
        $backoffs = [500, 1000, 2000];
        $currentPath = $path;
        $visited = [];

        while (true) {
            $attempts++;
            try {
                $opts = [
                    'headers' => ['X-API-Key' => $this->apiKey],
                ];
                if ($json !== null) {
                    $opts['json'] = $json;
                }

                $uri = $this->buildUri($currentPath, $useQueryParam);
                $res = $this->http->request($method, $uri, $opts);
                $status = $res->getStatusCode();

                if ($status >= 300 && $status < 400) {
                    $location = $res->getHeaderLine('Location');
                    if ($location !== '') {
                        $normalised = $this->normaliseLocation($location);
                        if (isset($visited[$normalised])) {
                            $this->logger->error('Lotus yönlendirme döngüsü algılandı: ' . $normalised);
                        } else {
                            $visited[$normalised] = true;
                            $this->logger->info('Lotus yönlendirmesi algılandı, yeni hedef: ' . $normalised);
                            $currentPath = $normalised;
                            $attempts--; // yönlendirme yeniden deneme sayılmasın
                            continue;
                        }
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
                    $snippet = function_exists('mb_substr') ? mb_substr($body, 0, 200) : substr($body, 0, 200);
                    $preview = trim((string) $snippet);

                    if (!$useQueryParam && $this->shouldFallbackToQuery($status, $body, $contentType)) {
                        $this->logger->info('Header ile doğrulama başarısız, querystring anahtar denenecek.');
                        return $this->request($method, $path, $json, true);
                    }

                    $this->logger->error(sprintf(
                        'Geçersiz JSON alındı (HTTP %d, content-type=%s): %s',
                        $status,
                        $contentType !== '' ? $contentType : 'bilinmiyor',
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
                    if (!$useQueryParam && $this->isRedirectLike($code, $e->getMessage())) {
                        $this->logger->info('Redirect hatasında query string fallback denenecek.');
                        return $this->request($method, $path, $json, true);
                    }
                    throw $e;
                }

                $delay = $backoffs[min($attempts - 1, count($backoffs) - 1)] * 1000;
                usleep($delay);
            }
        }
    }

    private function buildUri(string $path, bool $useQueryParam): string
    {
        $uri = strpos($path, '://') !== false ? $path : ltrim($path, '/');
        if ($useQueryParam) {
            $separator = str_contains($uri, '?') ? '&' : '?';
            $uri .= $separator . 'apikey=' . rawurlencode($this->apiKey);
        }

        if (!str_contains($uri, '://') && str_contains($uri, '//')) {
            $uri = ltrim($uri, '/');
        }

        return $uri;
    }

    private function normaliseLocation(string $location): string
    {
        $trimmed = trim($location);
        if ($trimmed === '') {
            return $trimmed;
        }

        if (strpos($trimmed, '://') !== false) {
            return $trimmed;
        }

        return ltrim($trimmed, '/');
    }

    private function shouldFallbackToQuery(int $status, string $body, string $contentType): bool
    {
        if ($status === 401 || $status === 403) {
            return true;
        }

        if (stripos($contentType, 'text/html') !== false) {
            return true;
        }

        return stripos($body, 'Moved Permanently') !== false || stripos($body, '<html') !== false;
    }

    private function isRedirectLike(int $code, string $message): bool
    {
        if ($code >= 300 && $code < 400) {
            return true;
        }

        return stripos($message, 'redirect') !== false;
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
