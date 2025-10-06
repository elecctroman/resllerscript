<?php declare(strict_types=1);

namespace App\Api\Controllers;

use App\ResellerApi\Exceptions\ApiException;
use App\ResellerApi\Repositories\ApiKeyRepository;
use App\ResellerApi\Services\AuthService;

final class ApiKeysController
{
    private AuthService $authService;
    private ApiKeyRepository $keys;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->keys = new ApiKeyRepository();
    }

    public function create(): void
    {
        try {
            $reseller = $this->authService->requireAuthenticatedReseller();
            $payload = read_json_body();
            $domains = $this->normaliseList(isset($payload['allowed_domains']) ? $payload['allowed_domains'] : array());
            $ips = $this->normaliseList(isset($payload['allowed_ips']) ? $payload['allowed_ips'] : array());

            $result = $this->keys->create((int) $reseller['id'], $domains, $ips);

            json_response(array(
                'success' => true,
                'api_key' => $result['api_key'],
                'api_secret' => $result['api_secret'],
            ), 201);
        } catch (ApiException $exception) {
            json_response(array(
                'success' => false,
                'error_code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ), $exception->getStatusCode());
        }
    }

    public function index(): void
    {
        try {
            $reseller = $this->authService->requireAuthenticatedReseller();
            $keys = $this->keys->listForReseller((int) $reseller['id']);

            json_response(array(
                'success' => true,
                'keys' => array_map(function ($key) {
                    return array(
                        'id' => (int) $key['id'],
                        'key' => $key['api_key'],
                        'status' => $key['status'],
                        'last_used' => $key['last_used_at'],
                        'created_at' => $key['created_at'],
                    );
                }, $keys),
            ));
        } catch (ApiException $exception) {
            json_response(array(
                'success' => false,
                'error_code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ), $exception->getStatusCode());
        }
    }

    public function revoke(): void
    {
        try {
            $reseller = $this->authService->requireAuthenticatedReseller();
            $payload = read_json_body();
            $key = isset($payload['key']) ? trim((string) $payload['key']) : '';

            if ($key === '') {
                throw new ApiException('VALIDATION_ERROR', 'İptal edilecek API anahtarını belirtin.', 422);
            }

            $success = $this->keys->revoke((int) $reseller['id'], $key);
            if (!$success) {
                throw new ApiException('INVALID_KEY', 'API anahtarı bulunamadı.', 404);
            }

            json_response(array('success' => true));
        } catch (ApiException $exception) {
            json_response(array(
                'success' => false,
                'error_code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ), $exception->getStatusCode());
        }
    }

    /**
     * @param mixed $input
     * @return array<int,string>
     */
    private function normaliseList($input): array
    {
        if (is_string($input)) {
            $input = array_map('trim', explode(',', $input));
        }

        if (!is_array($input)) {
            return array();
        }

        $normalised = array();
        foreach ($input as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $normalised[] = $value;
            }
        }

        return array_values(array_unique($normalised));
    }
}
