<?php

namespace App;

use PDO;
use PDOException;

class Database
{
            throw new PDOException('Veritabanı bağlantısı başlatılmadı.');
        }

        return self::$connection;
    }
}
