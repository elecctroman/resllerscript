<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Lotus\Client;
use Lotus\Exceptions\ApiError;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$client = new Client([
    // 'apiKey' => 'your-api-key',
    // 'baseUrl' => 'https://partner.lotuslisans.com.tr',
    // 'useQueryApiKey' => false,
]);

try {
    $me = $client->getUser();
    $credit = $me['data']['credit'] ?? $me['credit'] ?? null;
    if ($credit !== null) {
        echo 'Credit: ' . $credit . PHP_EOL;
    }

    $products = $client->listProducts();
    $firstAvailable = null;
    foreach (($products['data'] ?? []) as $product) {
        if (($product['available'] ?? false) === true) {
            $firstAvailable = $product;
            break;
        }
    }

    if ($firstAvailable) {
        $order = $client->createOrder([
            'product_id' => (int) ($firstAvailable['id'] ?? 0),
            'note' => 'müşteri notu',
        ]);

        print_r($order);

        $orderId = $order['data']['order_id'] ?? $order['data']['id'] ?? null;
        if ($orderId) {
            $detail = $client->getOrderById((int) $orderId);
            print_r($detail);
        }
    }

    $orders = $client->listOrders(['status' => 'completed']);
    echo 'Completed orders: ' . count($orders['data'] ?? []) . PHP_EOL;
} catch (ApiError $e) {
    fwrite(STDERR, sprintf("API Error (%d): %s\n", $e->httpStatus, $e->getMessage()));
    if ($e->requestId) {
        fwrite(STDERR, sprintf("X-Request-Id: %s\n", $e->requestId));
    }
    if (!empty($e->details)) {
        fwrite(STDERR, "Details: " . json_encode($e->details, JSON_PRETTY_PRINT) . PHP_EOL);
    }
}
