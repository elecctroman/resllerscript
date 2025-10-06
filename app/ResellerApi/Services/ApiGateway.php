<?php

declare(strict_types=1);

namespace App\ResellerApi\Services;

use App\ResellerApi\Exceptions\ApiException;
use App\ResellerApi\Repositories\ApiKeyRepository;
use App\ResellerApi\Repositories\ApiLogRepository;
use App\ResellerApi\Repositories\ResellerRepository;
use App\ResellerApi\Support\Config;

final class ApiGateway
{
    private ApiKeyRepository $keys;
    private ApiLogRepository $logs;
    private ResellerRepository $resellers;

    public function __construct()
    {
        $this->keys = new ApiKeyRepository();
        $this->logs = new ApiLogRepository();
        $this->resellers = new ResellerRepository();
    }

    /**
     * @return array<string,mixed>
     */
    public function authenticate(string $method, string $endpoint, string $requestBody): array
    {
        $apiKey = $this->getHeader('X-API-KEY');
        $apiSecret = $this->getHeader('X-API-SECRET');
        $clientDomain = $this->getHeader('X-CLIENT-DOMAIN');
        $ip = $this->clientIp();

        if ($apiKey === '' || $apiSecret === '') {
            throw new ApiException('INVALID_KEY', 'API anahtarı veya gizli anahtar eksik.', 401, array('ip' => $ip));
        }

        $record = $this->keys->findActiveByKey($apiKey);
        if (!$record) {
            throw new ApiException('INVALID_KEY', 'Sağlanan API anahtarı geçersiz veya devre dışı.', 401, array('api_key' => $apiKey, 'ip' => $ip));
        }

        $expectedHash = $record['api_secret'];
        if (!hash_equals($expectedHash, $this->keys->hashSecret($apiSecret))) {
            throw new ApiException('INVALID_KEY', 'API gizli anahtarı doğrulanamadı.', 401, array('api_key' => $apiKey, 'ip' => $ip));
        }

        $allowedDomains = $record['allowed_domains'];
        if ($allowedDomains && $clientDomain !== '') {
            $this->assertDomainAllowed($clientDomain, $allowedDomains, $apiKey, $ip);
        } elseif ($allowedDomains && $clientDomain === '') {
            throw new ApiException('DOMAIN_NOT_ALLOWED', 'İzin verilen etki alanı başlığı eksik.', 403, array('api_key' => $apiKey, 'ip' => $ip));
        }

        $allowedIps = $record['allowed_ips'];
        if ($allowedIps && !in_array($ip, $allowedIps, true)) {
            throw new ApiException('IP_NOT_ALLOWED', 'IP adresiniz bu API anahtarı için yetkilendirilmemiş.', 403, array('api_key' => $apiKey, 'ip' => $ip));
        }

        $limit = Config::rateLimitPerHour();
        if ($limit > 0) {
            $requests = $this->logs->countRequestsLastHour($apiKey);
            if ($requests >= $limit) {
                throw new ApiException('RATE_LIMIT_EXCEEDED', 'Saatlik istek limiti aşıldı.', 429, array('retry_after' => 3600, 'api_key' => $apiKey, 'ip' => $ip));
            }
        }

        $signatureHeader = $this->getHeader('X-SIGNATURE');
        if ($signatureHeader !== '') {
            $computed = hash_hmac('sha256', $requestBody, $apiSecret);
            if (!hash_equals($computed, $signatureHeader)) {
                throw new ApiException('INVALID_SIGNATURE', 'İmza doğrulanamadı.', 401, array('api_key' => $apiKey, 'ip' => $ip));
            }
        }

        $this->keys->markUsed($apiKey);

        $reseller = $this->resellers->findById((int) $record['reseller_id']);
        if (!$reseller) {
            throw new ApiException('RESELLER_NOT_FOUND', 'Bayi kaydı bulunamadı.', 404);
        }

        return array(
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'reseller' => $reseller,
            'ip' => $ip,
        );
    }

    /**
     * @param array<string,mixed> $context
     * @param mixed $response
     */
    public function log(array $context, string $endpoint, string $method, int $status, float $startedAt, $response, string $requestBody): void
    {
        $responseTime = (int) round((microtime(true) - $startedAt) * 1000);
        $body = is_array($response) ? json_encode($response) : (is_string($response) ? $response : null);
        $this->logs->log(
            $context['api_key'],
            $endpoint,
            strtoupper($method),
            $context['ip'],
            $status,
            $responseTime,
            $requestBody === '' ? null : $requestBody,
            $body
        );
    }

    /**
     * @param array<int,string> $allowed
     */
    private function assertDomainAllowed(string $domain, array $allowed, string $apiKey, string $ip): void
    {
        $domain = strtolower($domain);
        foreach ($allowed as $candidate) {
            if ($domain === strtolower($candidate)) {
                return;
            }
        }

        throw new ApiException('DOMAIN_NOT_ALLOWED', 'Bu domain için yetki bulunmuyor.', 403, array('domain' => $domain, 'api_key' => $apiKey, 'ip' => $ip));
    }

    private function getHeader(string $name): string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (!empty($_SERVER[$serverKey])) {
            return trim((string) $_SERVER[$serverKey]);
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers[$name])) {
                return trim((string) $headers[$name]);
            }
            $lower = strtolower($name);
            foreach ($headers as $key => $value) {
                if (strtolower($key) === $lower) {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }

    private function clientIp(): string
    {
        $candidates = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        foreach ($candidates as $candidate) {
            if (!empty($_SERVER[$candidate])) {
                $value = (string) $_SERVER[$candidate];
                if ($candidate === 'HTTP_X_FORWARDED_FOR') {
                    $parts = explode(',', $value);
                    return trim($parts[0]);
                }
                return trim($value);
            }
        }

        return '0.0.0.0';
    }
}
