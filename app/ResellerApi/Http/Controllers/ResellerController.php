<?php declare(strict_types=1);

namespace App\ResellerApi\Http\Controllers;

use App\ResellerApi\Http\Request;
use App\ResellerApi\Http\Response;
use App\ResellerApi\Services\ApiGateway;

final class ResellerController
{
    private ApiGateway $gateway;

    public function __construct(ApiGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function profile(Request $request): Response
    {
        $context = $this->gateway->authenticate($request, false);
        $reseller = $context['reseller'];
        $keys = $this->gateway->listKeys((int) $reseller['id']);

        $response = array(
            'success' => true,
            'data' => array(
                'id' => (int) $reseller['id'],
                'name' => $reseller['name'],
                'email' => $reseller['email'],
                'status' => $reseller['status'],
                'rate_limit_per_hour' => (int) $reseller['rate_limit_per_hour'],
                'api_keys' => array_map(static function (array $key): array {
                    return array(
                        'id' => (int) $key['id'],
                        'key' => $key['api_key'],
                        'status' => $key['status'],
                        'last_used' => $key['last_used_at'],
                        'created_at' => $key['created_at'],
                    );
                }, $keys),
            ),
        );

        return Response::json($response);
    }
}
