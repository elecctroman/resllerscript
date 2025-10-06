<?php declare(strict_types=1);

namespace App\Api\Controllers;

use App\ResellerApi\Exceptions\ApiException;
use App\ResellerApi\Services\AuthService;

final class AuthController
{
    public function login(): void
    {
        try {
            $payload = read_json_body();
            $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
            $password = isset($payload['password']) ? (string) $payload['password'] : '';

            if ($email === '' || $password === '') {
                throw new ApiException('VALIDATION_ERROR', 'E-posta ve şifre alanları zorunludur.', 422);
            }

            $service = new AuthService();
            $result = $service->login($email, $password);

            json_response(array(
                'success' => true,
                'token' => $result['token'],
                'expires_at' => gmdate('c', $result['expires_at']),
                'reseller' => array(
                    'id' => (int) $result['reseller']['id'],
                    'name' => $result['reseller']['name'],
                    'email' => $result['reseller']['email'],
                    'status' => $result['reseller']['status'],
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
