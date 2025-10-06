<?php declare(strict_types=1);

namespace App\ResellerApi\Repositories;

use App\Database;
use PDO;

final class ApiKeyRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function create(int $resellerId, string $apiKey, string $secretHash, ?array $allowedIps, ?array $allowedDomains): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO api_keys (reseller_id, api_key, api_secret, allowed_ips, allowed_domains, created_at, updated_at) VALUES (:reseller_id, :api_key, :api_secret, :allowed_ips, :allowed_domains, NOW(), NOW())');
        $stmt->execute(array(
            'reseller_id' => $resellerId,
            'api_key' => $apiKey,
            'api_secret' => $secretHash,
            'allowed_ips' => $allowedIps ? json_encode(array_values($allowedIps)) : null,
            'allowed_domains' => $allowedDomains ? json_encode(array_values($allowedDomains)) : null,
        ));

        return $this->findById((int) $this->pdo->lastInsertId());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM api_keys WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $id));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $result['allowed_ips'] = $result['allowed_ips'] ? json_decode((string) $result['allowed_ips'], true) : null;
            $result['allowed_domains'] = $result['allowed_domains'] ? json_decode((string) $result['allowed_domains'], true) : null;
        }
        return $result ?: null;
    }

    public function findByKey(string $apiKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM api_keys WHERE api_key = :key LIMIT 1');
        $stmt->execute(array('key' => $apiKey));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $result['allowed_ips'] = $result['allowed_ips'] ? json_decode((string) $result['allowed_ips'], true) : null;
            $result['allowed_domains'] = $result['allowed_domains'] ? json_decode((string) $result['allowed_domains'], true) : null;
        }
        return $result ?: null;
    }

    public function listForReseller(int $resellerId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM api_keys WHERE reseller_id = :id ORDER BY created_at DESC');
        $stmt->execute(array('id' => $resellerId));
        $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($keys as &$key) {
            $key['allowed_ips'] = $key['allowed_ips'] ? json_decode((string) $key['allowed_ips'], true) : null;
            $key['allowed_domains'] = $key['allowed_domains'] ? json_decode((string) $key['allowed_domains'], true) : null;
        }
        return $keys;
    }

    public function revoke(string $apiKey, int $resellerId): void
    {
        $stmt = $this->pdo->prepare('UPDATE api_keys SET status = "revoked", updated_at = NOW() WHERE api_key = :key AND reseller_id = :reseller_id');
        $stmt->execute(array('key' => $apiKey, 'reseller_id' => $resellerId));
    }

    public function touchUsage(string $apiKey): void
    {
        $stmt = $this->pdo->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE api_key = :key');
        $stmt->execute(array('key' => $apiKey));
    }
}
