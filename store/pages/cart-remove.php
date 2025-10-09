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

$result = store_cart_remove($productId);
if ($result['status'] !== 'success') {
    store_cart_flash('error', $result['message']);
}

if (store_request_wants_json()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'status' => $result['status'],
        'message' => $result['message'],
        'cart_count' => store_cart_count(),
        'cart_total' => store_cart_total(),
    ));
    return;
}

Helpers::redirect(store_url('cart'));
