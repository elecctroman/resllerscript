<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    /**
     * @var PDO|null
     */
    private static $connection = null;

    /**
     * Başlatılmış bir PDO bağlantısını bellekte tutar.
     *
     * Eski PHP sürümleriyle uyumluluk adına dönüş tipleri tanımlanmamıştır.
     *
     * @param array $config
     * @return void
     */
    public static function initialize(array $config)
    {
        if (self::$connection instanceof PDO) {
            return;
        }

        $host = isset($config['host']) ? $config['host'] : 'localhost';
        $name = isset($config['name']) ? $config['name'] : '';
        $user = isset($config['user']) ? $config['user'] : '';
        $password = isset($config['password']) ? $config['password'] : '';

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);

        try {
            self::$connection = new PDO($dsn, $user, $password, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
        } catch (PDOException $exception) {
            throw new PDOException(
                'Veritabanına bağlanırken bir hata oluştu: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * @return PDO
     */
    public static function connection()
    {
        if (!(self::$connection instanceof PDO)) {
            throw new PDOException('Veritabanı bağlantısı başlatılmadı.');
        }

        return self::$connection;
    }
}
