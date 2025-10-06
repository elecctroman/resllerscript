<?php declare(strict_types=1);

namespace App\ResellerApi\Http\Controllers;

use App\Database;
use App\ResellerApi\Exceptions\ApiException;
use App\ResellerApi\Http\Request;
use App\ResellerApi\Http\Response;
use App\ResellerApi\Services\ApiGateway;
use App\Services\ProductOrderService;
use PDO;

final class DataController
{
    private ApiGateway $gateway;
    private PDO $pdo;

    public function __construct(ApiGateway $gateway)
    {
        $this->gateway = $gateway;
        $this->pdo = Database::connection();
    }

    public function products(Request $request): Response
    {
        $context = $this->gateway->authenticate($request, true);
        $stmt = $this->pdo->query('SELECT p.id, p.name, p.sku, p.description, p.price, p.automatic_delivery, c.name AS category_name FROM products p INNER JOIN categories c ON c.id = p.category_id WHERE p.status = "active" ORDER BY c.name, p.name');
        $products = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = array(
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'sku' => $row['sku'],
                'description' => $row['description'],
                'price' => (float) $row['price'],
                'automatic_delivery' => (bool) $row['automatic_delivery'],
                'category' => $row['category_name'],
            );
        }

        return Response::json(array('success' => true, 'data' => $products));
    }

    public function createOrder(Request $request): Response
    {
        $context = $this->gateway->authenticate($request, true);
        $reseller = $context['reseller'];
        $payload = $request->json();

        $productId = isset($payload['product_id']) ? (int) $payload['product_id'] : 0;
        $note = isset($payload['note']) ? trim((string) $payload['note']) : null;
        $externalReference = isset($payload['external_reference']) ? trim((string) $payload['external_reference']) : null;

        if ($productId <= 0) {
            throw ApiException::validation('Sipariş verilecek ürün belirtilmelidir.');
        }

        $user = $this->gateway->authService()->ensureResellerUser((int) $reseller['id']);
        if (!$user) {
            throw ApiException::badRequest('Bayi kullanıcı kaydı oluşturulamadı.');
        }

        $result = ProductOrderService::placePanelOrder($user, $productId, $note);
        if (!$result['success']) {
            throw ApiException::badRequest($result['message'] ?? 'Sipariş oluşturulamadı.');
        }

        $orderId = (int) $result['order_id'];
        $update = $this->pdo->prepare('UPDATE product_orders SET source = :source, external_reference = :external_reference WHERE id = :id');
        $update->execute(array(
            'source' => 'api',
            'external_reference' => $externalReference,
            'id' => $orderId,
        ));

        $stmt = $this->pdo->prepare('SELECT po.id, po.status, po.created_at, po.external_reference, p.name AS product_name FROM product_orders po INNER JOIN products p ON p.id = po.product_id WHERE po.id = :id LIMIT 1');
        $stmt->execute(array('id' => $orderId));
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        $response = array(
            'success' => true,
            'data' => array(
                'order_id' => $orderId,
                'status' => $order ? $order['status'] : 'pending',
                'product' => $order ? $order['product_name'] : null,
                'created_at' => $order['created_at'] ?? null,
                'external_reference' => $externalReference,
            ),
        );

        return Response::json($response, 201);
    }

    public function orderStatus(Request $request): Response
    {
        $context = $this->gateway->authenticate($request, true);
        $reseller = $context['reseller'];
        $query = $request->query();
        $orderId = isset($query['order_id']) ? (int) $query['order_id'] : 0;
        $reference = isset($query['external_reference']) ? trim((string) $query['external_reference']) : null;

        if ($orderId <= 0 && !$reference) {
            throw ApiException::validation('order_id veya external_reference parametrelerinden biri gereklidir.');
        }

        $user = $this->gateway->authService()->ensureResellerUser((int) $reseller['id']);
        $sql = 'SELECT po.id, po.status, po.created_at, po.updated_at, po.external_reference, po.note, po.admin_note, p.name AS product_name FROM product_orders po INNER JOIN products p ON p.id = po.product_id WHERE po.user_id = :user_id';
        $params = array('user_id' => $user['id']);
        if ($orderId > 0) {
            $sql .= ' AND po.id = :order_id';
            $params['order_id'] = $orderId;
        } else {
            $sql .= ' AND po.external_reference = :external_reference';
            $params['external_reference'] = $reference;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw ApiException::notFound('Sipariş bulunamadı.');
        }

        $response = array(
            'success' => true,
            'data' => array(
                'order_id' => (int) $order['id'],
                'status' => $order['status'],
                'product' => $order['product_name'],
                'note' => $order['note'],
                'admin_note' => $order['admin_note'],
                'external_reference' => $order['external_reference'],
                'created_at' => $order['created_at'],
                'updated_at' => $order['updated_at'],
            ),
        );

        return Response::json($response);
    }

    public function balance(Request $request): Response
    {
        $context = $this->gateway->authenticate($request, true);
        $reseller = $context['reseller'];
        $user = $this->gateway->authService()->ensureResellerUser((int) $reseller['id']);
        $balance = (float) $user['balance'];

        return Response::json(array('success' => true, 'balance' => $balance, 'currency' => 'TRY'));
    }

    public function userInfo(Request $request): Response
    {
        $context = $this->gateway->authenticate($request, true);
        $reseller = $context['reseller'];
        $user = $this->gateway->authService()->ensureResellerUser((int) $reseller['id']);

        $response = array(
            'success' => true,
            'data' => array(
                'id' => (int) $reseller['id'],
                'name' => $reseller['name'],
                'email' => $reseller['email'],
                'balance' => (float) $user['balance'],
                'status' => $reseller['status'],
            ),
        );

        return Response::json($response);
    }
}
