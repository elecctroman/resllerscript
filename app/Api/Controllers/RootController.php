<?php declare(strict_types=1);

namespace App\Api\Controllers;

use App\Helpers;
use App\Settings;

final class RootController
{
    public function index(): void
    {
        json_response(array(
            'success' => true,
            'message' => 'Reseller API. Sürüm 1 kullanıma hazırdır.',
            'links' => array(
                'v1' => Helpers::apiBaseUrl(),
                'documentation' => Helpers::url('/api-documentation.php', true),
            ),
        ));
    }

    public function version(): void
    {
        $rateLimit = Settings::get('api_rate_limit_per_minute', 120);
        $rateLimit = $rateLimit ? (int)$rateLimit : 120;

        json_response(array(
            'success' => true,
            'message' => 'API v1 aktif. products, orders, profile ve token-webhook uç noktalarını kullanabilirsiniz.',
            'meta' => array(
                'rate_limit_per_minute' => $rateLimit,
            ),
        ));
    }

    public function ping(): void
    {
        json_response(array(
            'success' => true,
            'message' => 'pong',
        ));
    }
}
