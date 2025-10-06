<?php

declare(strict_types=1);

namespace App\ResellerApi\Services;

use App\Auth;
use App\ResellerApi\Exceptions\ApiException;
use App\ResellerApi\Repositories\ResellerRepository;

final class AuthService
{
    private ResellerRepository $resellerRepository;
    private BearerTokenService $tokenService;

    public function __construct()
    {
        $this->resellerRepository = new ResellerRepository();
        $this->tokenService = new BearerTokenService();
    }

    /**
     * @return array{token:string,expires_at:int,reseller:array<string,mixed>}
     */
    public function login(string $email, string $password): array
    {
        $user = Auth::attempt($email, $password);
        if (!$user) {
            throw new ApiException('INVALID_CREDENTIALS', 'E-posta veya şifre hatalı.', 401);
        }

        if (!isset($user['role']) || $user['role'] !== 'reseller') {
            throw new ApiException('NOT_ALLOWED', 'Bu kullanıcı hesabı bayi APIsine erişemez.', 403);
        }

        $reseller = $this->resellerRepository->ensureFromUser($user);
        if (!$reseller || ($reseller['status'] ?? 'active') !== 'active') {
            throw new ApiException('ACCOUNT_INACTIVE', 'Bayi hesabınız aktif değil. Lütfen yönetici ile iletişime geçin.', 403);
        }

        $token = $this->tokenService->issue((int) $reseller['id'], (string) $reseller['email']);

        return array(
            'token' => $token['token'],
            'expires_at' => $token['expires_at'],
            'reseller' => $reseller,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function requireAuthenticatedReseller(): array
    {
        $token = $this->extractBearerToken();
        $payload = $this->tokenService->validate($token);

        $reseller = $this->resellerRepository->findById((int) $payload['sub']);
        if (!$reseller) {
            throw new ApiException('RESELLER_NOT_FOUND', 'Bayi kaydı bulunamadı.', 404);
        }

        if (($reseller['status'] ?? 'active') !== 'active') {
            throw new ApiException('ACCOUNT_INACTIVE', 'Bayi hesabınız aktif değil.', 403);
        }

        return $reseller;
    }

    private function extractBearerToken(): string
    {
        $headers = array();
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        }

        $authHeader = '';
        if (isset($headers['Authorization'])) {
            $authHeader = (string) $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $authHeader = (string) $headers['authorization'];
        } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = (string) $_SERVER['HTTP_AUTHORIZATION'];
        }

        if ($authHeader !== '' && stripos($authHeader, 'Bearer ') === 0) {
            return trim(substr($authHeader, 7));
        }

        if (!empty($_GET['token'])) {
            return trim((string) $_GET['token']);
        }

        throw new ApiException('AUTH_REQUIRED', 'Bu uç noktaya erişmek için oturum açmanız gerekir.', 401);
    }
}
