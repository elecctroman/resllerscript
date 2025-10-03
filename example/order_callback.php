<?php declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\{Logger, LotusClient, LotusOrderRepository, LotusOrderService};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Yalnızca POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$localOrderId = isset($_POST['local_order_id']) ? (int) $_POST['local_order_id'] : 0;
$lotusProductId = isset($_POST['lotus_product_id']) ? (int) $_POST['lotus_product_id'] : 0;
$note = $_POST['note'] ?? null;

if ($localOrderId <= 0 || $lotusProductId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'local_order_id ve lotus_product_id zorunlu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$apiKey = envStr('LOTUS_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'LOTUS_API_KEY boş.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$baseUrl = envStr('LOTUS_BASE_URL', 'https://partner.lotuslisans.com.tr');
$timeoutMs = (int) envStr('LOTUS_TIMEOUT_MS', '20000');
$connectTimeoutMs = (int) envStr('LOTUS_CONNECT_TIMEOUT_MS', '10000');
$dbPath = __DIR__ . '/..' . envStr('LOTUS_DB_PATH', '/storage/lotus.sqlite');
$logPath = __DIR__ . '/..' . envStr('LOTUS_LOG_PATH', '/storage/lotus.log');

$logger = new Logger($logPath);
$client = new LotusClient($baseUrl, $apiKey, $timeoutMs, $connectTimeoutMs, $logger);
$repo = new LotusOrderRepository($dbPath, $logger);
$service = new LotusOrderService($client, $repo, $logger);

header('Content-Type: application/json');

try {
    $row = $service->placeExternalOrder($localOrderId, $lotusProductId, is_string($note) ? $note : null);

    if (($row['status'] ?? '') === 'completed') {
        echo json_encode(['ok' => true, 'status' => 'completed', 'content' => $row['content'] ?? null], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'ok' => true,
            'status' => $row['status'] ?? 'pending',
            'message' => 'Sipariş hazır olduğunda teslim edilecek.'
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
