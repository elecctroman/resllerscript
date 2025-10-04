<?php
require __DIR__ . '/includes/customer_api.php';

use App\Customers\WalletService;

header('Content-Type: application/json');

customerApiRequireScope('wallet');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    customerApiLog($customer['id'], '/api/wallet.php', 405);
    echo json_encode(array('success' => false, 'message' => 'Method Not Allowed'));
    exit;
}

$history = WalletService::history((int)$customer['id'], 50);
customerApiLog($customer['id'], '/api/wallet.php', 200);

echo json_encode(array(
    'success' => true,
    'balance' => (float)$customer['balance'],
    'currency' => $customer['currency'] ?? 'TRY',
    'history' => $history,
));
