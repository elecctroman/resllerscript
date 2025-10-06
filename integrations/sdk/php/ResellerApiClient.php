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

    public function getOrderStatus(?int $orderId = null, ?string $reference = null): array
    {
        $query = array();
        if ($orderId !== null) {
            $query['order_id'] = $orderId;
        }
        if ($reference !== null) {
            $query['external_reference'] = $reference;
        }
        return $this->request('GET', '/v1/order/status', null, $query);
    }

    public function getBalance(): array
    {
        return $this->request('GET', '/v1/balance');
    }

    public function getUserInfo(): array
    {
        return $this->request('GET', '/v1/user/info');
    }

    public function createApiKey(array $ips = array(), array $domains = array()): array
    {
        $payload = array();
        if ($ips !== array()) {
            $payload['allowed_ips'] = array_values($ips);
        }
        if ($domains !== array()) {
            $payload['allowed_domains'] = array_values($domains);
        }
        return $this->request('POST', '/v1/api-keys/create', $payload);
    }

    public function listApiKeys(): array
    {
        return $this->request('GET', '/v1/api-keys/list');
    }

    public function revokeApiKey(string $key): array
    {
        return $this->request('POST', '/v1/api-keys/revoke', array('key' => $key));
    }

    private function request(string $method, string $path, ?array $payload = null, ?array $query = null): array
    {
        $url = $this->baseUrl . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
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
