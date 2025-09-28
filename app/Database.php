<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function initialize(array $config): void
    {
        if (self::$connection !== null) {
            return;
        }

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['name']);

        try {
            self::$connection = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            throw new PDOException('Veritabanına bağlanırken bir hata oluştu: ' . $exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            throw new PDOException('Veritabanı bağlantısı başlatılmadı.');
        }

        return self::$connection;
    }
}
