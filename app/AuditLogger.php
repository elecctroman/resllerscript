<?php

namespace App;

use App\Database;

class AuditLogger
{
    /**
     * @param string $action
     * @param array<string,mixed> $context
     */
    public static function log(string $action, array $context = []): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $sessionUser = $_SESSION['user'] ?? null;
        $user = $context['user'] ?? $sessionUser;

        if (!$user || empty($user['id']) || empty($user['role'])) {
            return;
        }

        $metadata = $context['metadata'] ?? null;

        if (is_array($metadata) || is_object($metadata)) {
            $metadata = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (!is_string($metadata)) {
            $metadata = null;
        }

        $ipAddress = $context['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $userAgent = $context['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        if ($userAgent !== null) {
            $userAgent = substr($userAgent, 0, 255);
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO admin_activity_logs (user_id, user_role, action, target_type, target_id, description, metadata, ip_address, user_agent, created_at) '
                . 'VALUES (:user_id, :user_role, :action, :target_type, :target_id, :description, :metadata, :ip_address, :user_agent, NOW())'
            );

            $stmt->execute([
                'user_id' => $user['id'],
                'user_role' => $user['role'],
                'action' => $action,
                'target_type' => $context['target_type'] ?? null,
                'target_id' => $context['target_id'] ?? null,
                'description' => $context['description'] ?? null,
                'metadata' => $metadata,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        } catch (\Throwable $exception) {
            error_log('Audit log failed: ' . $exception->getMessage());
        }
    }
}
