<?php

namespace App\Services;



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


        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function findProvider(int $id): ?array
    {


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

    }

    /**
     * @param int $id
     * @return bool
     */
    public function deleteProvider(int $id): bool
    {

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

            ));
        } catch (PDOException $exception) {
            error_log('[ProviderIntegrationService] Test sonucu kaydedilemedi: ' . $exception->getMessage());
        }
    }


}
