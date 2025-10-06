<?php

declare(strict_types=1);

namespace App\ResellerApi\Repositories;

use App\Database;
use App\ResellerApi\Support\Config;
use PDO;

final class ApiKeyRepository
{
    /**
     * @param int $resellerId
     * @param array<int,string> $allowedDomains
     * @param array<int,string> $allowedIps
     * @return array{api_key:string,api_secret:string}
     */
    public function create(int $resellerId, array $allowedDomains = array(), array $allowedIps = array()): array
    {
        $pdo = Database::connection();

        $apiKey = $this->generateKey();
        $apiSecret = $this->generateSecret();
        $secretHash = $this->hashSecret($apiSecret);

        $stmt = $pdo->prepare('INSERT INTO api_keys (reseller_id, api_key, api_secret, allowed_ips, allowed_domains, status, created_at) VALUES (:reseller_id, :api_key, :api_secret, :allowed_ips, :allowed_domains, :status, NOW())');
        $stmt->execute(array(
            'reseller_id' => $resellerId,
            'api_key' => $apiKey,
            'api_secret' => $secretHash,
            'allowed_ips' => $this->encodeList($allowedIps),
            'allowed_domains' => $this->encodeList($allowedDomains),
            'status' => 'active',
        ));

        return array('api_key' => $apiKey, 'api_secret' => $apiSecret);
    }

    /**
     * @param int $resellerId
     * @return array<int,array<string,mixed>>
     */
    public function listForReseller(int $resellerId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM api_keys WHERE reseller_id = :reseller_id ORDER BY created_at DESC');
        $stmt->execute(array('reseller_id' => $resellerId));
        $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($keys as &$key) {
            $key['allowed_ips'] = $this->decodeList(isset($key['allowed_ips']) ? $key['allowed_ips'] : null);
            $key['allowed_domains'] = $this->decodeList(isset($key['allowed_domains']) ? $key['allowed_domains'] : null);
        }

        return $keys;
    }

    public function revoke(int $resellerId, string $apiKey): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE api_keys SET status = :status, updated_at = NOW() WHERE reseller_id = :reseller_id AND api_key = :api_key');
        $stmt->execute(array(
            'status' => 'revoked',
            'reseller_id' => $resellerId,
            'api_key' => $apiKey,
        ));

        return $stmt->rowCount() > 0;
    }

    public function findActiveByKey(string $apiKey): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM api_keys WHERE api_key = :api_key LIMIT 1');
        $stmt->execute(array('api_key' => $apiKey));
        $key = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$key || $key['status'] !== 'active') {
            return null;
        }

        $key['allowed_ips'] = $this->decodeList(isset($key['allowed_ips']) ? $key['allowed_ips'] : null);
        $key['allowed_domains'] = $this->decodeList(isset($key['allowed_domains']) ? $key['allowed_domains'] : null);

        return $key;
    }

    public function markUsed(string $apiKey): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE api_keys SET last_used_at = NOW(), updated_at = NOW() WHERE api_key = :api_key');
        $stmt->execute(array('api_key' => $apiKey));
    }

    public function hashSecret(string $secret): string
    {
        return hash_hmac('sha512', $secret, Config::masterSecret());
    }

    /**
     * @param string|null $value
     * @return array<int,string>
     */
    public function decodeList(?string $value): array
    {
        if ($value === null || $value === '') {
            return array();
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(static function ($item) {
                return is_string($item) ? trim($item) : '';
            }, $decoded), static function ($item) {
                return $item !== '';
            }));
        }

        return array();
    }

    /**
     * @param array<int,string> $items
     */
    private function encodeList(array $items): string
    {
        $normalised = array();
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $normalised[] = $item;
            }
        }

        return json_encode(array_values(array_unique($normalised)));
    }

    private function generateKey(): string
    {
        return strtoupper(bin2hex(random_bytes(16)));
    }

    private function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }
}
