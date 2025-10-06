<?php declare(strict_types=1);

namespace App\ResellerApi\Services;

use App\Database;
use App\ResellerApi\Exceptions\ApiException;
use App\ResellerApi\Http\Request;
use App\ResellerApi\Http\Response;
use App\ResellerApi\Repositories\ApiKeyRepository;
use App\ResellerApi\Repositories\ApiLogRepository;
use App\ResellerApi\Repositories\ResellerRepository;
use App\ResellerApi\Support\Config;
use PDO;

final class ApiGateway
{
    private ResellerRepository $resellers;
    private ApiKeyRepository $keys;
    private ApiLogRepository $logs;
    private BearerTokenService $tokens;
    private AuthService $auth;
    private ?array $context = null;
    private PDO $pdo;

    public function __construct()
    {
        $this->resellers = new ResellerRepository();
        $this->keys = new ApiKeyRepository();
        $this->logs = new ApiLogRepository();
        $this->tokens = new BearerTokenService();
        $this->auth = new AuthService($this->resellers, $this->tokens);
        $this->pdo = Database::connection();
    }

    public function authService(): AuthService
    {
        return $this->auth;
    }

    public function authenticate(Request $request, bool $requireKey = false): array
    {
        $this->context = null;
        $authorization = (string) $request->header('Authorization', '');
        $reseller = null;
        $apiKeyRow = null;
        $authType = null;

        if ($authorization !== '' && stripos($authorization, 'Bearer ') === 0 && !$requireKey) {
            $token = trim(substr($authorization, 7));
            $resellerId = $this->tokens->validateToken($token);
            if ($resellerId) {
                $reseller = $this->resellers->findById($resellerId);
                if ($reseller && $reseller['status'] === 'active') {
                    $authType = 'bearer';
                } else {
                    $reseller = null;
                }
            }
        }

        if (!$reseller) {
            $key = $request->header('X-API-KEY');
            $secret = $request->header('X-API-SECRET');
            if (!$key || !$secret) {
                throw ApiException::unauthorized('API anahtarı gereklidir.');
            }

            $apiKeyRow = $this->keys->findByKey($key);
            if (!$apiKeyRow || !isset($apiKeyRow['status']) || $apiKeyRow['status'] !== 'active') {
                throw ApiException::unauthorized('API anahtarı doğrulanamadı.');
            }

            $expected = $apiKeyRow['api_secret'];
            $providedHash = $this->hashSecret($secret);
            if (!hash_equals($expected, $providedHash)) {
                throw ApiException::unauthorized('API anahtarı doğrulanamadı.');
            }

            $reseller = $this->resellers->findById((int) $apiKeyRow['reseller_id']);
            if (!$reseller || $reseller['status'] !== 'active') {
                throw ApiException::forbidden('Hesap askıya alınmış.');
            }
            $authType = 'api_key';

            $this->validateNetworkRestrictions($request, $apiKeyRow);
            $this->enforceRateLimit((int) $reseller['id']);
            $this->keys->touchUsage($apiKeyRow['api_key']);
        } elseif ($requireKey) {
            throw ApiException::unauthorized('Bu uç nokta için API anahtarı gereklidir.');
        }

        $this->context = array(
            'reseller' => $reseller,
            'api_key' => $apiKeyRow,
            'auth_type' => $authType,
        );

        return $this->context;
    }

    public function issueToken(string $email, string $password): array
    {
        return $this->auth->login($email, $password);
    }

    public function log(Request $request, Response $response, int $duration): void
    {
        $context = $this->context;
        if (!$context) {
            return;
        }
        $reseller = $context['reseller'] ?? null;
        $apiKey = $context['api_key'] ?? null;
        $this->logs->record(array(
            'api_key' => $apiKey['api_key'] ?? null,
            'reseller_id' => $reseller ? (int) $reseller['id'] : null,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'response_code' => $response->status(),
            'response_time_ms' => $duration,
            'request_body' => $request->body(),
            'response_body' => $response->body(),
        ));
    }

    public function generateApiKey(int $resellerId, ?array $allowedIps, ?array $allowedDomains): array
    {
        $apiKey = strtoupper(bin2hex(random_bytes(16)));
        $secret = bin2hex(random_bytes(32));
        $hash = $this->hashSecret($secret);
        $record = $this->keys->create($resellerId, $apiKey, $hash, $allowedIps, $allowedDomains);
        $record['api_secret_plain'] = $secret;
        $record['allowed_ips'] = $allowedIps;
        $record['allowed_domains'] = $allowedDomains;
        return $record;
    }

    public function listKeys(int $resellerId): array
    {
        return $this->keys->listForReseller($resellerId);
    }

    public function revokeKey(string $apiKey, int $resellerId): void
    {
        $this->keys->revoke($apiKey, $resellerId);
    }

    public function context(): ?array
    {
        return $this->context;
    }

    private function hashSecret(string $secret): string
    {
        return hash_hmac('sha256', $secret, Config::secretKey());
    }

    private function validateNetworkRestrictions(Request $request, array $apiKeyRow): void
    {
        $allowedIps = $apiKeyRow['allowed_ips'] ?? null;
        if (is_string($allowedIps)) {
            $decoded = json_decode($allowedIps, true);
            $allowedIps = is_array($decoded) ? $decoded : null;
        }
        if (is_array($allowedIps) && $allowedIps !== array()) {
            $ip = $request->ip();
            $normalized = array_map('trim', $allowedIps);
            if (!in_array($ip, $normalized, true)) {
                throw ApiException::forbidden('IP adresine erişim izni verilmemiş.');
            }
        }

        $allowedDomains = $apiKeyRow['allowed_domains'] ?? null;
        if (is_string($allowedDomains)) {
            $decoded = json_decode($allowedDomains, true);
            $allowedDomains = is_array($decoded) ? $decoded : null;
        }
        if (is_array($allowedDomains) && $allowedDomains !== array()) {
            $clientDomain = $request->header('X-CLIENT-DOMAIN') ?? $request->header('X-Client-Domain') ?? '';
            $clientDomain = strtolower(trim($clientDomain));
            if ($clientDomain === '') {
                throw ApiException::forbidden('Domain doğrulaması başarısız.');
            }
            $normalizedDomains = array_map(static fn ($domain) => strtolower(trim((string) $domain)), $allowedDomains);
            if (!in_array($clientDomain, $normalizedDomains, true)) {
                throw ApiException::forbidden('Bu domain için izin bulunmuyor.');
            }
        }
    }

    private function enforceRateLimit(int $resellerId): void
    {
        $stmt = $this->pdo->prepare('SELECT rate_limit_per_hour FROM resellers WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $resellerId));
        $limit = (int) $stmt->fetchColumn();
        if ($limit <= 0) {
            $limit = Config::rateLimitFallback();
        }
        $count = $this->logs->countRequests($resellerId, 3600);
        if ($count >= $limit) {
            throw ApiException::rateLimited();
        }
    }
}
