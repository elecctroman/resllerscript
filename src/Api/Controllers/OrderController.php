<?php declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Exceptions\BadRequestException;
use App\Api\Exceptions\NotFoundException;
use App\Api\Exceptions\ValidationException;
use App\Api\Http\ApiResponse;
use App\Api\Http\Request;
use App\Api\Repositories\OrderRepository;
use App\Api\Repositories\ProductRepository;
use App\Api\Repositories\UserRepository;
use App\Api\Services\OrderService;

/**
 * Sipariş oluşturma ve durum sorgulama uç noktalarını yönetir.
 */
final class OrderController
{
    private OrderService $service;

    public function __construct()
    {
        $this->service = new OrderService(
            new ProductRepository(),
            new OrderRepository(),
            new UserRepository()
        );
    }

    public function create(Request $request): ApiResponse
    {
        $token = $request->getToken();
        if ($token === null) {
            throw new BadRequestException('Kimlik doğrulama başarısız.');
        }

        $payload = $request->getJsonBody();
        $result = $this->service->createOrder($token, $payload);

        return new ApiResponse(array(
            'data' => $result,
        ), 201);
    }

    public function status(Request $request): ApiResponse
    {
        $token = $request->getToken();
        if ($token === null) {
            throw new BadRequestException('Kimlik doğrulama başarısız.');
        }

        $orderId = null;
        $reference = null;

        if ($request->hasQuery('order_id')) {
            $orderId = (int) $request->query('order_id');
        }

        if ($request->hasQuery('external_reference')) {
            $reference = (string) $request->query('external_reference');
        }

        if (($orderId === null || $orderId <= 0) && ($reference === null || $reference === '')) {
            throw new ValidationException('Sipariş sorgusu doğrulanamadı.', array(
                'order_id' => 'order_id veya external_reference parametrelerinden en az biri gereklidir.',
            ));
        }

        $userId = isset($token['user_id']) ? (int) $token['user_id'] : 0;
        if ($userId <= 0) {
            throw new BadRequestException('API anahtarı için kullanıcı bulunamadı.');
        }

        if ($orderId !== null && $orderId > 0) {
            $order = $this->service->findOrderById($orderId, $userId);
        } else {
            $order = $this->service->findOrderByReference((string) $reference, $userId);
        }

        if ($order === null) {
            throw new NotFoundException('Sipariş bulunamadı.');
        }

        return new ApiResponse(array(
            'data' => array('order' => $order),
        ));
    }
}
