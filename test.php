<?php declare(strict_types=1);

/**
 * Quick API smoke test script.
 *
 * Configure the API base URL and key below, then run:
 *   php test.php
 * The script will attempt to hit the main reseller API endpoints and
 * persist the responses in storage/logs/api-test.log for later review.
 */

const API_BASE_URL = 'https://example.com/api/v1'; // <-- update
const API_KEY      = 'YOUR_API_KEY';               // <-- update

/**
 * Optional: set to a valid product ID to test order creation.
 * Leave as null to skip creating a test order.
 */
const TEST_PRODUCT_ID = null;
const TEST_ORDER_NOTE = 'API test order';

$logDirectory = __DIR__ . '/storage/logs';
$logFile = $logDirectory . '/api-test.log';

if (!is_dir($logDirectory)) {
    if (!mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
        fwrite(STDERR, "Log dizini oluşturulamadı: {$logDirectory}" . PHP_EOL);
        exit(1);
    }
}

if (API_BASE_URL === 'https://example.com/api/v1' || API_KEY === 'YOUR_API_KEY') {
    fwrite(STDERR, "Lütfen test.php dosyasındaki API_BASE_URL ve API_KEY değerlerini güncelleyin." . PHP_EOL);
    exit(1);
}

$tests = [
    'profile' => ['GET', '/user'],
    'products' => ['GET', '/products'],
    'orders_list' => ['GET', '/orders'],
];

if (TEST_PRODUCT_ID !== null) {
    $tests['create_order'] = ['POST', '/orders', ['product_id' => (int) TEST_PRODUCT_ID, 'note' => TEST_ORDER_NOTE]];
}

$timestamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);

$logHandle = fopen($logFile, 'ab');
if ($logHandle === false) {
    fwrite(STDERR, "Log dosyası açılamadı: {$logFile}" . PHP_EOL);
    exit(1);
}

fwrite($logHandle, str_repeat('=', 80) . PHP_EOL);
fwrite($logHandle, "API Test başlangıcı: {$timestamp}" . PHP_EOL);

foreach ($tests as $label => $definition) {
    [$method, $path, $payload] = [$definition[0], $definition[1], $definition[2] ?? null];
    $result = callApi($method, $path, $payload);

    $line = sprintf(
        '[%s] %s %s | HTTP %s | success=%s',
        (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        $method,
        $result['url'],
        $result['http_code'],
        $result['success'] ? 'true' : 'false'
    );

    fwrite($logHandle, $line . PHP_EOL);
    fwrite($logHandle, 'Yanıt: ' . $result['body'] . PHP_EOL);

    echo $line . PHP_EOL;
    echo $result['body'] . PHP_EOL . PHP_EOL;
}

fwrite($logHandle, "Test tamamlandı" . PHP_EOL);
fwrite($logHandle, str_repeat('=', 80) . PHP_EOL);
fclose($logHandle);

echo "Log dosyası: {$logFile}" . PHP_EOL;

/**
 * @param string               $method
 * @param string               $path
 * @param array<string, mixed> $payload
 * @return array{url:string, http_code:int, body:string, success:bool}
 */
function callApi(string $method, string $path, ?array $payload = null): array
{
    $url = rtrim(API_BASE_URL, '/') . '/' . ltrim($path, '/');

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('cURL başlatılamadı.');
    }

    $headers = [
        'Content-Type: application/json',
        'X-API-Key: ' . API_KEY,
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $body = curl_exec($ch);
    if ($body === false) {
        $body = json_encode(['success' => false, 'message' => curl_error($ch)], JSON_UNESCAPED_UNICODE);
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $success = false;
    $decoded = json_decode($body, true);
    if (is_array($decoded) && array_key_exists('success', $decoded)) {
        $success = (bool) $decoded['success'];
    }

    return [
        'url' => $url,
        'http_code' => $httpCode,
        'body' => $body,
        'success' => $success,
    ];
}
