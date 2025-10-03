<?php
require __DIR__ . '/../bootstrap.php';

use App\Services\PremiumPurchaseService;
use App\Settings;

default_headers();

$key = '';
if (defined('PREMIUM_MODULE_API_KEY') && PREMIUM_MODULE_API_KEY !== '') {
    $key = PREMIUM_MODULE_API_KEY;
}

if ($key === '') {
    $stored = Settings::get('premium_module_api_key');
    if ($stored) {
        $key = $stored;
    }
}

$provided = '';
if (isset($_SERVER['HTTP_X_API_KEY'])) {
    $provided = (string) $_SERVER['HTTP_X_API_KEY'];
} elseif (isset($_GET['api_key'])) {
    $provided = (string) $_GET['api_key'];
}

if ($key === '' || $provided === '' || !hash_equals($key, $provided)) {
    http_response_code(401);
    echo json_encode(array('status' => 'error', 'message' => 'API anahtarı doğrulanamadı.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('status' => 'error', 'message' => 'Yalnızca POST istekleri desteklenir.'));
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(array('status' => 'error', 'message' => 'Geçersiz JSON yükü.'));
    exit;
}

$purchaseId = isset($payload['purchase_id']) ? (int) $payload['purchase_id'] : 0;
$status = isset($payload['status']) ? (string) $payload['status'] : '';
$license = isset($payload['license_key']) ? (string) $payload['license_key'] : null;

if ($purchaseId <= 0 || $status === '') {
    http_response_code(400);
    echo json_encode(array('status' => 'error', 'message' => 'purchase_id ve status alanları zorunludur.'));
    exit;
}

$service = new PremiumPurchaseService();

try {
    if (strtolower($status) === 'paid') {
        $service->markAsPaid($purchaseId, $license);
        echo json_encode(array('status' => 'ok'));
        exit;
    }

    http_response_code(400);
    echo json_encode(array('status' => 'error', 'message' => 'Desteklenmeyen durum değeri.'));
    exit;
} catch (RuntimeException $exception) {
    http_response_code(404);
    echo json_encode(array('status' => 'error', 'message' => $exception->getMessage()));
    exit;
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode(array('status' => 'error', 'message' => 'Beklenmeyen bir hata oluştu.'));
    exit;
}

function default_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
}
