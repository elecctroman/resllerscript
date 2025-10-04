<?php declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Db
{
    public static function pdo(): PDO
    {
        try {
            return Database::connection();
        } catch (PDOException $exception) {
            throw $exception;
        }
    }
}
