<?php

declare(strict_types=1);

use App\Container;
use App\Services\NotificationService;

require __DIR__ . '/../bootstrap.php';

$args = $_SERVER['argv'] ?? [];
if (count($args) < 3) {
    fwrite(STDERR, "Kullanım: php app/Console/SendTest.php +905551112233 'Mesaj' [event]\n");
    exit(1);
}

$phone = $args[1];
$message = $args[2];
$event = $args[3] ?? 'manual';

if (!preg_match('/^\+?[1-9]\d{7,14}$/', $phone)) {
    throw new \RuntimeException('Telefon numarası E.164 formatında olmalı.');
}

$pdo = Container::db();
$redis = Container::redis();
$logger = Container::logger('console-test');
$service = new NotificationService($pdo, $redis, $logger);

$notificationId = $service->queueMessage(null, $phone, $event, $message, ['trigger' => 'cli']);

fwrite(STDOUT, sprintf("Bildirim kuyruğa alındı. ID: %d\n", $notificationId));
