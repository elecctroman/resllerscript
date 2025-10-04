<?php

namespace App\Services;

use App\Database;
use App\Settings;
use RuntimeException;

class ApiSecurityService
{
    /**
     * @param array<string,mixed> $tokenRow
     * @param string $ip
     * @param string $method
     * @param string $endpoint
     * @return void
     */
    public static function guard(array $tokenRow, string $ip, string $method, string $endpoint): void
    {
        self::assertActive($tokenRow);
        self::validateIpWhitelist($tokenRow, $ip);
        self::validateOtp($tokenRow);
        self::validateCaptcha();
        self::checkRateLimit($tokenRow, $ip, $method, $endpoint);
    }

    /**
     * @param array<string,mixed> $tokenRow
     * @return void
     */
    private static function assertActive(array $tokenRow): void
    {
        if (isset($tokenRow['token_status']) && $tokenRow['token_status'] !== 'active') {
            throw new RuntimeException('API anahtarı devre dışı.');
        }
    }

    /**
     * @param array<string,mixed> $tokenRow
     * @param string $ip
     * @return void
     */
    private static function validateIpWhitelist(array $tokenRow, string $ip): void
    {
        $whitelist = isset($tokenRow['ip_whitelist']) ? (string)$tokenRow['ip_whitelist'] : '';
        if ($whitelist === '') {
            return;
        }

        $ips = array_filter(array_map('trim', preg_split('/[,\n]+/', $whitelist) ?: array()));
        if (!$ips) {
            return;
        }

        foreach ($ips as $allowed) {
            if ($allowed === $ip) {
                return;
            }

            if (self::ipMatchesCidr($ip, $allowed)) {
                return;
            }
        }

        throw new RuntimeException('IP adresi beyaz listede değil.');
    }

    private static function ipMatchesCidr(string $ip, string $allowed): bool
    {
        if (strpos($allowed, '/') === false) {
            return false;
        }

        [$subnet, $mask] = explode('/', $allowed, 2);
        $mask = (int)$mask;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $mask = max(0, min(32, $mask));
            $maskLong = -1 << (32 - $mask);
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        return false;
    }

    /**
     * @param array<string,mixed> $tokenRow
     * @return void
     */
    private static function validateOtp(array $tokenRow): void
    {
        $secret = isset($tokenRow['otp_secret']) ? trim((string)$tokenRow['otp_secret']) : '';
        if ($secret === '') {
            return;
        }

        $headerCode = '';
        if (!empty($_SERVER['HTTP_X_API_OTP'])) {
            $headerCode = trim((string)$_SERVER['HTTP_X_API_OTP']);
        }

        if ($headerCode === '') {
            throw new RuntimeException('OTP doğrulaması başarısız.');
        }

        $current = self::generateTotp($secret, time());
        $previous = self::generateTotp($secret, time() - 30);
        $next = self::generateTotp($secret, time() + 30);

        if (!hash_equals($current, $headerCode) && !hash_equals($previous, $headerCode) && !hash_equals($next, $headerCode)) {
            throw new RuntimeException('OTP doğrulaması başarısız.');
        }
    }

    private static function generateTotp(string $secret, int $timestamp): string
    {
        $base32 = strtoupper($secret);
        $binarySecret = self::base32Decode($base32);
        if ($binarySecret === '') {
            $binarySecret = $secret;
        }

        $time = pack('N*', 0) . pack('N*', (int)floor($timestamp / 30));
        $hash = hash_hmac('sha1', $time, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4));
        $code = ($truncated[1] & 0x7FFFFFFF) % 1000000;
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $filtered = preg_replace('/[^A-Z2-7]/', '', strtoupper($input));
        if (!is_string($filtered) || $filtered === '') {
            return '';
        }
        $input = $filtered;

        $bits = '';
        foreach (str_split($input) as $char) {
            $value = strpos($alphabet, $char);
            if ($value === false) {
                continue;
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }

        return $output;
    }

