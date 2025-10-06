<?php declare(strict_types=1);

namespace App\Api\Controllers;

use App\ResellerApi\Exceptions\ApiException;
use App\ResellerApi\Repositories\ApiKeyRepository;
use App\ResellerApi\Services\AuthService;

final class ResellerController
{
    private AuthService $authService;
    private ApiKeyRepository $keys;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->keys = new ApiKeyRepository();
    }

    public function profile(): void
    {
        try {
            $reseller = $this->authService->requireAuthenticatedReseller();
            $keys = $this->keys->listForReseller((int) $reseller['id']);

            json_response(array(
                'success' => true,
                'data' => array(
                    'id' => (int) $reseller['id'],
                    'name' => $reseller['name'],
                    'email' => $reseller['email'],
                    'status' => $reseller['status'],
                    'api_keys' => array_map(static function ($key) {
                        return array(
                            'id' => (int) $key['id'],
                            'key' => $key['api_key'],
                            'status' => $key['status'],
                            'last_used' => $key['last_used_at'],
                            'created_at' => $key['created_at'],
                        );
                    }, $keys),
                ),
            ));
        } catch (ApiException $exception) {
            json_response(array(
                'success' => false,
                'error_code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ), $exception->getStatusCode());
        }
    }
}
