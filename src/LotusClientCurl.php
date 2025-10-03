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
        $payload = array('product_id' => $productId);
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
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $url = $this->baseUrl . ltrim($path, '/');

        $attempts = 0;
        $delays = array(500, 1000, 2000);

        do {
            $attempts++;
            $response = $this->performRequest($method, $url, $payload);

            if ($response['status'] >= 500 && $attempts < 3) {
                $delay = $delays[$attempts - 1] ?? end($delays);
                usleep($delay * 1000);
                continue;
            }

            return $response['body'];
        } while (true);
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array{status:int,body:array<string,mixed>}
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

        $headers = array(
            'Accept: application/json',
            'X-API-Key: ' . $this->apiKey,
            'User-Agent: LotusIntegrationCurl/1.0'
        );

        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $this->timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $this->connectTimeoutMs,
            CURLOPT_HTTPHEADER => $headers,
        ));

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->logger->error('cURL isteği başarısız: ' . $error);
            throw new RuntimeException('Sağlayıcı API isteği başarısız: ' . $error);
        }

        curl_close($ch);

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            $this->logger->error('Geçersiz JSON alındı: ' . $result);
            throw new RuntimeException('Sağlayıcıdan beklenmeyen yanıt alındı.');
        }

        return array('status' => $status, 'body' => $decoded);
    }
}
