<?php declare(strict_types=1);

namespace App\ResellerApi\Http\Controllers;

use App\ResellerApi\Exceptions\ApiException;
use App\ResellerApi\Http\Request;
use App\ResellerApi\Http\Response;
use App\ResellerApi\Services\ApiGateway;

final class ApiKeysController
{
    private ApiGateway $gateway;

    public function __construct(ApiGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function create(Request $request): Response
    {
        $context = $this->gateway->authenticate($request, false);
        $reseller = $context['reseller'];
        $payload = $request->json();

        $ips = isset($payload['allowed_ips']) && is_array($payload['allowed_ips']) ? array_filter(array_map('trim', $payload['allowed_ips'])) : null;
        $domains = isset($payload['allowed_domains']) && is_array($payload['allowed_domains']) ? array_filter(array_map('trim', $payload['allowed_domains'])) : null;

        $record = $this->gateway->generateApiKey((int) $reseller['id'], $ips, $domains);

        $response = array(
            'success' => true,
            'data' => array(
                'key' => $record['api_key'],
                'secret' => $record['api_secret_plain'],
                'status' => $record['status'],
                'allowed_ips' => $ips,
                'allowed_domains' => $domains,
            ),
        );

        return Response::json($response, 201);
    }

    public function index(Request $request): Response
    {
        $context = $this->gateway->authenticate($request, false);
        $reseller = $context['reseller'];
        $keys = $this->gateway->listKeys((int) $reseller['id']);

        $items = array();
        foreach ($keys as $key) {
            $items[] = array(
                'id' => (int) $key['id'],
                'key' => $key['api_key'],
                'status' => $key['status'],
                'last_used' => $key['last_used_at'],
                'created_at' => $key['created_at'],
                'allowed_ips' => $key['allowed_ips'],
                'allowed_domains' => $key['allowed_domains'],
            );
        }

        return Response::json(array('success' => true, 'keys' => $items));
    }

    public function revoke(Request $request): Response
    {
        $context = $this->gateway->authenticate($request, false);
        $reseller = $context['reseller'];
        $payload = $request->json();
        $key = isset($payload['key']) ? trim((string) $payload['key']) : '';

        if ($key === '') {
            throw ApiException::validation('İptal edilecek API anahtarı belirtilmelidir.');
        }

        $this->gateway->revokeKey($key, (int) $reseller['id']);

        return Response::json(array('success' => true));
    }
}
