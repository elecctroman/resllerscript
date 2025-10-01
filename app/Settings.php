<?php

namespace App;

use App\Database;
use PDO;
use PDOException;

class Settings
{
    /**
     * @var array<string,mixed>
     */
    private static $cache = [];

    /**
     * @var bool
     */
    private static $settingsTableMissing = false;

    /**
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public static function get($key, $default = null)
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = :key LIMIT 1');

        try {
            $stmt->execute(['key' => $key]);
        } catch (PDOException $exception) {
            if (self::handleSettingsException($exception)) {
                return $default;
            }

            throw $exception;
        }
        $value = $stmt->fetchColumn();

        if ($value === false) {
            return $default;
        }

        self::$cache[$key] = $value;

        return $value;
    }

    /**
     * @param string $key
     * @param string|null $value
     * @return void
     */
    public static function set($key, $value)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, created_at) VALUES (:key, :value, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');

        try {
            $stmt->execute([
                'key' => $key,
                'value' => $value,
            ]);
        } catch (PDOException $exception) {
            if (self::handleSettingsException($exception)) {
                self::$cache[$key] = $value;

                return;
            }

            throw $exception;
        }

        self::$cache[$key] = $value;
    }

    /**
     * @param array $keys
     * @return array
     */
    public static function getMany($keys)
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

            try {
                $stmt->execute($missing);
            } catch (PDOException $exception) {
                if (self::handleSettingsException($exception)) {
                    foreach ($missing as $key) {
                        $values[$key] = null;
                    }

                    return $values;
                }

                throw $exception;
            }

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

    /**
     * @param PDOException $exception
     * @return bool
     */
    private static function handleSettingsException(PDOException $exception)
    {
        if (!self::isMissingSettingsTable($exception)) {
            return false;
        }

        if (!self::$settingsTableMissing) {
            self::$settingsTableMissing = true;
            error_log('[Settings] system_settings table is missing or inaccessible: ' . $exception->getMessage());
        }

        return true;
    }

    /**
     * @param PDOException $exception
     * @return bool
     */
    private static function isMissingSettingsTable(PDOException $exception)
    {
        if ($exception->getCode() === '42S02') {
            return true;
        }

        $message = $exception->getMessage();

        return stripos($message, 'system_settings') !== false && stripos($message, 'exist') !== false;
    }
}
