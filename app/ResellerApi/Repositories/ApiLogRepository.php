<?php declare(strict_types=1);

namespace App\ResellerApi\Repositories;

use App\Database;
use PDO;

final class ApiLogRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function record(array $data): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO api_logs (api_key, reseller_id, endpoint, method, ip, response_code, response_time_ms, request_body, response_body, created_at) VALUES (:api_key, :reseller_id, :endpoint, :method, :ip, :response_code, :response_time_ms, :request_body, :response_body, NOW())');
        $stmt->execute(array(
            'api_key' => $data['api_key'] ?? null,
            'reseller_id' => $data['reseller_id'] ?? null,
            'endpoint' => $data['endpoint'] ?? null,
            'method' => $data['method'] ?? null,
            'ip' => $data['ip'] ?? null,
            'response_code' => $data['response_code'] ?? null,
            'response_time_ms' => $data['response_time_ms'] ?? null,
            'request_body' => $data['request_body'] ?? null,
            'response_body' => $data['response_body'] ?? null,
        ));
    }

    public function countRequests(int $resellerId, int $seconds): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM api_logs WHERE reseller_id = :reseller_id AND created_at >= (NOW() - INTERVAL :seconds SECOND)');
        $stmt->bindValue(':reseller_id', $resellerId, PDO::PARAM_INT);
        $stmt->bindValue(':seconds', $seconds, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
