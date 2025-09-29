<?php
require __DIR__ . '/bootstrap.php';

$request = array();

$method = strtoupper($_SERVER['REQUEST_METHOD']);
$contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower(trim($_SERVER['CONTENT_TYPE'])) : '';

if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
    if (strpos($contentType, 'application/json') !== false) {
        $request = read_json_body();
    } else {
        $request = $_POST;
    }
} else {
    $request = $_GET;
}

$key = isset($request['key']) ? trim((string)$request['key']) : '';
$action = isset($request['action']) ? strtolower(trim((string)$request['action'])) : '';

if ($key === '') {
    json_response(array('success' => false, 'error' => 'API anahtarı gönderilmelidir.'), 401);
}

$token = App\ApiToken::findActiveToken($key);
if (!$token) {
    json_response(array('success' => false, 'error' => 'API anahtarı doğrulanamadı.'), 401);
}

if ($action === '') {
    json_response(array('success' => false, 'error' => 'İşlem tipi (action) gönderilmelidir.'), 400);
}

$pdo = App\Database::connection();

switch ($action) {
    case 'services':
        try {
            $categoryStmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
            $categories = array();
            while ($row = $categoryStmt->fetch()) {
                $categories[(int)$row['id']] = $row['name'];
            }

            $productStmt = $pdo->prepare('SELECT pr.*, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id WHERE pr.status = :status ORDER BY cat.name ASC, pr.name ASC');
            $productStmt->execute(array('status' => 'active'));

            $services = array();
            while ($product = $productStmt->fetch()) {
                $minQuantity = isset($product['min_quantity']) ? (int)$product['min_quantity'] : 1;
                if ($minQuantity < 1) {
                    $minQuantity = 1;
                }

                $maxQuantity = isset($product['max_quantity']) ? (int)$product['max_quantity'] : $minQuantity;
                if ($maxQuantity < $minQuantity) {
                    $maxQuantity = $minQuantity;
                }

                $serviceType = isset($product['service_type']) && $product['service_type'] !== '' ? $product['service_type'] : 'default';
                $categoryName = isset($categories[(int)$product['category_id']]) ? $categories[(int)$product['category_id']] : $product['category_name'];

                $services[] = array(
                    'service' => (int)$product['id'],
                    'name' => $product['name'],
                    'type' => $serviceType,
                    'category' => $categoryName,
                    'rate' => number_format((float)$product['price'], 4, '.', ''),
                    'min' => $minQuantity,
                    'max' => $maxQuantity,
                    'currency' => 'USD',
                    'description' => isset($product['description']) ? $product['description'] : null,
                );
            }

            json_response(array(
                'success' => true,
                'services' => $services,
            ));
        } catch (\PDOException $exception) {
            json_response(array('success' => false, 'error' => 'Servis listesi yüklenemedi: ' . $exception->getMessage()), 500);
        }
        break;

    case 'add':
        $serviceId = isset($request['service']) ? (int)$request['service'] : 0;
        $quantity = isset($request['quantity']) ? (int)$request['quantity'] : 0;
        $link = isset($request['link']) ? trim((string)$request['link']) : '';
        $runs = isset($request['runs']) ? (int)$request['runs'] : null;
        $interval = isset($request['interval']) ? (int)$request['interval'] : null;
        $comments = isset($request['comments']) ? trim((string)$request['comments']) : null;
        $customData = isset($request['data']) ? $request['data'] : null;

        if ($serviceId <= 0) {
            json_response(array('success' => false, 'error' => 'Geçerli bir servis ID\'si (service) göndermelisiniz.'), 422);
        }

        if ($quantity <= 0) {
            $quantity = 1;
        }

        try {
            $pdo->beginTransaction();

            $productStmt = $pdo->prepare('SELECT pr.*, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id WHERE pr.id = :id AND pr.status = :status LIMIT 1 FOR UPDATE');
            $productStmt->execute(array('id' => $serviceId, 'status' => 'active'));
            $product = $productStmt->fetch();

            if (!$product) {
                $pdo->rollBack();
                json_response(array('success' => false, 'error' => 'Servis bulunamadı veya pasif durumda.'), 404);
            }

            $minQuantity = isset($product['min_quantity']) ? (int)$product['min_quantity'] : 1;
            if ($minQuantity < 1) {
                $minQuantity = 1;
            }
            $maxQuantity = isset($product['max_quantity']) ? (int)$product['max_quantity'] : $minQuantity;
            if ($maxQuantity < $minQuantity) {
                $maxQuantity = $minQuantity;
            }

            if ($quantity < $minQuantity || $quantity > $maxQuantity) {
                $pdo->rollBack();
                json_response(array('success' => false, 'error' => sprintf('Bu servis için minimum %d, maksimum %d adet sipariş verebilirsiniz.', $minQuantity, $maxQuantity)), 422);
            }

            $userStmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $userStmt->execute(array('id' => $token['user_id']));
            $userRow = $userStmt->fetch();

            if (!$userRow) {
                $pdo->rollBack();
                json_response(array('success' => false, 'error' => 'Bayi kaydı bulunamadı.'), 404);
            }

            $unitPrice = isset($product['price']) ? (float)$product['price'] : 0.0;
            $totalCost = $unitPrice * $quantity;

            $currentBalance = isset($userRow['balance']) ? (float)$userRow['balance'] : 0.0;
            if ($totalCost > $currentBalance) {
                $pdo->rollBack();
                json_response(array('success' => false, 'error' => 'Bakiyeniz siparişi karşılamak için yetersiz.'), 422);
            }

            $metadata = array(
                'smm' => array(
                    'link' => $link,
                    'quantity' => $quantity,
                    'runs' => $runs,
                    'interval' => $interval,
                    'comments' => $comments,
                    'custom_data' => $customData,
                ),
            );

            $orderStmt = $pdo->prepare('INSERT INTO product_orders (product_id, user_id, api_token_id, quantity, note, price, status, source, external_reference, external_metadata, created_at) VALUES (:product_id, :user_id, :api_token_id, :quantity, :note, :price, :status, :source, :external_reference, :external_metadata, NOW())');
            $orderStmt->execute(array(
                'product_id' => $product['id'],
                'user_id' => $token['user_id'],
                'api_token_id' => isset($token['id']) ? (int)$token['id'] : null,
                'quantity' => $quantity,
                'note' => $link !== '' ? $link : null,
                'price' => $unitPrice,
                'status' => 'pending',
                'source' => 'smm_api',
                'external_reference' => $link !== '' ? substr($link, 0, 190) : null,
                'external_metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ));

            $orderId = (int)$pdo->lastInsertId();

            $balanceStmt = $pdo->prepare('UPDATE users SET balance = balance - :amount WHERE id = :id');
            $balanceStmt->execute(array('amount' => $totalCost, 'id' => $token['user_id']));

            $transactionStmt = $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())');
            $transactionStmt->execute(array(
                'user_id' => $token['user_id'],
                'amount' => $totalCost,
                'type' => 'debit',
                'description' => 'SMM API siparişi: ' . $product['name'],
            ));

            $pdo->commit();

            $remaining = $currentBalance - $totalCost;

            json_response(array(
                'success' => true,
                'order' => $orderId,
                'charge' => round($totalCost, 2),
                'balance' => round($remaining, 2),
            ), 201);
        } catch (\PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            json_response(array('success' => false, 'error' => 'Sipariş oluşturulamadı: ' . $exception->getMessage()), 500);
        }
        break;

    case 'status':
        $orderId = isset($request['order']) ? (int)$request['order'] : 0;
        if ($orderId <= 0) {
            json_response(array('success' => false, 'error' => 'Geçerli bir sipariş numarası (order) göndermelisiniz.'), 422);
        }

        try {
            $stmt = $pdo->prepare('SELECT po.*, pr.name AS product_name FROM product_orders po INNER JOIN products pr ON pr.id = po.product_id WHERE po.id = :id AND po.user_id = :user_id LIMIT 1');
            $stmt->execute(array('id' => $orderId, 'user_id' => $token['user_id']));
            $order = $stmt->fetch();

            if (!$order) {
                json_response(array('success' => false, 'error' => 'Sipariş bulunamadı.'), 404);
            }

            $totalCharge = ((float)$order['price']) * ((int)$order['quantity']);
            $metadata = array();
            if (!empty($order['external_metadata'])) {
                $decoded = json_decode($order['external_metadata'], true);
                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }

            json_response(array(
                'success' => true,
                'order' => (int)$order['id'],
                'status' => $order['status'],
                'charge' => round($totalCharge, 2),
                'quantity' => isset($order['quantity']) ? (int)$order['quantity'] : 0,
                'link' => isset($metadata['smm']['link']) ? $metadata['smm']['link'] : (isset($order['note']) ? $order['note'] : null),
                'admin_note' => isset($order['admin_note']) ? $order['admin_note'] : null,
                'created_at' => isset($order['created_at']) ? $order['created_at'] : null,
                'updated_at' => isset($order['updated_at']) ? $order['updated_at'] : null,
            ));
        } catch (\PDOException $exception) {
            json_response(array('success' => false, 'error' => 'Sipariş durumu getirilemedi: ' . $exception->getMessage()), 500);
        }
        break;

    case 'balance':
        try {
            $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(array('id' => $token['user_id']));
            $balance = $stmt->fetchColumn();

            json_response(array(
                'success' => true,
                'balance' => $balance !== false ? round((float)$balance, 2) : 0.0,
                'currency' => 'USD',
            ));
        } catch (\PDOException $exception) {
            json_response(array('success' => false, 'error' => 'Bakiye bilgisi getirilemedi: ' . $exception->getMessage()), 500);
        }
        break;

    default:
        json_response(array('success' => false, 'error' => 'Desteklenmeyen işlem: ' . $action), 400);
}
