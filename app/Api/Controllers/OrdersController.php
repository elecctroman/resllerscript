<?php declare(strict_types=1);

namespace App\Api\Controllers;

use App\Database;
use App\Services\ProviderDispatchService;
use PDO;
use PDOException;

final class OrdersController
{
    public function index(): void
    {
        $token = authenticate_token();
        require_scope($token, 'read');

        $pdo = Database::connection();

        $externalReference = isset($_GET['external_reference']) ? trim((string) $_GET['external_reference']) : '';
        $statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
        $since = isset($_GET['since']) ? trim((string) $_GET['since']) : '';

        $query = 'SELECT po.*, pr.name AS product_name, pr.sku AS product_sku FROM product_orders po INNER JOIN products pr ON po.product_id = pr.id WHERE po.user_id = :user_id';
        $params = array('user_id' => $token['user_id']);

        if ($externalReference !== '') {
            $query .= ' AND po.external_reference = :external_reference';
            $params['external_reference'] = $externalReference;
        }

        if ($statusFilter !== '') {
            $query .= ' AND po.status = :status';
            $params['status'] = $statusFilter;
        }

        if ($since !== '') {
            $query .= ' AND po.updated_at >= :since';
            $params['since'] = $since;
        }

        $query .= ' ORDER BY po.created_at DESC';

        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }

        $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 50;
        if ($perPage < 1) {
            $perPage = 50;
        }
        if ($perPage > 200) {
            $perPage = 200;
        }

        $offset = ($page - 1) * $perPage;
        $query .= ' LIMIT :limit OFFSET :offset';

        $countQuery = 'SELECT COUNT(*) FROM product_orders po WHERE po.user_id = :user_id';
        $countParams = array('user_id' => $token['user_id']);

        if ($externalReference !== '') {
            $countQuery .= ' AND po.external_reference = :external_reference';
            $countParams['external_reference'] = $externalReference;
        }

        if ($statusFilter !== '') {
            $countQuery .= ' AND po.status = :status';
            $countParams['status'] = $statusFilter;
        }

        if ($since !== '') {
            $countQuery .= ' AND po.updated_at >= :since';
            $countParams['since'] = $since;
        }

        try {
            $stmt = $pdo->prepare($query);
            foreach ($params as $key => $value) {
                $paramType = $key === 'user_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue(':' . $key, $value, $paramType);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $orders = $stmt->fetchAll();

            $responseOrders = array();
            foreach ($orders as $order) {
                $responseOrders[] = $this->transformOrderRow($order);
            }

            $countStmt = $pdo->prepare($countQuery);
            foreach ($countParams as $key => $value) {
                $paramType = $key === 'user_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
                $countStmt->bindValue(':' . $key, $value, $paramType);
            }
            $countStmt->execute();
            $total = (int) $countStmt->fetchColumn();

            json_response(array(
                'success' => true,
                'data' => array(
                    'orders' => $responseOrders,
                    'pagination' => array(
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                    ),
                ),
            ));
        } catch (PDOException $exception) {
            json_response(array('success' => false, 'error' => 'Siparişler getirilemedi: ' . $exception->getMessage()), 500);
        }
    }

    public function show(string $id): void
    {
        $token = authenticate_token();
        require_scope($token, 'read');

        $orderId = (int) $id;
        if ($orderId <= 0) {
            json_response(array('success' => false, 'error' => 'Geçersiz sipariş kimliği.'), 400);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT po.*, pr.name AS product_name, pr.sku AS product_sku FROM product_orders po INNER JOIN products pr ON po.product_id = pr.id WHERE po.id = :id AND po.user_id = :user_id LIMIT 1');
        $stmt->execute(array('id' => $orderId, 'user_id' => $token['user_id']));
        $order = $stmt->fetch();

        if (!$order) {
            json_response(array('success' => false, 'error' => 'Sipariş bulunamadı.'), 404);
        }

        json_response(array(
            'success' => true,
            'data' => $this->transformOrderRow($order),
        ));
    }

    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(array('success' => false, 'error' => 'Yalnızca POST isteklerine izin verilir.'), 405);
        }

        $token = authenticate_token();
        require_scope($token, 'orders');

        $pdo = Database::connection();

        $payload = read_json_body();
        $orderReference = isset($payload['order_id']) ? trim((string) $payload['order_id']) : '';
        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array();
        $customer = isset($payload['customer']) && is_array($payload['customer']) ? $payload['customer'] : array();
        $currency = isset($payload['currency']) ? $payload['currency'] : 'USD';
        $webhookOverride = isset($payload['webhook_override']) ? trim((string) $payload['webhook_override']) : '';
        if ($webhookOverride !== '' && !filter_var($webhookOverride, FILTER_VALIDATE_URL)) {
            json_response(array('success' => false, 'error' => "Geçerli bir webhook_override URL'si belirtiniz."), 422);
        }

        if ($orderReference === '') {
            json_response(array('success' => false, 'error' => 'order_id alanı zorunludur.'), 422);
        }

        if (!$items) {
            json_response(array('success' => false, 'error' => 'items alanı boş olamaz.'), 422);
        }

        $normalizedItems = array();
        foreach ($items as $item) {
            $sku = isset($item['sku']) ? trim((string) $item['sku']) : '';
            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;
            $note = isset($item['note']) ? trim((string) $item['note']) : '';

            if ($sku === '') {
                json_response(array('success' => false, 'error' => 'Her sipariş satırı için sku alanı zorunludur.'), 422);
            }

            if ($quantity <= 0) {
                json_response(array('success' => false, 'error' => 'Sipariş satırlarının miktarı en az 1 olmalıdır.'), 422);
            }

            $normalizedItems[] = array(
                'sku' => $sku,
                'quantity' => $quantity,
                'note' => $note,
            );
        }

