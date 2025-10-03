<?php declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

// .env yükle
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Klasörleri oluştur
@mkdir(__DIR__ . '/storage', 0777, true);

// Basit helper'lar
function envStr(string $key, ?string $default = null): ?string {
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}
