<?php declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * API istekleri için opsiyonel HMAC doğrulama kontrollerini gerçekleştirir.
 */
final class ApiSignatureService
{
    /**
     * Validate the optional HMAC signature headers for the API request.
     *
     * @param array<string,mixed> $tokenRow
     * @param string $rawBody
     * @param string $method
     * @param string $endpoint
     */
    public static function assertValid(array $tokenRow, string $rawBody, string $method, string $endpoint): void
    {
        $secret = isset($tokenRow['hmac_secret']) ? trim((string) $tokenRow['hmac_secret']) : '';
        if ($secret === '') {
            return; // HMAC zorunlu değil.
        }

        $timestamp = self::readHeader('HTTP_X_REQUEST_TIMESTAMP');
        if ($timestamp === null) {
            $timestamp = self::readHeader('HTTP_X_TIMESTAMP');
        }
        if ($timestamp === null) {
            throw new RuntimeException('HMAC doğrulaması için zaman damgası bulunamadı.');
        }

        if (!ctype_digit($timestamp)) {
            throw new RuntimeException('Geçersiz zaman damgası değeri.');
        }

        $requestTime = (int) $timestamp;
        $now = time();
        if (abs($now - $requestTime) > 300) {
            throw new RuntimeException('İmza zaman aşımına uğradı.');
        }

        $signature = self::readHeader('HTTP_X_SIGNATURE');
        if ($signature === null) {
            $signature = self::readHeader('HTTP_X_HMAC_SIGNATURE');
        }
        if ($signature === null) {
            throw new RuntimeException('HMAC imzası bulunamadı.');
        }

        $signature = trim($signature);
        if ($signature === '') {
            throw new RuntimeException('HMAC imzası bulunamadı.');
        }

        $hash = $signature;
        if (stripos($hash, 'sha256=') === 0) {
            $hash = substr($hash, 7);
        }

        if (!preg_match('/^[A-Fa-f0-9]{64}$/', $hash)) {
            throw new RuntimeException('HMAC imza formatı geçersiz.');
        }

        $payload = $requestTime . "\n" . strtoupper($method) . "\n" . $endpoint . "\n" . $rawBody;
        $computed = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($computed, strtolower($hash))) {
            throw new RuntimeException('HMAC imzası doğrulanamadı.');
        }
    }

    private static function readHeader(string $name): ?string
    {
        return isset($_SERVER[$name]) ? (string) $_SERVER[$name] : null;
    }
}
