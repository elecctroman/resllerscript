<?php declare(strict_types=1);

namespace App\Api\Controllers;

use App\Database;
use App\ResellerApi\Exceptions\ApiException;
use App\ResellerApi\Repositories\ResellerRepository;
use App\ResellerApi\Services\ApiGateway;
use App\Services\ProductOrderService;
use PDO;
use Throwable;

final class DataController
{
    private ApiGateway $gateway;
    private ResellerRepository $resellers;

    public function __construct()
    {
        $this->gateway = new ApiGateway();
        $this->resellers = new ResellerRepository();
    }

    public function products(): void
    {
        $startedAt = microtime(true);
        $requestBody = '';
        try {
            $context = $this->gateway->authenticate('GET', '/api/v1/data/products', $requestBody);

            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? max(1, min(100, (int) $_GET['per_page'])) : 50;
            $offset = ($page - 1) * $perPage;

            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT p.id, p.name, p.sku, p.description, p.price, c.name AS category_name FROM products p INNER JOIN categories c ON c.id = p.category_id WHERE p.status = :status ORDER BY p.id DESC LIMIT :limit OFFSET :offset');
            $stmt->bindValue(':status', 'active', PDO::PARAM_STR);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = array(
                'success' => true,
                'data' => $products,
                'meta' => array(
                    'page' => $page,
                    'per_page' => $perPage,
                ),
            );

            $this->gateway->log($context, '/api/v1/data/products', 'GET', 200, $startedAt, $response, $requestBody);
            json_response($response);
        } catch (ApiException $exception) {
            $ctx = $exception->getContext();
            $apiKey = isset($ctx['api_key']) ? (string) $ctx['api_key'] : 'unknown';
            $ip = isset($ctx['ip']) ? (string) $ctx['ip'] : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            $this->gateway->log(array('api_key' => $apiKey, 'ip' => $ip), '/api/v1/data/products', 'GET', $exception->getStatusCode(), $startedAt, array('error_code' => $exception->getErrorCode()), $requestBody);
            json_response(array(
                'success' => false,
                'error_code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ), $exception->getStatusCode());
        }
    }

    public function profile(): void
    {
        $startedAt = microtime(true);
        $requestBody = '';
        try {
            $context = $this->gateway->authenticate('GET', '/api/v1/data/profile', $requestBody);
            $reseller = $context['reseller'];
            $userId = $this->resellers->mapResellerToUserId($reseller);

            $balance = 0.0;
            if ($userId !== null) {
                $pdo = Database::connection();
                $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id LIMIT 1');
                $stmt->execute(array('id' => $userId));
                $balance = (float) $stmt->fetchColumn();
            }

            $response = array(
                'success' => true,
                'data' => array(
                    'id' => (int) $reseller['id'],
                    'name' => $reseller['name'],
                    'email' => $reseller['email'],
                    'status' => $reseller['status'],
                    'balance' => $balance,
                ),
            );

            $this->gateway->log($context, '/api/v1/data/profile', 'GET', 200, $startedAt, $response, $requestBody);
            json_response($response);
        } catch (ApiException $exception) {
            $ctx = $exception->getContext();
            $apiKey = isset($ctx['api_key']) ? (string) $ctx['api_key'] : 'unknown';
            $ip = isset($ctx['ip']) ? (string) $ctx['ip'] : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            $this->gateway->log(array('api_key' => $apiKey, 'ip' => $ip), '/api/v1/data/profile', 'GET', $exception->getStatusCode(), $startedAt, array('error_code' => $exception->getErrorCode()), $requestBody);
            json_response(array(
                'success' => false,
                'error_code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ), $exception->getStatusCode());
        }
    }

    public function orders(): void
    {
        $startedAt = microtime(true);
        $requestBody = '';
        try {
            $context = $this->gateway->authenticate('GET', '/api/v1/data/orders', $requestBody);
            $reseller = $context['reseller'];
            $userId = $this->resellers->mapResellerToUserId($reseller);
            if ($userId === null) {
                throw new ApiException('USER_NOT_FOUND', 'Bayi hesabına bağlı kullanıcı bulunamadı.', 404);
            }

            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT o.id, o.product_id, p.name AS product_name, o.status, o.price, o.created_at FROM product_orders o INNER JOIN products p ON p.id = o.product_id WHERE o.user_id = :user_id ORDER BY o.created_at DESC LIMIT 100');
            $stmt->execute(array('user_id' => $userId));
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = array('success' => true, 'data' => $orders);
            $this->gateway->log($context, '/api/v1/data/orders', 'GET', 200, $startedAt, $response, $requestBody);
            json_response($response);
        } catch (ApiException $exception) {
            $ctx = $exception->getContext();
            $apiKey = isset($ctx['api_key']) ? (string) $ctx['api_key'] : 'unknown';
            $ip = isset($ctx['ip']) ? (string) $ctx['ip'] : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            $this->gateway->log(array('api_key' => $apiKey, 'ip' => $ip), '/api/v1/data/orders', 'GET', $exception->getStatusCode(), $startedAt, array('error_code' => $exception->getErrorCode()), $requestBody);
            json_response(array(
                'success' => false,
                'error_code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ), $exception->getStatusCode());
        }
    }

    public function createOrder(): void
    {
        $startedAt = microtime(true);
        try {
            $requestBody = file_get_contents('php://input') ?: '';
            $context = $this->gateway->authenticate('POST', '/api/v1/data/orders', $requestBody);
            $payload = $requestBody !== '' ? json_decode($requestBody, true) : array();
            if (!is_array($payload)) {
                throw new ApiException('VALIDATION_ERROR', 'Geçersiz JSON yükü.', 400);
            }

            $productId = isset($payload['product_id']) ? (int) $payload['product_id'] : 0;
            $note = isset($payload['note']) ? (string) $payload['note'] : null;

            if ($productId <= 0) {
                throw new ApiException('VALIDATION_ERROR', 'Ürün numarası zorunludur.', 422);
            }

            $userId = $this->resellers->mapResellerToUserId($context['reseller']);
            if ($userId === null) {
                throw new ApiException('USER_NOT_FOUND', 'Bayi hesabına bağlı kullanıcı bulunamadı.', 404);
            }

            $pdo = Database::connection();
            $userStmt = $pdo->prepare('SELECT id, name FROM users WHERE id = :id LIMIT 1');
            $userStmt->execute(array('id' => $userId));
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                throw new ApiException('USER_NOT_FOUND', 'Kullanıcı kaydı bulunamadı.', 404);
            }

            $result = ProductOrderService::placePanelOrder($user, $productId, $note);
            if (empty($result['success'])) {
                throw new ApiException('ORDER_FAILED', isset($result['message']) ? (string) $result['message'] : 'Sipariş oluşturulamadı.', 422);
            }

            $response = array(
                'success' => true,
                'data' => array(
                    'order_id' => $result['order_id'],
                    'status' => $result['status'],
                    'message' => $result['message'],
                ),
            );
            $this->gateway->log($context, '/api/v1/data/orders', 'POST', 201, $startedAt, $response, $requestBody);
            json_response($response, 201);
        } catch (ApiException $exception) {
            $body = array(
                'success' => false,
                'error_code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            );
            $ctx = $exception->getContext();
            $apiKey = isset($ctx['api_key']) ? (string) $ctx['api_key'] : 'unknown';
            $ip = isset($ctx['ip']) ? (string) $ctx['ip'] : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            $this->gateway->log(array('api_key' => $apiKey, 'ip' => $ip), '/api/v1/data/orders', 'POST', $exception->getStatusCode(), $startedAt, $body, $requestBody ?? '');
            json_response($body, $exception->getStatusCode());
        } catch (Throwable $throwable) {
            $body = array(
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Sipariş oluşturma sırasında beklenmeyen bir hata oluştu.',
            );
            $this->gateway->log(
                array('api_key' => 'unknown', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
                '/api/v1/data/orders',
                'POST',
                500,
                $startedAt,
                $body,
                $requestBody ?? ''
            );
            json_response($body, 500);
        }
    }
}
