<?php declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Exceptions\BadRequestException;
use App\Api\Http\ApiResponse;
use App\Api\Http\Request;
use App\Api\Repositories\UserRepository;

/**
 * Kullanıcı bakiyesi sorgusunu sağlayan denetleyici.
 */
final class BalanceController
{
    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    public function show(Request $request): ApiResponse
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

        return new ApiResponse(array(
            'data' => array(
                'balance' => $user['balance'],
                'currency' => 'TRY',
                'updated_at' => $user['updated_at'],
            ),
        ));
    }
}
