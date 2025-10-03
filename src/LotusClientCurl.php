<?php declare(strict_types=1);

namespace App;

use RuntimeException;

final class LotusClientCurl
{
    private string $baseUrl;
    private string $apiKey;
    private Logger $logger;
    private int $timeoutMs;
    private int $connectTimeoutMs;

    public function __construct(string $baseUrl, string $apiKey, int $timeoutMs, int $connectTimeoutMs, Logger $logger)
    {
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->apiKey = $apiKey;
        $this->timeoutMs = max(1000, $timeoutMs);
        $this->connectTimeoutMs = max(1000, $connectTimeoutMs);
        $this->logger = $logger;
    }

    /** @return array<string,mixed> */
    public function getUser(): array
    {
        return $this->request('GET', 'api/user');
    }

    /** @return array<string,mixed> */
    public function getProducts(): array
    {
        return $this->request('GET', 'api/products');
    }

    /** @return array<string,mixed> */
    public function createOrder(int $productId, ?string $note = null): array
    {
        $payload = ['product_id' => $productId];
        if ($note !== null && $note !== '') {
            $payload['note'] = $note;
        }

        return $this->request('POST', 'api/orders', $payload);
    }

    /** @return array<string,mixed> */
    public function listOrders(): array
    {
        return $this->request('GET', 'api/orders');
    }

    /** @return array<string,mixed> */
    public function getOrder(int $orderId): array
    {
        return $this->request('GET', 'api/orders/' . $orderId);
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $payload = null, bool $useQueryParam = false): array
    {
        $attempts = 0;
        $delays = [500, 1000, 2000];
        $currentUrl = $this->buildUrl($path, $useQueryParam);
        $visited = [];

        while (true) {
            $attempts++;
            $response = $this->performRequest($method, $currentUrl, $payload);

            if ($response['status'] >= 300 && $response['status'] < 400) {
                $location = $response['location'];
                if ($location !== null) {
                    $next = $this->resolveRedirectTarget($currentUrl, $location);
                    $key = $this->loopKey($next);
                    if (isset($visited[$key])) {
                        $this->logger->error('Lotus yönlendirme döngüsü algılandı (cURL): ' . $next);
                        throw new RuntimeException('Sağlayıcı API yönlendirme döngüsü algılandı.');
                    }

                    $visited[$key] = true;
                    $currentUrl = $next;
                    $attempts--; // yönlendirme yeniden deneme sayılmasın
                    continue;
                }

                throw new RuntimeException('Sağlayıcı API yönlendirme yanıtı döndürdü. API URL ayarınızı "https://alanadiniz.com" formatında girin ve güvenlik kısıtlamalarını kontrol edin.');
            }

            if (!is_array($response['body'])) {
                if (!$useQueryParam && $this->shouldFallbackToQuery($response['status'], $response['raw'], $response['content_type'])) {
                    $this->logger->info('Header ile doğrulama başarısız (cURL), querystring anahtar denenecek.');
                    return $this->request($method, $path, $payload, true);
                }

                throw new RuntimeException('Sağlayıcıdan beklenmeyen yanıt alındı.');
            }

            if ($response['status'] >= 500 && $attempts < 3) {
                $delay = $delays[$attempts - 1] ?? end($delays);
                usleep($delay * 1000);
                continue;
            }

            return $response['body'];
        }
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array{status:int,body:mixed,raw:string,content_type:string|null,location: ?string}
     */
    private function performRequest(string $method, string $url, ?array $payload): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL desteği bulunamadı.');
        }

        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('cURL oturumu başlatılamadı.');
        }

        $headers = [
            'Accept: application/json',
            'X-API-Key: ' . $this->apiKey,
            'User-Agent: LotusIntegrationCurl/1.0',
        ];

        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $this->timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $this->connectTimeoutMs,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ];

        curl_setopt_array($ch, $options);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->logger->error('cURL isteği başarısız: ' . $error);
            throw new RuntimeException('Sağlayıcı API isteği başarısız: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        [$headersBlock, $body] = $this->splitResponse($result);
        $location = $this->extractHeader($headersBlock, 'Location');

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $snippet = function_exists('mb_substr') ? mb_substr($body, 0, 200) : substr($body, 0, 200);
            $preview = trim((string) $snippet);
            $this->logger->error(sprintf(
                'Geçersiz JSON alındı (HTTP %d, content-type=%s, location=%s): %s',
                $status,
                $contentType !== false ? (string) $contentType : 'bilinmiyor',
                $location ?? 'yok',
                $preview
            ));
        }

        return [
            'status' => $status,
            'body' => $decoded,
            'raw' => $body,
            'content_type' => $contentType !== false ? (string) $contentType : null,
            'location' => $location,
        ];
    }

    private function splitResponse(string $response): array
    {
        $parts = preg_split("/\r?\n\r?\n/", $response, 2);
        if ($parts === false || count($parts) < 2) {
            return ['', $response];
        }

        return [$parts[0], $parts[1]];
    }

    private function extractHeader(string $headers, string $name): ?string
    {
        if (preg_match('/' . preg_quote($name, '/') . '\s*:\s*(.+)/i', $headers, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function buildUrl(string $path, bool $useQueryParam): string
    {
        $url = (strpos($path, '://') !== false) ? $path : $this->baseUrl . ltrim($path, '/');
        if ($useQueryParam) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . 'apikey=' . rawurlencode($this->apiKey);
        }

        return $url;
    }

    private function resolveRedirectTarget(string $current, string $location): string
    {
        $trimmed = trim($location);
        if ($trimmed === '') {
            return $current;
        }

        if (stripos($trimmed, 'http://') === 0) {
            $trimmed = 'https://' . substr($trimmed, 7);
        }

        if (strpos($trimmed, '://') !== false) {
            return $trimmed;
        }

        if ($trimmed[0] === '/') {
            return $this->baseUrl . ltrim($trimmed, '/');
        }

        $base = strpos($current, '://') !== false ? $current : $this->baseUrl . ltrim($current, '/');
        $parsed = parse_url($base);
        if ($parsed === false) {
            return $this->baseUrl . ltrim($trimmed, '/');
        }

        $path = isset($parsed['path']) ? rtrim($parsed['path'], '/') . '/' : '/';
        return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . (isset($parsed['port']) ? ':' . $parsed['port'] : '') . $path . ltrim($trimmed, '/');
    }

    private function loopKey(string $uri): string
    {
        $parsed = parse_url($uri);
        if ($parsed === false) {
            return strtolower($uri);
        }

        $host = strtolower($parsed['host'] ?? '');
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        return $host . $path . $query;
    }

    private function shouldFallbackToQuery(int $status, string $body, ?string $contentType): bool
    {
        if ($status === 401 || $status === 403) {
            return true;
        }

        if ($contentType !== null && stripos($contentType, 'text/html') !== false) {
            return true;
        }

        return stripos($body, 'Moved Permanently') !== false || stripos($body, '<html') !== false;
    }
}
