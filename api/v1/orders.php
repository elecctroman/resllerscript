<?php
require __DIR__ . '/../bootstrap.php';

$token = authenticate_token();
$pdo = App\Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = read_json_body();
    $orderReference = isset($payload['order_id']) ? trim($payload['order_id']) : '';
    $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array();
    $customer = isset($payload['customer']) && is_array($payload['customer']) ? $payload['customer'] : array();
    $currency = isset($payload['currency']) ? $payload['currency'] : 'USD';

    if ($orderReference === '') {
        json_response(array('success' => false, 'error' => 'order_id alanı zorunludur.'), 422);
    }

    if (!$items) {
        json_response(array('success' => false, 'error' => 'items alanı boş olamaz.'), 422);
    }

    $normalizedItems = array();
    foreach ($items as $index => $item) {
        $sku = isset($item['sku']) ? trim($item['sku']) : '';
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        $note = isset($item['note']) ? trim($item['note']) : '';

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

        $productLookup = $pdo->prepare('SELECT id, name, price, sku FROM products WHERE sku = :sku AND status = :status LIMIT 1');
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

            $lineTotal = (float)$product['price'] * (int)$line['quantity'];
            $totalCost += $lineTotal;
            $lineDetails[] = array(
                'product' => $product,
                'line' => $line,
                'total' => $lineTotal,
            );
        }

        $currentBalance = isset($userRow['balance']) ? (float)$userRow['balance'] : 0.0;
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
                'woocommerce_order' => array(
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

            $orderInsert->execute(array(
                'product_id' => (int)$product['id'],
                'user_id' => $token['user_id'],
                'api_token_id' => isset($token['id']) ? (int)$token['id'] : null,
                'quantity' => (int)$line['quantity'],
                'note' => $line['note'] !== '' ? $line['note'] : null,
                'price' => $lineTotal,
                'status' => 'processing',
                'source' => 'woocommerce',
                'external_reference' => $orderReference,
                'external_metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ));

            $orderId = (int)$pdo->lastInsertId();
            $orderIds[] = $orderId;

            $transactionInsert->execute(array(
                'user_id' => $token['user_id'],
                'amount' => $lineTotal,
                'type' => 'debit',
                'description' => 'WooCommerce siparişi #' . $orderReference . ' - ' . $product['name'] . ' x ' . (int)$line['quantity'],
            ));
        }

        $pdo->commit();

        $remaining = $currentBalance - $totalCost;

        json_response(array(
            'success' => true,
            'data' => array(
                'orders' => $orderIds,
                'remaining_balance' => round($remaining, 2),
            ),
        ), 201);
    } catch (\PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_response(array('success' => false, 'error' => 'Sipariş oluşturulamadı: ' . $exception->getMessage()), 500);
    }
} else {
    $externalReference = isset($_GET['external_reference']) ? trim($_GET['external_reference']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $since = isset($_GET['since']) ? trim($_GET['since']) : '';

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

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        $responseOrders = array();
        foreach ($orders as $order) {
            $responseOrders[] = array(
                'id' => (int)$order['id'],
                'product_id' => (int)$order['product_id'],
                'product_name' => $order['product_name'],
                'product_sku' => isset($order['product_sku']) ? $order['product_sku'] : null,
                'quantity' => isset($order['quantity']) ? (int)$order['quantity'] : 1,
                'price' => (float)$order['price'],
                'status' => $order['status'],
                'note' => isset($order['note']) ? $order['note'] : null,
                'admin_note' => isset($order['admin_note']) ? $order['admin_note'] : null,
                'external_reference' => isset($order['external_reference']) ? $order['external_reference'] : null,
                'source' => isset($order['source']) ? $order['source'] : null,
                'created_at' => $order['created_at'],
                'updated_at' => $order['updated_at'],
            );
        }

        json_response(array(
            'success' => true,
            'data' => array(
                'orders' => $responseOrders,
            ),
        ));
    } catch (\PDOException $exception) {
        json_response(array('success' => false, 'error' => 'Siparişler getirilemedi: ' . $exception->getMessage()), 500);
    }
}
