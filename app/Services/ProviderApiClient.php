<?php declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ProviderApiClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            throw new RuntimeException('Sağlayıcı adresi tanımlı değil.');
        }

        $baseUrl = rtrim($baseUrl, '/');
        if (!preg_match('#/api$#i', $baseUrl)) {
            $baseUrl .= '/api';
        }

        $this->baseUrl = $baseUrl;
        $this->apiKey = trim($apiKey);
    }

    /**
     * @return array<string,mixed>
     */
    public function fetchUser(): array
    {
        return $this->request('GET', 'user');
    }

    /**
     * @return array<string,mixed>
     */
    public function fetchProducts(): array
    {
        return $this->request('GET', 'products');
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createOrder(array $payload): array
    {
        return $this->request('POST', 'orders', $payload);
    }

    /**
     * @param string $method
     * @param string $path
     * @param array<string,mixed>|null $payload
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $method = strtoupper($method);
        $url = $this->buildUrl($path);
        $headers = array(
            'Accept: application/json',
            'X-API-Key: ' . $this->apiKey,
            'User-Agent: ResellerPanel-ProviderClient/1.0'
        );

        $body = null;
        if ($payload !== null) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                throw new RuntimeException('Sağlayıcı isteği hazırlanırken JSON hatası oluştu.');
            }
            $headers[] = 'Content-Type: application/json';
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL desteği etkin değil.');
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        }

        $responseBody = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false) {
            return array(
                'success' => false,
                'message' => 'Sağlayıcı isteği başarısız oldu: ' . $error,
                'status_code' => $statusCode,
            );
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            return array(
                'success' => false,
                'message' => 'Sağlayıcı yanıtı çözümlenemedi.',
                'status_code' => $statusCode,
                'raw' => $responseBody,
            );
        }

        $decoded['status_code'] = $statusCode;
        return $decoded;
    }

    private function buildUrl(string $path): string
    {
        $path = ltrim($path, '/');
        $separator = strpos($this->baseUrl, '?') === false ? '?' : '&';
        $query = 'apikey=' . urlencode($this->apiKey);

        return $this->baseUrl . '/' . $path . $separator . $query;
    }
}
