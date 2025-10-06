<?php declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Exceptions\BadRequestException;
use App\Api\Http\ApiResponse;
use App\Api\Http\Request;
use App\Api\Repositories\UserRepository;

/**
 * Bayi ve token meta verilerini döndüren uç nokta denetleyicisi.
 */
final class UserController
{
    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    public function info(Request $request): ApiResponse
    {
        $token = $request->getToken();
        if ($token === null) {
            throw new BadRequestException('Kimlik doğrulama başarısız.');
        }

        $userId = isset($token['user_id']) ? (int) $token['user_id'] : 0;
        if ($userId <= 0) {
            throw new BadRequestException('API anahtarı için kullanıcı bulunamadı.');
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new BadRequestException('Kullanıcı bulunamadı.');
        }

        $tokenInfo = array(
            'token_id' => isset($token['token_id']) ? (int) $token['token_id'] : null,
            'label' => $token['label'] ?? null,
            'scopes' => $token['scopes'] ?? null,
            'ip_whitelist' => $token['ip_whitelist'] ?? null,
            'rate_limit_per_minute' => $token['rate_limit_per_minute'] ?? null,
        );

        return new ApiResponse(array(
            'data' => array(
                'user' => $user,
                'token' => $tokenInfo,
            ),
        ));
    }
}
