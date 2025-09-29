<?php
require __DIR__ . '/bootstrap.php';

use App\Helpers;
use App\Settings;

if (!function_exists('smm_currency')) {
    function smm_currency()
    {
        static $currency = null;

        if ($currency !== null) {
            return $currency;
        }

        $currency = 'USD';

        try {
            $configured = Settings::get('smm_currency', null);
            if ($configured) {
                $candidate = strtoupper(trim((string)$configured));
                if (preg_match('/^[A-Z]{3}$/', $candidate)) {
                    $currency = $candidate;
                }
            }
        } catch (\Throwable $exception) {
            // Settings tablosu mevcut değilse varsayılan para birimini kullanmaya devam ediyoruz.
        }

        return $currency;
    }
}

if (!function_exists('smm_min_quantity')) {
    function smm_min_quantity($product)
    {
        if (isset($product['min_quantity']) && (int)$product['min_quantity'] > 0) {
            return (int)$product['min_quantity'];
        }

        return 1;
    }
}

if (!function_exists('smm_max_quantity')) {
    function smm_max_quantity($product, $min)
    {
        if (isset($product['max_quantity']) && (int)$product['max_quantity'] >= $min) {
            return (int)$product['max_quantity'];
        }

        if (isset($product['max_quantity']) && (int)$product['max_quantity'] > 0) {
            return $min;
        }

        return max($min, 10000);
    }
}

if (!function_exists('smm_service_type')) {
    function smm_service_type($product)
    {
        $value = '';
        if (isset($product['service_type'])) {
            $value = strtolower((string)$product['service_type']);
        }

        $map = array(
            'default' => 'Default',
            'package' => 'Package',
            'dripfeed' => 'Drip-feed',
            'subscriptions' => 'Subscriptions',
            'custom_comments' => 'Custom Comments',
            'mentions' => 'Mentions',
            'comment_likes' => 'Comment Likes',
        );

        if ($value !== '' && isset($map[$value])) {
            return $map[$value];
        }

        if ($value !== '') {
            return ucfirst($value);
        }

        return 'Default';
    }
}

if (!function_exists('smm_description')) {
    function smm_description($product)
    {
        $description = isset($product['description']) ? trim((string)$product['description']) : '';

        if ($description === '') {
            $description = Helpers::defaultProductDescription();
        }

        return $description;
    }
}

if (!function_exists('smm_rate')) {
    function smm_rate($product)
    {
        $price = isset($product['price']) ? (float)$product['price'] : 0.0;
        return number_format($price, 4, '.', '');
    }
}

if (!function_exists('smm_charge')) {
    function smm_charge($price, $quantity)
    {
        return number_format(((float)$price) * ((int)$quantity), 4, '.', '');
    }
}

if (!function_exists('smm_status_label')) {
    function smm_status_label($status)
    {
        $status = strtolower((string)$status);

        $map = array(
            'pending' => 'Pending',
            'new' => 'Pending',
            'processing' => 'Processing',
            'in progress' => 'Processing',
            'completed' => 'Completed',
            'complete' => 'Completed',
            'done' => 'Completed',
            'cancelled' => 'Canceled',
            'canceled' => 'Canceled',
            'rejected' => 'Canceled',
            'refunded' => 'Refunded',
            'partial' => 'Partial',
        );

        if (isset($map[$status])) {
            return $map[$status];
        }

        return 'Processing';
    }
}

if (!function_exists('smm_service_payload')) {
    function smm_service_payload($product, $categories)
    {
        $categoryId = isset($product['category_id']) ? (int)$product['category_id'] : null;
        $categoryName = isset($categories[$categoryId]) ? $categories[$categoryId] : (isset($product['category_name']) ? $product['category_name'] : 'General');
        $min = smm_min_quantity($product);
        $max = smm_max_quantity($product, $min);
        $description = smm_description($product);

        return array(
            'service' => (int)$product['id'],
            'name' => isset($product['name']) ? $product['name'] : 'Service #' . (int)$product['id'],
            'type' => smm_service_type($product),
            'category' => $categoryName,
            'category_id' => $categoryId,
            'rate' => smm_rate($product),
            'min' => $min,
            'max' => $max,
            'desc' => $description,
            'description' => $description,
            'currency' => smm_currency(),
            'dripfeed' => '0',
            'refill' => '0',
            'cancel' => '0',
        );
    }
}

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
    json_response(array('error' => 'API key is required.'), 401);
}

$token = App\ApiToken::findActiveToken($key);
if (!$token) {
    json_response(array('error' => 'Invalid API key.'), 401);
}

