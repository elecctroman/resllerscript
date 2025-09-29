<?php

namespace App;

use PDO;

class ResellerPolicy
{
    /**
     * @var bool
     */
    private static $enforced = false;

    /**
     * @var bool|null
     */
    private static $hasColumn = null;

    /**
     * Enforce automatic suspension rules for reseller accounts.
     *
     * @return void
     */
    public static function enforce()
    {
        if (self::$enforced) {
            return;
        }

        self::$enforced = true;

        $enabled = Settings::get('reseller_auto_suspend_enabled');
        if ($enabled !== '1') {
            return;
        }

        $threshold = (float)Settings::get('reseller_auto_suspend_threshold', '0');
        $graceDays = (int)Settings::get('reseller_auto_suspend_days', '0');

        if ($threshold <= 0 || $graceDays <= 0) {
            return;
        }

        $pdo = Database::connection();

        if (!self::columnExists($pdo)) {
            return;
        }

        try {
            $clearStmt = $pdo->prepare('UPDATE users SET low_balance_since = NULL WHERE low_balance_since IS NOT NULL AND balance >= :threshold');
            $clearStmt->execute(array('threshold' => $threshold));
        } catch (\Throwable $exception) {
            return;
        }

        try {
            $selectStmt = $pdo->prepare("SELECT id, low_balance_since FROM users WHERE role = 'reseller' AND status = 'active' AND balance < :threshold");
            $selectStmt->execute(array('threshold' => $threshold));
        } catch (\Throwable $exception) {
            return;
        }

        $now = time();
        $graceSeconds = $graceDays * 86400;

        while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
            $userId = isset($row['id']) ? (int)$row['id'] : 0;
            if ($userId <= 0) {
                continue;
            }

            $since = isset($row['low_balance_since']) && $row['low_balance_since'] ? strtotime($row['low_balance_since']) : null;

            if ($since === null) {
                $markStmt = $pdo->prepare('UPDATE users SET low_balance_since = NOW() WHERE id = :id');
                $markStmt->execute(array('id' => $userId));
                continue;
            }

            if (($now - $since) < $graceSeconds) {
                continue;
            }

            $suspendStmt = $pdo->prepare("UPDATE users SET status = 'inactive', low_balance_since = NOW() WHERE id = :id");
            $suspendStmt->execute(array('id' => $userId));

            AuditLog::record(null, 'reseller.auto_suspend', 'user', $userId, sprintf('Bakiye %.2f altına düştüğü için otomatik pasife alındı.', $threshold));
        }
    }

    /**
     * @param PDO $pdo
     * @return bool
     */
    private static function columnExists(PDO $pdo)
    {
        if (self::$hasColumn !== null) {
            return self::$hasColumn;
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'low_balance_since'");
            self::$hasColumn = $stmt && $stmt->fetch() ? true : false;
        } catch (\Throwable $exception) {
            self::$hasColumn = false;
        }

        return self::$hasColumn;
    }
}
