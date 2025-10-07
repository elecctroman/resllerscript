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


            return array(
                'success' => false,
                'status' => 0,

    }

    /**
     * @param array<string,mixed> $provider
     * @return array<int,array<string,mixed>>|array<string,mixed>
     */
    public function fetchProducts(array $provider)
    {

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


    {
        $baseUrl = isset($provider['base_url']) ? (string) $provider['base_url'] : '';
        $baseUrl = trim($baseUrl);

        if ($baseUrl === '') {
            $baseUrl = 'https://partner.lotuslisans.com.tr';
        }

        $baseUrl = rtrim($baseUrl, '/') . '/';

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
