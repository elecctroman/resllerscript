#!/usr/bin/env php
<?php declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\{Logger, LotusClient, LotusOrderRepository, LotusOrderService};

$apiKey = envStr('LOTUS_API_KEY');
if (!$apiKey) {
    fwrite(STDERR, "HATA: LOTUS_API_KEY boş.\n");
    exit(1);
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

$service->pollPending(function (int $localOrderId, string $content) use ($logger): void {
    $msg = "LOCAL ORDER #{$localOrderId} TESLİM: \n{$content}\n";
    echo $msg;
    $logger->info($msg);
});
