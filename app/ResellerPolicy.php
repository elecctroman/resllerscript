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
            $selectStmt = $pdo->prepare("SELECT id, name, email, balance, low_balance_since FROM users WHERE role = 'reseller' AND status = 'active' AND balance < :threshold");
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

                if ($markStmt->rowCount() > 0) {
                    $email = isset($row['email']) ? trim((string)$row['email']) : '';
                    if ($email !== '') {
                        $name = isset($row['name']) ? trim((string)$row['name']) : '';
                        $balance = isset($row['balance']) ? (float)$row['balance'] : 0.0;
                        $deficit = $threshold - $balance;
                        if ($deficit < 0) {
                            $deficit = 0.0;
                        }

                        $deadline = date('d.m.Y H:i', $now + $graceSeconds);
                        $subject = 'Düşük Bakiye Uyarısı';
                        $message = sprintf(
                            "Merhaba %s,\n\nBakiyeniz %s altına düştü. Hesabınız %s tarihine kadar minimum bakiye tutarı yüklenmezse pasife alınacaktır.\n\nBayiliğinize devam etmek için en az %s yükleyerek bakiyenizi güncelleyin.\n\nSaygılarımızla,\n%s",
                            $name !== '' ? $name : 'Bayi',
                            Helpers::formatCurrency($threshold),
                            $deadline,
                            Helpers::formatCurrency($deficit > 0 ? $deficit : $threshold),
                            Helpers::siteName()
                        );

                        try {
                            Mailer::send($email, $subject, $message);
                        } catch (\Throwable $notificationException) {
                            error_log('Düşük bakiye bildirimi gönderilemedi: ' . $notificationException->getMessage());
                        }
                    }
                }

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

    /**
     * @param array|null $user
     * @return array|null
     */
    public static function lowBalanceNotice($user)
    {
        if (!$user || !is_array($user)) {
            return null;
        }

        if (!isset($user['role']) || $user['role'] !== 'reseller') {
            return null;
        }

        if (Settings::get('reseller_auto_suspend_enabled') !== '1') {
            return null;
        }

        $threshold = (float)Settings::get('reseller_auto_suspend_threshold', '0');
        $graceDays = (int)Settings::get('reseller_auto_suspend_days', '0');

        if ($threshold <= 0 || $graceDays <= 0) {
            return null;
        }

        $balance = isset($user['balance']) ? (float)$user['balance'] : 0.0;
        if ($balance >= $threshold) {
            return null;
        }

        $sinceRaw = isset($user['low_balance_since']) ? $user['low_balance_since'] : null;
        $since = $sinceRaw ? strtotime($sinceRaw) : null;
        if ($since === false) {
            $since = null;
        }

        $graceSeconds = $graceDays * 86400;
        $reference = $since ?: time();
        $deadlineTs = $reference + $graceSeconds;
        $remainingSeconds = $deadlineTs - time();
        if ($remainingSeconds < 0) {
            $remainingSeconds = 0;
        }

        $remainingDays = (int)ceil($remainingSeconds / 86400);
        $remainingHours = (int)ceil($remainingSeconds / 3600);

        return array(
            'threshold' => $threshold,
            'grace_days' => $graceDays,
            'deadline_ts' => $deadlineTs,
            'deadline' => date('d.m.Y H:i', $deadlineTs),
            'remaining_days' => $remainingDays,
            'remaining_hours' => $remainingHours,
            'balance' => $balance,
            'deficit' => max(0.0, $threshold - $balance),
        );
    }
}
