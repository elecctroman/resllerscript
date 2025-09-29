<?php

namespace App;

use App\Database;
use PDO;

class AuditLog
{
    /**
     * Record an administrative activity.
     *
     * @param int $userId
     * @param string $action
     * @param string|null $targetType
     * @param int|null $targetId
     * @param string|null $description
     * @return void
     */
    public static function record($userId, $action, $targetType = null, $targetId = null, $description = null)
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('INSERT INTO admin_activity_logs (user_id, action, target_type, target_id, description, ip_address, created_at) VALUES (:user_id, :action, :target_type, :target_id, :description, :ip_address, NOW())');
            $stmt->execute(array(
                'user_id' => $userId !== null ? (int)$userId : null,
                'action' => (string)$action,
                'target_type' => $targetType ?: null,
                'target_id' => $targetId !== null ? (int)$targetId : null,
                'description' => $description ?: null,
                'ip_address' => Helpers::ipAddress(),
            ));
        } catch (\Throwable $exception) {
            // Silently ignore logging failures to avoid breaking the main workflow.
        }
    }
}
