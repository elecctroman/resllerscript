<?php

namespace App;

use App\Database;
use PDO;

class Settings
{
    /**
     * @var array<string,mixed>
     */
    private static $cache = [];

    public static function get(string $key, $default = null)
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            return $default;
        }

        self::$cache[$key] = $value;

        return $value;
    }

    public static function set(string $key, ?string $value): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, created_at) VALUES (:key, :value, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
        $stmt->execute([
            'key' => $key,
            'value' => $value,
        ]);

        self::$cache[$key] = $value;
    }

    public static function getMany(array $keys): array
    {
        $values = [];
        $missing = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, self::$cache)) {
                $values[$key] = self::$cache[$key];
            } else {
                $missing[] = $key;
            }
        }

        if ($missing) {
            $pdo = Database::connection();
            $in  = str_repeat('?,', count($missing) - 1) . '?';
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($in)");
            $stmt->execute($missing);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
                $values[$row['setting_key']] = $row['setting_value'];
            }

            foreach ($missing as $key) {
                if (!isset($values[$key])) {
                    $values[$key] = null;
                }
            }
        }

        return $values;
    }
}