    private static function validateCaptcha(): void
    {
        $secret = Settings::get('api_captcha_secret');
        if ($secret === null || $secret === '') {
            return;
        }

        $token = '';
        if (!empty($_SERVER['HTTP_X_CAPTCHA_TOKEN'])) {
            $token = trim((string)$_SERVER['HTTP_X_CAPTCHA_TOKEN']);
        }

        if ($token === '' || !hash_equals($secret, $token)) {
            throw new RuntimeException('Captcha doğrulaması başarısız.');
        }
    }

    /**
     * @param array<string,mixed> $tokenRow
     * @param string $ip
     * @param string $method
     * @param string $endpoint
     * @return void
     */
    private static function checkRateLimit(array $tokenRow, string $ip, string $method, string $endpoint): void
    {
        $limitPerMinute = (int)Settings::get('api_rate_limit_per_minute');
        if ($limitPerMinute <= 0) {
            $limitPerMinute = 120;
        }

        $bucketKey = substr(hash('sha256', $method . '|' . $endpoint), 0, 40);
        $tokenId = isset($tokenRow['token_id']) ? (int)$tokenRow['token_id'] : null;
        $minuteStart = date('Y-m-d H:i:00');

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $select = $pdo->prepare('SELECT id, hits, period_start FROM api_rate_limits WHERE token_id <=> :token_id AND ip_address = :ip AND bucket = :bucket LIMIT 1 FOR UPDATE');
            $select->execute(array(
                ':token_id' => $tokenId,
                ':ip' => $ip,
                ':bucket' => $bucketKey,
            ));
            $row = $select->fetch();

            if ($row) {
                $hits = (int)$row['hits'];
                $periodStart = (string)$row['period_start'];
                if ($periodStart !== $minuteStart) {
                    $update = $pdo->prepare('UPDATE api_rate_limits SET hits = 1, period_start = :period_start, updated_at = NOW() WHERE id = :id');
                    $update->execute(array(':period_start' => $minuteStart, ':id' => (int)$row['id']));
                } else {
                    if ($hits + 1 > $limitPerMinute) {
                        $pdo->rollBack();
                        throw new RuntimeException('Rate limit aşıldı.');
                    }
                    $update = $pdo->prepare('UPDATE api_rate_limits SET hits = hits + 1, updated_at = NOW() WHERE id = :id');
                    $update->execute(array(':id' => (int)$row['id']));
                }
            } else {
                $insert = $pdo->prepare('INSERT INTO api_rate_limits (token_id, ip_address, bucket, hits, period_start, updated_at) VALUES (:token_id, :ip, :bucket, 1, :period_start, NOW())');
                $insert->execute(array(
                    ':token_id' => $tokenId,
                    ':ip' => $ip,
                    ':bucket' => $bucketKey,
                    ':period_start' => $minuteStart,
                ));
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @param array<string,mixed>|null $tokenRow
     * @param string $ip
     * @param string $method
     * @param string $endpoint
     * @param int $status
     * @return void
     */
    public static function logRequest(?array $tokenRow, string $ip, string $method, string $endpoint, int $status): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO api_request_logs (token_id, ip_address, method, endpoint, status_code, user_agent) VALUES (:token_id, :ip, :method, :endpoint, :status, :agent)');
        $stmt->execute(array(
            ':token_id' => $tokenRow && isset($tokenRow['token_id']) ? (int)$tokenRow['token_id'] : null,
            ':ip' => $ip,
            ':method' => strtoupper($method),
            ':endpoint' => $endpoint,
            ':status' => $status,
            ':agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 250) : null,
        ));
    }

    /**
     * @param array<string,mixed> $tokenRow
     * @param string $requiredScope
     * @return void
     */
    public static function enforceScope(array $tokenRow, string $requiredScope): void
    {
        $scopesRaw = isset($tokenRow['scopes']) ? (string)$tokenRow['scopes'] : '';
        if ($scopesRaw === '' || strtolower($scopesRaw) === 'full') {
            return;
        }

        $scopes = array_filter(array_map('trim', preg_split('/[,\s]+/', strtolower($scopesRaw)) ?: array()));
        if (!$scopes) {
            throw new RuntimeException('API anahtarının gerekli yetkisi bulunmuyor.');
        }

        if (!in_array(strtolower($requiredScope), $scopes, true)) {
            throw new RuntimeException('API anahtarının gerekli yetkisi bulunmuyor.');
        }
    }
}