        try {
            $pdo->beginTransaction();

            $userStmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $userStmt->execute(array('id' => $token['user_id']));
            $userRow = $userStmt->fetch();

            if (!$userRow) {
                $pdo->rollBack();
                json_response(array('success' => false, 'error' => 'Bayi kaydı bulunamadı.'), 404);
            }

            $productLookup = $pdo->prepare('SELECT id, name, price, sku, provider_code FROM products WHERE sku = :sku AND status = :status LIMIT 1');
            $stockCheckStmt = $pdo->prepare('SELECT COUNT(*) FROM product_stock_items WHERE product_id = :product_id AND status = "available" FOR UPDATE');
            $orderIds = array();
            $totalCost = 0.0;
            $lineDetails = array();

            foreach ($normalizedItems as $line) {
                $productLookup->execute(array('sku' => $line['sku'], 'status' => 'active'));
                $product = $productLookup->fetch();

                if (!$product) {
                    $pdo->rollBack();
                    json_response(array('success' => false, 'error' => 'SKU ' . $line['sku'] . ' ürün kataloğunda bulunamadı.'), 404);
                }

                $providerCode = isset($product['provider_code']) ? strtolower((string) $product['provider_code']) : '';
                $requiresStock = ($providerCode === '' || $providerCode === 'stock' || $providerCode === 'panel');
                if ($requiresStock) {
                    $stockCheckStmt->execute(array('product_id' => (int) $product['id']));
                    $availableStock = (int) $stockCheckStmt->fetchColumn();
                    if ($availableStock < (int) $line['quantity']) {
                        $pdo->rollBack();
                        json_response(array('success' => false, 'error' => 'SKU ' . $line['sku'] . ' için yeterli stok bulunmuyor.'), 422);
                    }
                }

                $lineTotal = (float) $product['price'] * (int) $line['quantity'];
                $totalCost += $lineTotal;
                $lineDetails[] = array(
                    'product' => $product,
                    'line' => $line,
                    'total' => $lineTotal,
                );
            }

            $currentBalance = isset($userRow['balance']) ? (float) $userRow['balance'] : 0.0;
            if ($totalCost > $currentBalance) {
                $pdo->rollBack();
                json_response(array('success' => false, 'error' => 'Bakiyeniz bu siparişi karşılamak için yetersiz.'), 422);
            }

            $pdo->prepare('UPDATE users SET balance = balance - :amount WHERE id = :id')->execute(array(
                'amount' => $totalCost,
                'id' => $token['user_id'],
            ));

            $orderInsert = $pdo->prepare('INSERT INTO product_orders (product_id, user_id, api_token_id, quantity, note, price, status, source, external_reference, external_metadata, created_at) VALUES (:product_id, :user_id, :api_token_id, :quantity, :note, :price, :status, :source, :external_reference, :external_metadata, NOW())');
            $transactionInsert = $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())');

            foreach ($lineDetails as $detail) {
                $product = $detail['product'];
                $line = $detail['line'];
                $lineTotal = $detail['total'];

                $metadata = array(
                    'external_order' => array(
                        'id' => $orderReference,
                        'currency' => $currency,
                        'customer' => $customer,
                    ),
                    'line_item' => array(
                        'sku' => $line['sku'],
                        'quantity' => $line['quantity'],
                        'note' => $line['note'],
                    ),
                );

                if ($webhookOverride !== '') {
                    $metadata['webhook_override'] = $webhookOverride;
                }

                $orderInsert->execute(array(
                    'product_id' => (int) $product['id'],
                    'user_id' => $token['user_id'],
                    'api_token_id' => isset($token['id']) ? (int) $token['id'] : null,
                    'quantity' => (int) $line['quantity'],
                    'note' => $line['note'] !== '' ? $line['note'] : null,
                    'price' => $lineTotal,
                    'status' => 'processing',
                    'source' => 'api',
                    'external_reference' => $orderReference,
                    'external_metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ));

                $orderId = (int) $pdo->lastInsertId();
                $orderIds[] = $orderId;

                $transactionInsert->execute(array(
                    'user_id' => $token['user_id'],
                    'amount' => $lineTotal,
                    'type' => 'debit',
                    'description' => 'API siparişi #' . $orderReference . ' - ' . $product['name'] . ' x ' . (int) $line['quantity'],
                ));
            }

            $pdo->commit();

            if ($orderIds) {
                ProviderDispatchService::dispatchProductOrders($orderIds);
            }

            $remaining = $currentBalance - $totalCost;

            json_response(array(
                'success' => true,
                'data' => array(
                    'orders' => $orderIds,
                    'remaining_balance' => round($remaining, 2),
                ),
            ), 201);
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_response(array('success' => false, 'error' => 'Sipariş oluşturulamadı: ' . $exception->getMessage()), 500);
        }
    }

    /**
     * @param array<string,mixed> $order
     * @return array<string,mixed>
     */
    private function transformOrderRow($order): array
    {
        return array(
            'id' => (int) $order['id'],
            'product_id' => (int) $order['product_id'],
            'product_name' => $order['product_name'],
            'product_sku' => isset($order['product_sku']) ? $order['product_sku'] : null,
            'quantity' => isset($order['quantity']) ? (int) $order['quantity'] : 1,
            'price' => (float) $order['price'],
            'status' => $order['status'],
            'note' => isset($order['note']) ? $order['note'] : null,
            'admin_note' => isset($order['admin_note']) ? $order['admin_note'] : null,
            'external_reference' => isset($order['external_reference']) ? $order['external_reference'] : null,
            'source' => isset($order['source']) ? $order['source'] : null,
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at'],
        );
    }
}
