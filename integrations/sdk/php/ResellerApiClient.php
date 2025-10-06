<?php declare(strict_types=1);

namespace ResellerApi\Sdk;

class ResellerApiClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $apiSecret;
    private ?string $domain;

    public function __construct(string $baseUrl, string $apiKey, string $apiSecret, ?string $domain = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->domain = $domain;
    }

    public function getProducts(): array
    {
        return $this->request('GET', '/v1/products');
    }

    public function createOrder(int $productId, ?string $note = null, ?string $reference = null): array
    {
        $payload = array('product_id' => $productId);
        if ($note !== null) {
            $payload['note'] = $note;
        }
        if ($reference !== null) {
            $payload['external_reference'] = $reference;
        }
        return $this->request('POST', '/v1/order/create', $payload);
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        $headers = array(
            'X-API-KEY: ' . $this->apiKey,
            'X-API-SECRET: ' . $this->apiSecret,
            'Accept: application/json',
        );
        if ($this->domain) {
            $headers[] = 'X-CLIENT-DOMAIN: ' . $this->domain;
        }
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ));
        $response = curl_exec($ch);
        if ($response === false) {
            throw new \RuntimeException('API isteği başarısız: ' . curl_error($ch));
        }
        $decoded = json_decode($response, true);
        curl_close($ch);
        return is_array($decoded) ? $decoded : array();
    }
}