if ($action === '') {
    json_response(array('error' => 'Action parameter is required.'), 400);
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
                $services[] = smm_service_payload($product, $categories);
            }

            json_response($services);
        } catch (\PDOException $exception) {
            json_response(array('error' => 'Unable to load services: ' . $exception->getMessage()), 500);
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
            json_response(array('error' => 'Service parameter is invalid.'), 422);
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
                json_response(array('error' => 'Service not found.'), 404);
            }

            $minQuantity = smm_min_quantity($product);
            $maxQuantity = smm_max_quantity($product, $minQuantity);

            if ($quantity < $minQuantity || $quantity > $maxQuantity) {
                $pdo->rollBack();
                json_response(array('error' => sprintf('You can order between %d and %d units for this service.', $minQuantity, $maxQuantity)), 422);
            }

            $userStmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $userStmt->execute(array('id' => $token['user_id']));
            $userRow = $userStmt->fetch();

            if (!$userRow) {
                $pdo->rollBack();
                json_response(array('error' => 'Reseller account could not be found.'), 404);
            }

            $unitPrice = isset($product['price']) ? (float)$product['price'] : 0.0;
            $totalCost = (float)$unitPrice * (int)$quantity;

            $currentBalance = isset($userRow['balance']) ? (float)$userRow['balance'] : 0.0;
            if ($totalCost > $currentBalance) {
                $pdo->rollBack();
                json_response(array('error' => 'Insufficient balance.'), 422);
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
                'description' => 'SMM API order: ' . $product['name'],
            ));

            $pdo->commit();

            $remaining = $currentBalance - $totalCost;

            json_response(array(
                'order' => $orderId,
                'charge' => smm_charge($unitPrice, $quantity),
                'balance' => number_format($remaining, 4, '.', ''),
                'currency' => smm_currency(),
            ));
        } catch (\PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            json_response(array('error' => 'Order could not be created: ' . $exception->getMessage()), 500);
        }
        break;

    case 'status':
        $orderId = isset($request['order']) ? (int)$request['order'] : 0;
        if ($orderId <= 0) {
            json_response(array('error' => 'Order parameter is invalid.'), 422);
        }

        try {
            $stmt = $pdo->prepare('SELECT po.*, pr.name AS product_name FROM product_orders po INNER JOIN products pr ON pr.id = po.product_id WHERE po.id = :id AND po.user_id = :user_id LIMIT 1');
            $stmt->execute(array('id' => $orderId, 'user_id' => $token['user_id']));
            $order = $stmt->fetch();

            if (!$order) {
                json_response(array('error' => 'Order not found.'), 404);
            }

            $totalCharge = ((float)$order['price']) * ((int)$order['quantity']);
            $metadata = array();
            if (!empty($order['external_metadata'])) {
                $decoded = json_decode($order['external_metadata'], true);
                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }

            $status = isset($order['status']) ? $order['status'] : 'pending';

            json_response(array(
                'order' => (int)$order['id'],
                'status' => smm_status_label($status),
                'charge' => number_format($totalCharge, 4, '.', ''),
                'quantity' => isset($order['quantity']) ? (int)$order['quantity'] : 0,
                'link' => isset($metadata['smm']['link']) ? $metadata['smm']['link'] : (isset($order['note']) ? $order['note'] : null),
                'remains' => isset($order['remains']) ? (int)$order['remains'] : 0,
                'start_count' => isset($metadata['smm']['start_count']) ? (int)$metadata['smm']['start_count'] : 0,
                'currency' => smm_currency(),
                'admin_note' => isset($order['admin_note']) ? $order['admin_note'] : null,
                'created_at' => isset($order['created_at']) ? $order['created_at'] : null,
                'updated_at' => isset($order['updated_at']) ? $order['updated_at'] : null,
            ));
        } catch (\PDOException $exception) {
            json_response(array('error' => 'Unable to load order status: ' . $exception->getMessage()), 500);
        }
        break;

    case 'status_mult':
    case 'multistatus':
    case 'status_multi':
    case 'multi_status':
        $ids = array();
        if (isset($request['orders'])) {
            if (is_array($request['orders'])) {
                $ids = $request['orders'];
            } else {
                $ids = explode(',', (string)$request['orders']);
            }
        }

        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (empty($ids)) {
            json_response(array('error' => 'Orders parameter is required.'), 422);
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = $ids;
            $params[] = $token['user_id'];

            $stmt = $pdo->prepare('SELECT po.*, pr.name AS product_name FROM product_orders po INNER JOIN products pr ON pr.id = po.product_id WHERE po.id IN (' . $placeholders . ') AND po.user_id = ? ORDER BY po.id ASC');
            $stmt->execute($params);

            $result = array();
            while ($order = $stmt->fetch()) {
                $metadata = array();
                if (!empty($order['external_metadata'])) {
                    $decoded = json_decode($order['external_metadata'], true);
                    if (is_array($decoded)) {
                        $metadata = $decoded;
                    }
                }

                $totalCharge = ((float)$order['price']) * ((int)$order['quantity']);

                $result[$order['id']] = array(
                    'status' => smm_status_label(isset($order['status']) ? $order['status'] : 'pending'),
                    'charge' => number_format($totalCharge, 4, '.', ''),
                    'quantity' => isset($order['quantity']) ? (int)$order['quantity'] : 0,
                    'link' => isset($metadata['smm']['link']) ? $metadata['smm']['link'] : (isset($order['note']) ? $order['note'] : null),
                    'remains' => isset($order['remains']) ? (int)$order['remains'] : 0,
                    'start_count' => isset($metadata['smm']['start_count']) ? (int)$metadata['smm']['start_count'] : 0,
                    'currency' => smm_currency(),
                );
            }

            json_response($result);
        } catch (\PDOException $exception) {
            json_response(array('error' => 'Unable to load orders: ' . $exception->getMessage()), 500);
        }
        break;

    case 'balance':
        try {
            $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(array('id' => $token['user_id']));
            $balance = $stmt->fetchColumn();

            json_response(array(
                'balance' => $balance !== false ? number_format((float)$balance, 4, '.', '') : '0.0000',
                'currency' => smm_currency(),
            ));
        } catch (\PDOException $exception) {
            json_response(array('error' => 'Unable to fetch balance: ' . $exception->getMessage()), 500);
        }
        break;

    case 'refill':
    case 'refill_status':
    case 'refillstatus':
    case 'multirefill':
    case 'refill_multi':
    case 'multirefillstatus':
    case 'refill_status_multi':
    case 'cancel':
        json_response(array('error' => 'This feature is not available for your account.'), 400);
        break;

    default:
        json_response(array('error' => 'Unsupported action: ' . $action), 400);
}
