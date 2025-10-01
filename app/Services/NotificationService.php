<?php

declare(strict_types=1);

namespace App\Services;

use App\Logger;
use App\Notifications\Template;
use App\RedisAdapterInterface;
use InvalidArgumentException;
use PDO;

class NotificationService
{
    private PDO $db;
    private RedisAdapterInterface $redis;
    private Logger $logger;

    /** @var array<string,string> */
    private array $defaultTemplates = [
        'order_completed' => 'Merhaba {{name}}, #{{order_id}} numaralı siparişiniz tamamlandı.',
        'support_replied' => 'Merhaba {{name}}, destek talebiniz yanıtlandı: {{ticket_subject}}.',
        'price_changed' => 'Sayın {{name}}, {{product_name}} için fiyat güncellendi: {{price}}.',
        'balance_low' => 'Sayın {{name}}, bakiyeniz {{balance}} seviyesine düştü. Lütfen yükleme yapın.',
        'manual' => '{{message}}',
        'test' => 'Test mesajı: {{message}}',
    ];

    public function __construct(PDO $db, RedisAdapterInterface $redis, Logger $logger)
    {
        $this->db = $db;
        $this->redis = $redis;
        $this->logger = $logger;
    }

    /**
     * @param array<string, scalar|array|null> $payload
     * @return array<int>
     */
    public function notify(string $event, array $payload, int $resellerId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM notification_subscriptions WHERE reseller_id = :reseller AND enabled = 1');
        $stmt->execute([':reseller' => $resellerId]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $notificationIds = [];
        foreach ($subscriptions as $subscription) {
            $events = json_decode($subscription['events'] ?? '[]', true);
            if (!is_array($events) || !in_array($event, $events, true)) {
                continue;
            }

            $template = $this->resolveTemplate($event, $payload);
            $message = Template::render($template, $payload + ['message' => $payload['message'] ?? '']);
            $payloadWithMessage = $payload;
            $payloadWithMessage['rendered'] = $message;

            $notificationId = $this->queueMessage(
                (int) $subscription['id'],
                (string) $subscription['phone_number'],
                $event,
                $message,
                $payloadWithMessage
            );
            $notificationIds[] = $notificationId;
        }

        return $notificationIds;
    }

    /**
     * @param array<string, scalar|array|null> $payload
     */
    public function queueMessage(?int $subscriptionId, string $phone, string $event, string $message, array $payload = []): int
    {
        if (!preg_match('/^\+?[1-9]\d{7,14}$/', $phone)) {
            throw new InvalidArgumentException('Phone number must be in E.164 format.');
        }

        $payload['message'] = $message;
        $stmt = $this->db->prepare('INSERT INTO notifications (subscription_id, event_type, payload, status, created_at) VALUES (:subscription_id, :event, :payload, :status, NOW())');
        $stmt->execute([
            ':subscription_id' => $subscriptionId,
            ':event' => $event,
            ':payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ':status' => 'pending',
        ]);

        $notificationId = (int) $this->db->lastInsertId();

        $job = [
            'notification_id' => $notificationId,
            'phone' => $phone,
            'message' => $message,
        ];

        $this->redis->lpush('whatsapp:queue', json_encode($job, JSON_THROW_ON_ERROR));
        $this->logger->info('Notification queued', ['notification_id' => $notificationId, 'phone' => $phone]);

        return $notificationId;
    }

    /**
     * @param array<string, scalar|array|null> $payload
     */
    private function resolveTemplate(string $event, array $payload): string
    {
        if (isset($payload['template']) && is_string($payload['template'])) {
            return $payload['template'];
        }

        if (isset($this->defaultTemplates[$event])) {
            return $this->defaultTemplates[$event];
        }

        return $this->defaultTemplates['manual'];
    }
}
