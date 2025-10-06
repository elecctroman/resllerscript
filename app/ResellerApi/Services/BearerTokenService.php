<?php

declare(strict_types=1);

namespace App\ResellerApi\Services;

use App\ResellerApi\Exceptions\ApiException;
use App\ResellerApi\Support\Config;

final class BearerTokenService
{
    public function issue(int $resellerId, string $email, int $ttl = 3600): array
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + max(60, $ttl);

        $payload = array(
            'sub' => $resellerId,
            'email' => $email,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        );

        $encoded = $this->encode(json_encode($payload));
        $signature = $this->sign($encoded);

        return array('token' => $encoded . '.' . $signature, 'expires_at' => $expiresAt);
    }

    /**
     * @return array<string,mixed>
     */
    public function validate(string $token): array
    {
        if (strpos($token, '.') === false) {
            throw new ApiException('INVALID_TOKEN', 'Sağlanan erişim anahtarı doğrulanamadı.', 401);
        }

        list($encoded, $signature) = explode('.', $token, 2);
        if (!hash_equals($this->sign($encoded), $signature)) {
            throw new ApiException('INVALID_TOKEN', 'Token imzası geçersiz.', 401);
        }

        $payloadJson = $this->decode($encoded);
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw new ApiException('INVALID_TOKEN', 'Token verisi çözümlenemedi.', 401);
        }

        if (!isset($payload['exp']) || (int) $payload['exp'] < time()) {
            throw new ApiException('TOKEN_EXPIRED', 'Oturum süresi sona erdi, lütfen tekrar giriş yapın.', 401);
        }

        return $payload;
    }

    private function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function decode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }

    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, Config::masterSecret());
    }
}
