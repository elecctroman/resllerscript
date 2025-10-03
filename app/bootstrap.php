<?php

declare(strict_types=1);

namespace App;

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(static function ($class): void {
    $prefix = __NAMESPACE__ . '\\';
    $baseDir = __DIR__ . '/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

final class Logger
{
    private string $channel;
    private string $logFile;

    public function __construct(string $channel, string $logDirectory)
    {
        $this->channel = $channel;
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0775, true);
        }
        $this->logFile = rtrim($logDirectory, '/\\') . '/app.log';
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    private function log(string $level, string $message, array $context): void
    {
        $contextString = '';
        if ($context) {
            $encoded = [];
            foreach ($context as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $encoded[] = $key . '=' . (string) $value;
                } else {
                    $encoded[] = $key . '=' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            $contextString = ' ' . implode(' ', $encoded);
        }

        $line = sprintf('[%s] %s.%s: %s%s', date('Y-m-d H:i:s'), $this->channel, strtoupper($level), $message, $contextString);
        @file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND);
    }
}

interface RedisAdapterInterface
{
    public function incr(string $key): int;

    public function expire(string $key, int $seconds): bool;

    /**
     * @return array{0:string,1:string}|null
     */
    public function brpop(array $keys, int $timeout);

    public function lpush(string $key, string $value): int;

    public function zadd(string $key, array $members): int;

    /**
     * @param array{limit?:array{0:int,1:int}} $options
     * @return array<int,string>
     */
    public function zrangebyscore(string $key, $min, $max, array $options = []): array;

    public function zrem(string $key, string $member): int;
}

final class PredisRedisAdapter implements RedisAdapterInterface
{
    private \Predis\Client $client;

    public function __construct(\Predis\Client $client)
    {
        $this->client = $client;
    }

    public function incr(string $key): int
    {
        return (int) $this->client->incr($key);
    }

    public function expire(string $key, int $seconds): bool
    {
        $this->client->expire($key, $seconds);
        return true;
    }

    public function brpop(array $keys, int $timeout)
    {
        $result = $this->client->brpop($keys, $timeout);
        if ($result === null) {
            return null;
        }
        return [ (string) $result[0], (string) $result[1] ];
    }

    public function lpush(string $key, string $value): int
    {
        return (int) $this->client->lpush($key, [$value]);
    }

    public function zadd(string $key, array $members): int
    {
        $this->client->zadd($key, $members);
        return count($members);
    }

    public function zrangebyscore(string $key, $min, $max, array $options = []): array
    {
        $result = $this->client->zrangebyscore($key, $min, $max, $options);
        return array_map('strval', is_array($result) ? $result : []);
    }

    public function zrem(string $key, string $member): int
    {
        return (int) $this->client->zrem($key, [$member]);
    }
}

final class PhpRedisAdapter implements RedisAdapterInterface
{
    private \Redis $client;

    public function __construct(\Redis $client)
    {
        $this->client = $client;
    }

    public function incr(string $key): int
    {
        return (int) $this->client->incr($key);
    }

    public function expire(string $key, int $seconds): bool
    {
        return $this->client->expire($key, $seconds);
    }

    public function brpop(array $keys, int $timeout)
    {
        $result = $this->client->brPop($keys, $timeout);
        if ($result === false) {
            return null;
        }
        return [ (string) $result[0], (string) $result[1] ];
    }

    public function lpush(string $key, string $value): int
    {
        return (int) $this->client->lPush($key, $value);
    }

    public function zadd(string $key, array $members): int
    {
        $added = 0;
        foreach ($members as $score => $member) {
            $added += (int) $this->client->zAdd($key, (float) $score, (string) $member);
        }
        return $added;
    }

    public function zrangebyscore(string $key, $min, $max, array $options = []): array
    {
        $offset = 0;
        $count = -1;
        if (isset($options['limit'])) {
            $offset = (int) ($options['limit'][0] ?? 0);
            $count = (int) ($options['limit'][1] ?? -1);
        }
        $result = $this->client->zRangeByScore($key, $min, $max, ['withscores' => false, 'limit' => [$offset, $count]]);
        return array_map('strval', is_array($result) ? $result : []);
    }

    public function zrem(string $key, string $member): int
    {
        return (int) $this->client->zRem($key, $member);
    }
}

final class ArrayRedisAdapter implements RedisAdapterInterface
{
    /** @var array<string,mixed> */
    private array $store = [];
    /** @var array<string,int> */
    private array $expires = [];

    public function incr(string $key): int
    {
        $this->refresh($key);
        $value = (int) ($this->store[$key] ?? 0);
        $value++;
        $this->store[$key] = $value;
        return $value;
    }

    public function expire(string $key, int $seconds): bool
    {
        $this->expires[$key] = time() + $seconds;
        return true;
    }

    public function brpop(array $keys, int $timeout)
    {
        $end = microtime(true) + max(0, $timeout);
        while (microtime(true) < $end) {
            foreach ($keys as $key) {
                $this->refresh($key);
                if (!empty($this->store[$key]) && is_array($this->store[$key])) {
                    $value = array_pop($this->store[$key]);
                    if (empty($this->store[$key])) {
                        unset($this->store[$key]);
                    }
                    return [$key, (string) $value];
                }
            }
            usleep(250000);
        }
        return null;
    }

    public function lpush(string $key, string $value): int
    {
        $this->refresh($key);
        if (!isset($this->store[$key]) || !is_array($this->store[$key])) {
            $this->store[$key] = [];
        }
        array_unshift($this->store[$key], $value);
        return count($this->store[$key]);
    }

    public function zadd(string $key, array $members): int
    {
        $this->refresh($key);
        if (!isset($this->store[$key]) || !is_array($this->store[$key])) {
            $this->store[$key] = [];
        }
        foreach ($members as $score => $member) {
            $this->store[$key][(string) $member] = (float) $score;
        }
        return count($members);
    }

    public function zrangebyscore(string $key, $min, $max, array $options = []): array
    {
        $this->refresh($key);
        $set = isset($this->store[$key]) && is_array($this->store[$key]) ? $this->store[$key] : [];
        $minScore = $min === '-inf' ? -INF : (float) $min;
        $maxScore = $max === '+inf' ? INF : (float) $max;
        $filtered = [];
        foreach ($set as $member => $score) {
            if ($score >= $minScore && $score <= $maxScore) {
                $filtered[$member] = $score;
            }
        }
        asort($filtered);
        $values = array_keys($filtered);
        if (isset($options['limit'])) {
            $offset = (int) ($options['limit'][0] ?? 0);
            $count = (int) ($options['limit'][1] ?? count($values));
            $values = array_slice($values, $offset, $count);
        }
        return array_values($values);
    }

    public function zrem(string $key, string $member): int
    {
        $this->refresh($key);
        if (isset($this->store[$key]) && is_array($this->store[$key]) && array_key_exists($member, $this->store[$key])) {
            unset($this->store[$key][$member]);
            if (empty($this->store[$key])) {
                unset($this->store[$key]);
            }
            return 1;
        }
        return 0;
    }

    private function refresh(string $key): void
    {
        if (isset($this->expires[$key]) && $this->expires[$key] <= time()) {
            unset($this->store[$key], $this->expires[$key]);
        }
    }
}

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

    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
        putenv(sprintf('%s=%s', $key, $value));
        $_ENV[$key] = $value;
    }
}

