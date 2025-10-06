<?php

declare(strict_types=1);

namespace App\ResellerApi\Support;

final class Config
{
    public static function masterSecret(): string
    {
        if (defined('API_MASTER_SECRET') && API_MASTER_SECRET !== '') {
            return (string) API_MASTER_SECRET;
        }

        return 'reseller-api-default-secret';
    }

    public static function rateLimitPerHour(): int
    {
        if (defined('API_RATE_LIMIT_PER_HOUR') && (int) API_RATE_LIMIT_PER_HOUR > 0) {
            return (int) API_RATE_LIMIT_PER_HOUR;
        }

        return 1000;
    }
}
