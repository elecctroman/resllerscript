<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Helpers;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::redirect(store_url('cart'));
    return;
}

if (!Helpers::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
    $message = 'Oturum doğrulaması başarısız. Lütfen tekrar deneyin.';
    store_cart_flash('error', $message);
    if (store_request_wants_json()) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'status' => 'error',
            'message' => $message,
        ));
        return;
    }

    Helpers::redirect(store_url('cart'));
    return;
}

$productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 1;

$result = store_cart_add($productId, $quantity);
if ($result['status'] !== 'success') {
    store_cart_flash('error', $result['message']);
}

if (store_request_wants_json()) {
    $itemPayload = null;
    if (isset($result['item']) && is_array($result['item'])) {
        $item = $result['item'];
        $itemPayload = array(
            'name' => isset($item['name']) ? (string) $item['name'] : 'Ürün',
            'url' => isset($item['url']) ? (string) $item['url'] : store_url('cart'),
            'image' => store_media_url(isset($item['image']) ? (string) $item['image'] : '', theme_asset('img/placeholder-16x9.svg')),
            'price' => money_format_try(isset($item['price']) ? (float) $item['price'] : 0.0, isset($item['currency']) ? (string) $item['currency'] : null),
            'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : 1,
            'quantity_label' => 'Adet: ' . (isset($item['quantity']) ? (int) $item['quantity'] : 1),
        );
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'status' => $result['status'],
        'message' => $result['message'],
        'cart_count' => store_cart_count(),
        'cart_total' => store_cart_total(),
        'cart_total_formatted' => money_format_try(store_cart_total()),
        'item' => $itemPayload,
    ));
    return;
}

Helpers::redirect(store_url('cart'));
