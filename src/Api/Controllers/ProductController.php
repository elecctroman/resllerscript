<?php declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Http\ApiResponse;
use App\Api\Http\Request;
use App\Api\Repositories\ProductRepository;

/**
 * Ürün listeleme uç noktasını yöneten denetleyici.
 */
final class ProductController
{
    private ProductRepository $repository;

    public function __construct()
    {
        $this->repository = new ProductRepository();
    }

    public function index(Request $request): ApiResponse
    {
        $products = $this->repository->listActive();

        return new ApiResponse(array(
            'data' => array(
                'products' => $products,
                'count' => count($products),
            ),
        ));
    }
}
