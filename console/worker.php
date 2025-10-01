<?php

declare(strict_types=1);

use App\Container;
use App\Environment;
use App\RedisAdapterInterface;
use App\WhatsApp\Gateway;

require __DIR__ . '/../app/bootstrap.php';

$pdo = Container::db();
$redis = Container::redis();
$logger = Container::logger('worker');

$maxAttempts = (int) (Environment::get('WORKER_MAX_ATTEMPTS', '5') ?? 5);

$logger->info('Worker started', ['max_attempts' => $maxAttempts]);

while (true) {
    promoteDelayedJobs($redis);

    $jobItem = $redis->brpop(['whatsapp:queue'], 5);
    if (!$jobItem) {
        continue;
    }

    $payload = json_decode($jobItem[1], true);
    if (!is_array($payload) || !isset($payload['notification_id'])) {
        $logger->warning('Invalid job payload', ['payload' => $jobItem[1] ?? null]);
        continue;
    }

    $notificationId = (int) $payload['notification_id'];
    $phone = (string) ($payload['phone'] ?? '');
    $message = (string) ($payload['message'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE id = :id');
    $stmt->execute([':id' => $notificationId]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$notification) {
        $logger->warning('Notification not found', ['notification_id' => $notificationId]);
        continue;
    }

    $attempts = (int) $notification['attempts'];

    try {
        $session = findConnectedSession($pdo);
        if ($session === null) {
            throw new \RuntimeException('No connected WhatsApp session available');
        }

        $gateway = new Gateway($pdo, $logger, $session);
        $gateway->sendMessage($phone, $message);

        $update = $pdo->prepare('UPDATE notifications SET status = :status, attempts = :attempts, sent_at = NOW(), last_error = NULL WHERE id = :id');
        $update->execute([
            ':status' => 'sent',
            ':attempts' => $attempts + 1,
            ':id' => $notificationId,
        ]);
        $logger->info('Notification sent', ['notification_id' => $notificationId, 'phone' => $phone]);
    } catch (\Throwable $exception) {
        $attempts++;
        $status = $attempts >= $maxAttempts ? 'failed' : 'pending';
        $update = $pdo->prepare('UPDATE notifications SET attempts = :attempts, status = :status, last_error = :error WHERE id = :id');
        $update->execute([
            ':attempts' => $attempts,
            ':status' => $status,
            ':error' => $exception->getMessage(),
            ':id' => $notificationId,
        ]);

        $logger->error('Notification delivery failed', [
            'notification_id' => $notificationId,
            'error' => $exception->getMessage(),
            'attempts' => $attempts,
        ]);

        if ($status !== 'failed') {
            $delay = min(3600, max(15, 60 * $attempts));
            scheduleRetry($redis, $payload, $delay);
        }
    }
}

/**
 * @return array<string,mixed>|null
 */
function findConnectedSession(\PDO $pdo): ?array
{
    $stmt = $pdo->query("SELECT * FROM whatsapp_sessions WHERE status = 'connected' ORDER BY updated_at DESC LIMIT 1");
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($session) {
        return $session;
    }

    $stmt = $pdo->query('SELECT * FROM whatsapp_sessions ORDER BY updated_at DESC LIMIT 1');
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function promoteDelayedJobs(RedisAdapterInterface $redis): void
{
    $now = time();
    $due = $redis->zrangebyscore('whatsapp:retry', '-inf', (string) $now, ['limit' => [0, 10]]);
    foreach ($due as $job) {
        $redis->zrem('whatsapp:retry', $job);
        $redis->lpush('whatsapp:queue', $job);
    }
}

function scheduleRetry(RedisAdapterInterface $redis, array $payload, int $delay): void
{
    $runAt = time() + $delay;
    try {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $redis->zadd('whatsapp:retry', [$runAt => $encoded]);
    } catch (\JsonException $exception) {
        Container::logger('worker')->error('Failed to schedule retry', ['error' => $exception->getMessage()]);
    }
}