final class Container
{
    private static ?\PDO $pdo = null;
    private static ?RedisAdapterInterface $redis = null;
    private static ?Logger $logger = null;
    private static bool $redisFallbackLogged = false;

    public static function db(): \PDO
    {
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }

        $host = Environment::get('DB_HOST')
            ?? (\defined('DB_HOST') ? DB_HOST : '127.0.0.1');
        $db = Environment::get('DB_NAME')
            ?? (\defined('DB_NAME') ? DB_NAME : 'resellerscript');
        $user = Environment::get('DB_USER')
            ?? (\defined('DB_USER') ? DB_USER : 'root');
        $pass = Environment::get('DB_PASS')
            ?? (\defined('DB_PASSWORD') ? DB_PASSWORD : '');
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

    public static function redis(): RedisAdapterInterface
    {
        if (self::$redis instanceof RedisAdapterInterface) {
            return self::$redis;
        }

        $host = Environment::get('REDIS_HOST', '127.0.0.1');
        $port = (int) (Environment::get('REDIS_PORT', '6379') ?? 6379);

        if (class_exists('\Predis\Client')) {
            self::$redis = new PredisRedisAdapter(new \Predis\Client([
                'scheme' => 'tcp',
                'host' => $host,
                'port' => $port,
            ]));
            return self::$redis;
        }

        if (class_exists('\Redis')) {
            $client = new \Redis();
            try {
                if (@$client->connect($host, $port, 1.5)) {
                    self::$redis = new PhpRedisAdapter($client);
                    return self::$redis;
                }
            } catch (\Throwable $exception) {
                error_log('Redis sunucusuna bağlanılamadı: ' . $exception->getMessage());
            }
        }

        if (!self::$redisFallbackLogged) {
            error_log('Predis veya PHP Redis uzantısı bulunamadı; dosya tabanlı yedek kuyruk kullanılacak.');
            self::$redisFallbackLogged = true;
        }
        self::$redis = new ArrayRedisAdapter();
        return self::$redis;
    }

    public static function logger(?string $channel = null): Logger
    {
        if ($channel === null && self::$logger instanceof Logger) {
            return self::$logger;
        }

        $channelName = $channel ?? 'application';
        $logDir = Environment::get('LOG_DIR', dirname(__DIR__) . '/storage/logs') ?? (dirname(__DIR__) . '/storage/logs');
        $logger = new Logger($channelName, $logDir);

        if ($channel === null) {
            self::$logger = $logger;
        }

        return $logger;
    }
}

// Allow the gateway utilities to share the main application configuration.
$configFile = dirname(__DIR__) . '/config/config.php';
if (is_file($configFile)) {
    require_once $configFile;

    if (\defined('DB_HOST')) {
        Environment::set('DB_HOST', (string) DB_HOST);
    }
    if (\defined('DB_NAME')) {
        Environment::set('DB_NAME', (string) DB_NAME);
    }
    if (\defined('DB_USER')) {
        Environment::set('DB_USER', (string) DB_USER);
    }
    if (\defined('DB_PASSWORD')) {
        Environment::set('DB_PASS', (string) DB_PASSWORD);
        Environment::set('DB_PASSWORD', (string) DB_PASSWORD);
    }
}

Environment::load(dirname(__DIR__) . '/.env');

\date_default_timezone_set(Environment::get('APP_TIMEZONE', 'UTC') ?? 'UTC');
