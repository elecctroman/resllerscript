<?php

namespace App\Services;

use PDO;
use PDOException;

class ProviderIntegrationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listProviders(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM external_providers ORDER BY name ASC');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function findProvider(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM external_providers WHERE id = :id');
        $stmt->execute(array('id' => $id));
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);

        return $provider ?: null;
    }

    /**
     * @param string $name
     * @param string $baseUrl
     * @param string $apiKey
     * @param bool $isActive
     * @return int
     */
    public function createProvider(string $name, string $baseUrl, string $apiKey, bool $isActive): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO external_providers (name, base_url, api_key, is_active, created_at) VALUES (:name, :base_url, :api_key, :is_active, NOW())');
        $stmt->execute(array(
            'name' => $name,
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'is_active' => $isActive ? 1 : 0,
        ));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $baseUrl
     * @param string $apiKey
     * @param bool $isActive
     * @return bool
     */
    public function updateProvider(int $id, string $name, string $baseUrl, string $apiKey, bool $isActive): bool
    {
        $stmt = $this->pdo->prepare('UPDATE external_providers SET name = :name, base_url = :base_url, api_key = :api_key, is_active = :is_active, updated_at = NOW() WHERE id = :id');
        return $stmt->execute(array(
            'id' => $id,
            'name' => $name,
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'is_active' => $isActive ? 1 : 0,
        ));
    }

    /**
     * @param int $id
     * @return bool
     */
    public function deleteProvider(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM external_providers WHERE id = :id');
        return $stmt->execute(array('id' => $id));
    }

    /**
     * @param array<string,mixed> $provider
     * @return array<string,mixed>
     */
    public function testConnection(array $provider): array
    {
        $response = $this->sendRequest($provider, 'GET', 'api/user', array('apikey' => $provider['api_key']));

        if ($response['error'] !== null) {
            $this->storeTestResult((int) $provider['id'], 0, $response['error']);

            return array(
                'success' => false,
                'status' => 0,
                'message' => 'API isteği gönderilemedi: ' . $response['error'],
                'data' => array(),
            );
        }

        $statusCode = $response['status'];
        $body = $response['body'];
        $data = json_decode($body, true);

        $message = $statusCode === 200 ? 'API bağlantısı başarılı.' : sprintf('API bağlantısı başarısız (HTTP %d).', $statusCode);

        $this->storeTestResult((int) $provider['id'], $statusCode, $body);

        return array(
            'success' => $statusCode === 200,
            'status' => $statusCode,
            'message' => $message,
            'data' => is_array($data) ? $data : array(),
        );
    }

    /**
     * @param array<string,mixed> $provider
     * @return array<int,array<string,mixed>>|array<string,mixed>
     */
    public function fetchProducts(array $provider)
    {
        $response = $this->sendRequest($provider, 'GET', 'api/products', array('apikey' => $provider['api_key']));

        if ($response['error'] !== null) {
            return array(
                'success' => false,
                'status' => 0,
                'message' => 'Ürün listesi alınamadı: ' . $response['error'],
                'data' => array(),
            );
        }

        $statusCode = $response['status'];
        $body = $response['body'];
        $json = json_decode($body, true);

        if ($statusCode !== 200 || !is_array($json)) {
            return array(
                'success' => false,
                'status' => $statusCode,
                'message' => $statusCode === 0 ? 'API yanıtı alınamadı.' : sprintf('Beklenmeyen yanıt (HTTP %d).', $statusCode),
                'data' => array(),
            );
        }

        $items = array();
        if (isset($json['data']) && is_array($json['data'])) {
            foreach ($json['data'] as $product) {
                if (!is_array($product)) {
                    continue;
                }

                $items[] = array(
                    'id' => isset($product['id']) ? (string) $product['id'] : '',
                    'title' => isset($product['title']) ? (string) $product['title'] : '',
                    'content' => isset($product['content']) ? (string) $product['content'] : '',
                    'amount' => isset($product['amount']) ? (string) $product['amount'] : '0',
                    'stock' => isset($product['stock']) ? (int) $product['stock'] : 0,
                    'available' => isset($product['available']) ? (bool) $product['available'] : false,
                );
            }
        }

        return array(
            'success' => (bool)($json['success'] ?? false),
            'status' => $statusCode,
            'message' => isset($json['message']) ? (string) $json['message'] : 'Ürünler alındı.',
            'data' => $items,
        );
    }

    /**
     * @param int $providerId
     * @param string $remoteProductId
     * @return array<string,mixed>|null
     */
    public function findMapping(int $providerId, string $remoteProductId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM external_provider_products WHERE provider_id = :provider_id AND provider_product_id = :remote_id');
        $stmt->execute(array('provider_id' => $providerId, 'remote_id' => $remoteProductId));

        $mapping = $stmt->fetch(PDO::FETCH_ASSOC);

        return $mapping ?: null;
    }

    /**
     * @param int $providerId
     * @param string $remoteProductId
     * @param int $productId
     * @return void
     */
    public function saveMapping(int $providerId, string $remoteProductId, int $productId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO external_provider_products (provider_id, provider_product_id, product_id, created_at, updated_at) VALUES (:provider_id, :remote_id, :product_id, NOW(), NOW())
            ON DUPLICATE KEY UPDATE product_id = VALUES(product_id), updated_at = NOW()');
        $stmt->execute(array(
            'provider_id' => $providerId,
            'remote_id' => $remoteProductId,
            'product_id' => $productId,
        ));
    }

    /**
     * @param array<string,mixed> $provider
     * @param string $method
     * @param string $path
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $payload
     * @return array{status:int,body:string,error:?string}
     */
    private function sendRequest(array $provider, string $method, string $path, array $query = array(), ?array $payload = null): array
    {
        $baseUrl = isset($provider['base_url']) ? (string) $provider['base_url'] : '';
        $baseUrl = trim($baseUrl);

        if ($baseUrl === '') {
            $baseUrl = 'https://partner.lotuslisans.com.tr';
        }

        $baseUrl = rtrim($baseUrl, '/') . '/';
        $url = $baseUrl . ltrim($path, '/');

        if ($query) {
            $separator = strpos($url, '?') === false ? '?' : '&';
            $url .= $separator . http_build_query($query);
        }

        $headers = $this->buildAuthHeaders($provider);
        $headerLines = array();
        foreach ($headers as $key => $value) {
            $headerLines[] = $key . ': ' . $value;
        }

        if ($payload !== null) {
            $headerLines[] = 'Content-Type: application/json';
        }

        $handle = curl_init($url);
        if ($handle === false) {
            return array('status' => 0, 'body' => '', 'error' => 'İstek başlatılamadı.');
        }

        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_TIMEOUT, 20);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headerLines);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);

        if ($payload !== null) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                curl_close($handle);
                return array('status' => 0, 'body' => '', 'error' => 'İstek verisi kodlanamadı.');
            }
            curl_setopt($handle, CURLOPT_POSTFIELDS, $json);
        }

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = null;

        if ($body === false) {
            $error = curl_error($handle);
        }

        curl_close($handle);

        return array(
            'status' => $status,
            'body' => $body !== false ? $body : '',
            'error' => $error,
        );
    }

    /**
     * @param array<string,mixed> $provider
     * @return array<string,string>
     */
    private function buildAuthHeaders(array $provider): array
    {
        $apiKey = isset($provider['api_key']) ? (string) $provider['api_key'] : '';
        return array(
            'Accept' => 'application/json',
            'X-API-Key' => $apiKey,
            'User-Agent' => 'LotusProviderIntegration/1.0',
        );
    }

    private function storeTestResult(int $providerId, int $status, string $response): void
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE external_providers SET last_tested_at = NOW(), last_test_response = :response, updated_at = NOW() WHERE id = :id');
            $stmt->execute(array(
                'id' => $providerId,
                'response' => $response !== '' ? mb_substr($response, 0, 1000) : null,
            ));
        } catch (PDOException $exception) {
            error_log('[ProviderIntegrationService] Test sonucu kaydedilemedi: ' . $exception->getMessage());
        }
    }
}
