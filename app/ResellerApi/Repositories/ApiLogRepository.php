<?php

declare(strict_types=1);

namespace App\ResellerApi\Repositories;

use App\Database;
use PDO;

final class ApiLogRepository
{
    /**
     * @param string $apiKey
     * @param string $endpoint
     * @param string $method
     * @param string $ip
     * @param int    $status
     * @param int    $responseTime
     * @param string|null $requestBody
     * @param string|null $responseBody
     */
    public function log(string $apiKey, string $endpoint, string $method, string $ip, int $status, int $responseTime, ?string $requestBody, ?string $responseBody): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO api_logs (api_key, endpoint, method, ip, response_code, response_time_ms, request_body, response_body, created_at) VALUES (:api_key, :endpoint, :method, :ip, :response_code, :response_time_ms, :request_body, :response_body, NOW())');
        $stmt->execute(array(
            'api_key' => $apiKey,
            'endpoint' => $endpoint,
            'method' => strtoupper($method),
            'ip' => $ip,
            'response_code' => $status,
            'response_time_ms' => $responseTime,
            'request_body' => $requestBody,
            'response_body' => $responseBody,
        ));
    }

    public function countRequestsLastHour(string $apiKey): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM api_logs WHERE api_key = :api_key AND created_at >= (NOW() - INTERVAL 1 HOUR)');
        $stmt->execute(array('api_key' => $apiKey));
        $count = $stmt->fetchColumn();

        return $count !== false ? (int) $count : 0;
    }
}
