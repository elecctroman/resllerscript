<?php declare(strict_types=1);

namespace Reseller\Sdk;

use RuntimeException;

class ResellerApiClient
{
    private string $baseUrl;
    private string $apiKey;
    private ?string $bearerToken;
    private ?string $hmacSecret;

    public function __construct(string $baseUrl, string $apiKey, ?string $bearerToken = null, ?string $hmacSecret = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->bearerToken = $bearerToken;
        $this->hmacSecret = $hmacSecret;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getProducts(): array
    {
        $response = $this->request('GET', '/products');
        return $response['data']['products'] ?? array();
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createOrder(array $payload): array
    {
        $response = $this->request('POST', '/order/create', $payload);
        return $response['data'] ?? array();
    }

    /**
     * @param array<string,string|int> $query
     * @return array<string,mixed>
     */
    public function getOrderStatus(array $query): array
    {
        $response = $this->request('GET', '/order/status', null, $query);
        return $response['data']['order'] ?? array();
    }

    /**
     * @return array<string,mixed>
     */
    public function getBalance(): array
    {
        $response = $this->request('GET', '/balance');
        return $response['data'] ?? array();
    }

    /**
     * @return array<string,mixed>
     */
    public function getUserInfo(): array
    {
        $response = $this->request('GET', '/user/info');
        return $response['data'] ?? array();
    }

    /**
     * @param array<string,mixed>|null $body
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $body = null, array $query = array()): array
    {
        $url = $this->baseUrl . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $headers = array(
            'Accept: application/json',
            'X-API-Key: ' . $this->apiKey,
        );

        $payload = '';
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                throw new RuntimeException('Gönderilecek JSON içeriği hazırlanamadı.');
            }
            $headers[] = 'Content-Type: application/json';
        }

        if ($this->bearerToken !== null && $this->bearerToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->bearerToken;
        }

        if ($this->hmacSecret) {
            $timestamp = (string) time();
            $signaturePayload = $timestamp . "\n" . strtoupper($method) . "\n" . $path . "\n" . $payload;
            $signature = hash_hmac('sha256', $signaturePayload, $this->hmacSecret);
            $headers[] = 'X-Request-Timestamp: ' . $timestamp;
            $headers[] = 'X-Signature: sha256=' . $signature;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if ($payload !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $responseBody = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('API isteği gönderilemedi: ' . $error);
        }

        curl_close($ch);

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('API yanıtı çözümlenemedi.');
        }

        if ($statusCode >= 400) {
            $message = isset($decoded['error']) ? (string) $decoded['error'] : 'API hatası oluştu.';
            throw new RuntimeException(sprintf('API hatası (%d): %s', $statusCode, $message));
        }

        return $decoded;
    }
}
