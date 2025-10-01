<?php

namespace App\Notifications;

use App\Database;
use PDO;

class PreferenceManager
{
    /**
     * @var bool
     */
    private static $ensured = false;

    /**
     * @var bool
     */
    private static $available = false;

    /**
     * Ensure notification preference columns exist on the users table.
     *
     * @return bool
     */
    public static function ensureUserColumns()
    {
        if (self::$ensured) {
            return self::$available;
        }

        self::$ensured = true;

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            self::$available = false;
            return false;
        }

        $definitions = array(
            'notify_order_completed' => "ALTER TABLE users ADD COLUMN notify_order_completed TINYINT(1) NOT NULL DEFAULT 1 AFTER status",
            'notify_balance_approved' => "ALTER TABLE users ADD COLUMN notify_balance_approved TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_order_completed",
            'notify_support_replied' => "ALTER TABLE users ADD COLUMN notify_support_replied TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_balance_approved",
        );

        foreach ($definitions as $column => $statement) {
            if (!self::columnExists($pdo, $column)) {
                try {
                    $pdo->exec($statement);
                } catch (\Throwable $exception) {
                    // If we cannot create the column we bail out and continue with legacy behaviour.
                    self::$available = false;
                    return false;
                }
            }
        }

        self::$available = true;
        return true;
    }

    /**
     * @return bool
     */
    public static function available()
    {
        return self::$available;
    }

    /**
     * @param PDO   $pdo
     * @param string $column
     * @return bool
     */
    private static function columnExists(PDO $pdo, $column)
    {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE :column");
            $stmt->execute(array('column' => $column));

            return (bool)$stmt->fetch();
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
