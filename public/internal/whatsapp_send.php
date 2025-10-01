<?php

declare(strict_types=1);

use App\Container;
use App\Environment;
use App\Services\NotificationService;

require __DIR__ . '/../../app/bootstrap.php';

$logger = Container::logger('internal-api');

$apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
$apiKeyQuery = $_GET['api_key'] ?? '';
$providedKey = $apiKeyHeader !== '' ? $apiKeyHeader : $apiKeyQuery;

$envKey = Environment::get('APP_GATEWAY_KEY');

if ($providedKey === '' && $envKey === null) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Gateway API key is not configured.']);
    exit;
}

if (!isValidApiKey($providedKey, $envKey)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input = [];
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw ?: '[]', true);
    if (!is_array($input)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }
} else {
    $input = $_POST;
}

$to = $input['to'] ?? '';
$message = $input['message'] ?? '';
$event = $input['event'] ?? 'manual';
$metadata = $input['metadata'] ?? [];

if (!is_string($to) || !preg_match('/^\+?[1-9]\d{7,14}$/', $to)) {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Phone number must be in E.164 format']);
    exit;
}

if (!is_string($message) || trim($message) === '') {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Message is required']);
    exit;
}

$redis = Container::redis();
$rateLimit = (int) (Environment::get('RATE_LIMIT_PER_MINUTE', '60') ?? 60);
$rateKey = 'ratelimit:' . preg_replace('/[^0-9]/', '', $to);
$current = (int) $redis->incr($rateKey);
if ($current === 1) {
    $redis->expire($rateKey, 60);
}

if ($current > $rateLimit) {
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Rate limit exceeded']);
    $logger->warning('Rate limit exceeded', ['phone' => $to, 'count' => $current]);
    exit;
}

$pdo = Container::db();
$service = new NotificationService($pdo, $redis, $logger);

try {
    if (!is_array($metadata)) {
        $metadata = [];
    }
    $metadata['to'] = $to;
    $notificationId = $service->queueMessage(null, $to, $event, $message, $metadata);
} catch (\Throwable $exception) {
    http_response_code(500);
    header('Content-Type: application/json');
    $logger->error('Failed to queue notification', ['error' => $exception->getMessage()]);
    echo json_encode(['error' => 'Failed to queue notification']);
    exit;
}

http_response_code(202);
header('Content-Type: application/json');

echo json_encode([
    'notification_id' => $notificationId,
    'status' => 'queued',
]);

function isValidApiKey(string $providedKey, ?string $envKey): bool
{
    if ($envKey !== null && hash_equals($envKey, $providedKey)) {
        return true;
    }

    if ($providedKey === '') {
        return false;
    }

    $pdo = Container::db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM api_keys WHERE api_key = :key');
    $stmt->execute([':key' => $providedKey]);
    return (int) $stmt->fetchColumn() > 0;
}
