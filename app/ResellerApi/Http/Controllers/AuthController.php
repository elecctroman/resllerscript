<?php declare(strict_types=1);

namespace App\ResellerApi\Http\Controllers;

use App\ResellerApi\Exceptions\ApiException;
use App\ResellerApi\Http\Request;
use App\ResellerApi\Http\Response;
use App\ResellerApi\Services\ApiGateway;

final class AuthController
{
    private ApiGateway $gateway;

    public function __construct(ApiGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function login(Request $request): Response
    {
        $data = $request->json();
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $password = isset($data['password']) ? (string) $data['password'] : '';

        if ($email === '' || $password === '') {
            throw ApiException::validation('E-posta ve şifre zorunludur.');
        }

        $result = $this->gateway->issueToken($email, $password);
        return Response::json($result, 200);
    }
}
