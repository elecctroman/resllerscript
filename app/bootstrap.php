<?php

declare(strict_types=1);

namespace App;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Predis\Client as RedisClient;

require __DIR__ . '/../vendor/autoload.php';

final class Environment
{
    private static bool $loaded = false;
    /** @var array<string,string> */
    private static array $values = [];

    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }

        if ($path && is_file($path) && is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    if (!str_contains($line, '=')) {
                        continue;
                    }
                    [$key, $value] = array_map('trim', explode('=', $line, 2));
                    $value = trim($value, " \"'");
                    self::$values[$key] = $value;
                    putenv(sprintf('%s=%s', $key, $value));
                    $_ENV[$key] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (!self::$loaded) {
            self::load(dirname(__DIR__) . '/.env');
        }

        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            $value = self::$values[$key] ?? $default;
        }

        return $value ?? $default;
    }
}

final class Container
{
    private static ?\PDO $pdo = null;
    private static ?RedisClient $redis = null;
    private static ?Logger $logger = null;

    public static function db(): \PDO
    {
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }

        $host = Environment::get('DB_HOST', '127.0.0.1');
        $db = Environment::get('DB_NAME', 'whatsapp');
        $user = Environment::get('DB_USER', 'root');
        $pass = Environment::get('DB_PASS', '');
        $charset = 'utf8mb4';

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $db, $charset);
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        self::$pdo = new \PDO($dsn, $user, $pass, $options);
        return self::$pdo;
    }

    public static function redis(): RedisClient
    {
        if (self::$redis instanceof RedisClient) {
            return self::$redis;
        }

        $host = Environment::get('REDIS_HOST', '127.0.0.1');
        $port = (int) (Environment::get('REDIS_PORT', '6379') ?? 6379);

        self::$redis = new RedisClient([
            'scheme' => 'tcp',
            'host' => $host,
            'port' => $port,
        ]);

        return self::$redis;
    }

    public static function logger(?string $channel = null): Logger
    {
        if ($channel === null && self::$logger instanceof Logger) {
            return self::$logger;
        }

        $channelName = $channel ?? 'whatsapp-gateway';
        $logger = new Logger($channelName);

        $logDir = Environment::get('LOG_DIR', dirname(__DIR__) . '/storage/logs');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $logFile = rtrim($logDir, '/') . '/app.log';
        $handler = new StreamHandler($logFile, Logger::DEBUG);
        $logger->pushHandler($handler);

        if ($channel === null) {
            self::$logger = $logger;
        }

        return $logger;
    }
}

Environment::load(dirname(__DIR__) . '/.env');

\date_default_timezone_set(Environment::get('APP_TIMEZONE', 'UTC') ?? 'UTC');
