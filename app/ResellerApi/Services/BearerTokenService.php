<?php declare(strict_types=1);

namespace App\ResellerApi\Services;

use App\ResellerApi\Support\Config;

final class BearerTokenService
{
    private const TTL_SECONDS = 86400;

    public function issueToken(int $resellerId): string
    {
        $payload = array(
            'sub' => $resellerId,
            'exp' => time() + self::TTL_SECONDS,
            'iat' => time(),
        );
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $signature = $this->sign($encoded);
        return $encoded . '.' . $signature;
    }

    public function validateToken(string $token): ?int
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        list($encoded, $signature) = $parts;
        if (!hash_equals($this->sign($encoded), $signature)) {
            return null;
        }
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        if (!is_string($json)) {
            return null;
        }
        $payload = json_decode($json, true);
        if (!is_array($payload) || !isset($payload['sub'], $payload['exp'])) {
            return null;
        }
        if ((int) $payload['exp'] < time()) {
            return null;
        }
        return (int) $payload['sub'];
    }

    private function sign(string $encoded): string
    {
        $secret = Config::secretKey();
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $encoded, $secret, true)), '+/', '-_'), '=');
    }
}
