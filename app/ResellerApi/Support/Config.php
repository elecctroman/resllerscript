<?php declare(strict_types=1);

namespace App\ResellerApi\Support;

use App\Settings;

final class Config
{
    public static function secretKey(): string
    {
        $fromEnv = getenv('RESELLER_API_SECRET');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }
        $setting = Settings::get('reseller_api_secret');
        if (is_string($setting) && $setting !== '') {
            return $setting;
        }
        $generated = hash('sha256', __FILE__ . php_uname());
        Settings::set('reseller_api_secret', $generated);
        return $generated;
    }

    public static function rateLimitFallback(): int
    {
        $configured = Settings::get('reseller_api_rate_limit');
        if ($configured !== null && is_numeric($configured)) {
            return max(100, (int) $configured);
        }
        return 1000;
    }
}
