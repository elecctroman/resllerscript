<?php
require __DIR__ . '/../bootstrap.php';

$token = authenticate_token();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_scope($token, 'read');
} else {
    require_scope($token, 'orders');
}
$pdo = App\Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$payload = read_json_body();
$orderReference = isset($payload['order_id']) ? trim((string)$payload['order_id']) : '';
$customer = isset($payload['customer']) && is_array($payload['customer']) ? $payload['customer'] : array();
$currency = isset($payload['currency']) ? (string)$payload['currency'] : 'TRY';

if ($orderReference === '') {
    json_response(array('success' => false, 'error' => 'order_id alanı zorunludur.'), 422);
}

$normalizedItems = array();
$items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array();
if ($items) {
    foreach ($items as $item) {
        $sku = isset($item['sku']) ? trim((string)$item['sku']) : '';
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        $note = isset($item['note']) ? trim((string)$item['note']) : '';

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
} elseif (!empty($payload['product_id'])) {
    $productId = (int)$payload['product_id'];
    if ($productId <= 0) {
        json_response(array('success' => false, 'error' => 'product_id değeri geçersiz.'), 422);
    }

    $note = isset($payload['note']) ? trim((string)$payload['note']) : '';
    $normalizedItems[] = array(
        'product_id' => $productId,
        'quantity' => 1,
        'note' => $note,
    );
} else {
    json_response(array('success' => false, 'error' => 'items veya product_id alanlarından biri zorunludur.'), 422);
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

    $productLookupBySku = $pdo->prepare('SELECT id, name, price, sku, provider_code FROM products WHERE sku = :sku AND status = :status LIMIT 1');
    $productLookupById = $pdo->prepare('SELECT id, name, price, sku, provider_code FROM products WHERE id = :id AND status = :status LIMIT 1');
    $stockCheckStmt = $pdo->prepare('SELECT COUNT(*) FROM product_stock_items WHERE product_id = :product_id AND status = "available" FOR UPDATE');
    $orderIds = array();
    $totalCost = 0.0;
    $lineDetails = array();

    foreach ($normalizedItems as $line) {
        if (isset($line['product_id'])) {
            $productLookupById->execute(array('id' => (int)$line['product_id'], 'status' => 'active'));
            $product = $productLookupById->fetch();
            if (!$product) {
                $pdo->rollBack();
                json_response(array('success' => false, 'error' => 'İstenen ürün kataloğumuzda bulunamadı.'), 404);
            }
            $skuValue = $product['sku'] ?: 'product-' . (int)$product['id'];
        } else {
            $productLookupBySku->execute(array('sku' => $line['sku'], 'status' => 'active'));
            $product = $productLookupBySku->fetch();
            $skuValue = $line['sku'];
            if (!$product) {
                $pdo->rollBack();
                json_response(array('success' => false, 'error' => 'SKU ' . $line['sku'] . ' ürün kataloğunda bulunamadı.'), 404);
            }
        }

        $providerCode = isset($product['provider_code']) ? strtolower((string) $product['provider_code']) : '';
        $requiresStock = ($providerCode === '' || $providerCode === 'stock' || $providerCode === 'panel');
        if ($requiresStock) {
            $stockCheckStmt->execute(array('product_id' => (int) $product['id']));
            $availableStock = (int) $stockCheckStmt->fetchColumn();
            if ($availableStock < (int) $line['quantity']) {
                $pdo->rollBack();
                json_response(array('success' => false, 'error' => 'Ürün için yeterli stok bulunmuyor.'), 422);
            }
        }

        $lineTotal = (float)$product['price'] * (int)$line['quantity'];
        $totalCost += $lineTotal;
        $lineDetails[] = array(
            'product' => $product,
            'line' => $line,
            'sku' => $skuValue,
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

        $orderInsert = $pdo->prepare('INSERT INTO product_orders (product_id, user_id, api_token_id, quantity, note, price, total_amount, status, source, external_reference, external_metadata, created_at) VALUES (:product_id, :user_id, :api_token_id, :quantity, :note, :price, :total_amount, :status, :source, :external_reference, :external_metadata, NOW())');
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
                    'sku' => $detail['sku'],
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
                'total_amount' => $lineTotal,
                'status' => 'processing',
                'source' => 'api',
                'external_reference' => $orderReference,
                'external_metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ));

            $orderId = (int)$pdo->lastInsertId();
            $orderIds[] = $orderId;

            $transactionInsert->execute(array(
                'user_id' => $token['user_id'],
                'amount' => $lineTotal,
                'type' => 'debit',
                'description' => 'API siparişi #' . $orderReference . ' - ' . $product['name'] . ' x ' . (int)$line['quantity'],
            ));
        }

        $pdo->commit();

        if ($orderIds) {
            \App\Services\ProviderDispatchService::dispatchProductOrders($orderIds);
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $summaryStmt = $pdo->prepare('SELECT id, status, external_metadata, admin_note FROM product_orders WHERE id IN (' . $placeholders . ')');
        $summaryStmt->execute($orderIds);
        $summaries = array();
        while ($row = $summaryStmt->fetch()) {
            $content = '';
            if (!empty($row['external_metadata'])) {
                $meta = json_decode((string)$row['external_metadata'], true);
                if (is_array($meta) && !empty($meta['delivery_content'])) {
                    $content = (string)$meta['delivery_content'];
                } elseif (is_array($meta) && isset($meta['provider_response']['data']['content'])) {
                    $content = (string)$meta['provider_response']['data']['content'];
                }
            }
            if ($content === '' && !empty($row['admin_note'])) {
                $content = (string)$row['admin_note'];
            }

            $summaries[] = array(
                'order_id' => (int)$row['id'],
                'status' => $row['status'],
                'content' => $content,
            );
        }

        $remaining = $currentBalance - $totalCost;

        json_response(array(
            'success' => true,
            'data' => array(
                'orders' => $summaries,
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

    $orderId = 0;
    if (!empty($_GET['id'])) {
        $orderId = (int)$_GET['id'];
    } elseif (!empty($_SERVER['PATH_INFO']) && preg_match('#^/([0-9]+)$#', (string)$_SERVER['PATH_INFO'], $match)) {
        $orderId = (int)$match[1];
    }

    $query = 'SELECT po.*, pr.name AS product_name, pr.sku AS product_sku FROM product_orders po INNER JOIN products pr ON po.product_id = pr.id WHERE po.user_id = :user_id';
    $params = array('user_id' => $token['user_id']);

    if ($orderId > 0) {
        $query .= ' AND po.id = :order_id';
        $params['order_id'] = $orderId;
    }

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
            $content = '';
            if (!empty($order['external_metadata'])) {
                $meta = json_decode((string)$order['external_metadata'], true);
                if (is_array($meta) && !empty($meta['delivery_content'])) {
                    $content = (string)$meta['delivery_content'];
                } elseif (is_array($meta) && isset($meta['provider_response']['data']['content'])) {
                    $content = (string)$meta['provider_response']['data']['content'];
                }
            }
            if ($content === '' && !empty($order['admin_note'])) {
                $content = (string)$order['admin_note'];
            }

            $responseOrders[] = array(
                'id' => (int)$order['id'],
                'product_id' => (int)$order['product_id'],
                'product_title' => $order['product_name'],
                'product_sku' => isset($order['product_sku']) ? $order['product_sku'] : null,
                'quantity' => isset($order['quantity']) ? (int)$order['quantity'] : 1,
                'amount' => (float)$order['price'],
                'status' => $order['status'],
                'note' => isset($order['note']) ? $order['note'] : null,
                'external_reference' => isset($order['external_reference']) ? $order['external_reference'] : null,
                'source' => isset($order['source']) ? $order['source'] : null,
                'content' => $content,
                'created_at' => $order['created_at'],
                'updated_at' => $order['updated_at'],
            );
        }

        if ($orderId > 0) {
            if (!$responseOrders) {
                json_response(array('success' => false, 'error' => 'Sipariş bulunamadı.'), 404);
            }

            json_response(array(
                'success' => true,
                'data' => $responseOrders[0],
            ));
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
