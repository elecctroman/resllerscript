<?php declare(strict_types=1);

$rootPath = __DIR__;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$forwardedScheme = null;
if (isset($_SERVER['HTTP_CF_VISITOR'])) {
    $visitorMeta = json_decode((string)$_SERVER['HTTP_CF_VISITOR'], true);
    if (is_array($visitorMeta) && isset($visitorMeta['scheme'])) {
        $forwardedScheme = strtolower((string)$visitorMeta['scheme']);
    }
}

if (!$forwardedScheme && isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $forwardedScheme = strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']);
}

if ($forwardedScheme === 'https') {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REQUEST_SCHEME'] = 'https';
} elseif ($forwardedScheme && !isset($_SERVER['REQUEST_SCHEME'])) {
    $_SERVER['REQUEST_SCHEME'] = $forwardedScheme;
}

$composerAutoload = $rootPath . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(static function ($class) use ($rootPath): void {
    $prefix = 'App\\';
    $length = strlen($prefix);

    if (strncmp($prefix, $class, $length) !== 0) {
        return;
    }

    $relative = substr($class, $length);
    $segments = str_replace('\\', '/', $relative);

    $paths = array(
        $rootPath . '/src/' . $segments . '.php',
        $rootPath . '/app/' . $segments . '.php',
    );

    foreach ($paths as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});

if (!function_exists('envStr')) {
    function envStr(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}

if (class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable($rootPath)->safeLoad();
} elseif (is_file($rootPath . '/env.php')) {
    /** @noinspection PhpIncludeInspection */
    require_once $rootPath . '/env.php';
}

@mkdir($rootPath . '/storage', 0777, true);

$configPath = $rootPath . '/config/config.php';
if (is_file($configPath)) {
    require_once $configPath;
}

$dbHost = envStr('DB_HOST', defined('DB_HOST') ? (string) DB_HOST : 'localhost');
$dbName = envStr('DB_NAME', defined('DB_NAME') ? (string) DB_NAME : '');
$dbUser = envStr('DB_USER', defined('DB_USER') ? (string) DB_USER : '');
$dbPassword = envStr('DB_PASSWORD', defined('DB_PASSWORD') ? (string) DB_PASSWORD : '');

if ($dbName !== '') {
    try {
        App\Database::initialize(array(
            'host' => $dbHost,
            'name' => $dbName,
            'user' => $dbUser,
            'password' => $dbPassword,
        ));
    } catch (\Throwable $connectionException) {
        error_log('[Bootstrap] Veritabanı bağlantısı kurulamadı: ' . $connectionException->getMessage());
    }
}

if (class_exists(App\Migrations\Schema::class)) {
    try {
        App\Migrations\Schema::ensure();
    } catch (\Throwable $schemaException) {
        error_log('[Bootstrap] Şema güncellenemedi: ' . $schemaException->getMessage());
    }
}
