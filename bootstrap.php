<?php

$rootPath = __DIR__;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!defined('APP_ERROR_LOG_INITIALIZED')) {
    $logDirectory = $rootPath . '/admin';
    $logFile = $logDirectory . '/error.log';

    if (!defined('APP_ERROR_LOG_PATH')) {
        define('APP_ERROR_LOG_PATH', $logFile);
    }

    if (!is_dir($logDirectory)) {
        @mkdir($logDirectory, 0775, true);
    }

    if (!is_file($logFile)) {
        @touch($logFile);
        if (is_file($logFile)) {
            @chmod($logFile, 0664);
        }
    }

    if (is_writable($logFile) || is_writable($logDirectory)) {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);

        $formatter = static function ($type, $message, $file = null, $line = null) {
            $timestamp = date('Y-m-d H:i:s');
            $location = '';
            if ($file !== null) {
                $location = ' in ' . $file;
                if ($line !== null) {
                    $location .= ':' . $line;
                }
            }

            return sprintf('[%s] %s: %s%s', $timestamp, $type, $message, $location);
        };

        set_error_handler(static function ($severity, $message, $file = null, $line = null) use ($formatter) {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            error_log($formatter('PHP Error', $message, $file, $line));
            return false;
        });

        set_exception_handler(static function ($throwable) use ($formatter) {
            $isThrowable = $throwable instanceof Exception;
            if (!$isThrowable && class_exists('Throwable')) {
                $isThrowable = is_a($throwable, 'Throwable');
            }

            if ($isThrowable && is_object($throwable)) {
                error_log($formatter('Uncaught Exception', $throwable->getMessage(), $throwable->getFile(), $throwable->getLine()));
                return;
            }

            error_log($formatter('Uncaught Error', is_object($throwable) ? get_class($throwable) : (string) $throwable));
        });

        register_shutdown_function(static function () use ($formatter) {
            $error = error_get_last();
            if ($error !== null) {
                error_log($formatter('Shutdown Error', (string) $error['message'], $error['file'], (int) $error['line']));
            }
        });

        define('APP_ERROR_LOG_INITIALIZED', true);
    }
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

spl_autoload_register(static function ($class) use ($rootPath) {
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
    function envStr($key, $default = null)
    {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return $default;
    }
}

if (class_exists('\\Dotenv\\Dotenv')) {
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
    } catch (Exception $connectionException) {
        error_log('[Bootstrap] Veritabanı bağlantısı kurulamadı: ' . $connectionException->getMessage());
    }
}

if (class_exists(App\Migrations\Schema::class)) {
    try {
        App\Migrations\Schema::ensure();
    } catch (Exception $schemaException) {
        error_log('[Bootstrap] Şema güncellenemedi: ' . $schemaException->getMessage());
    }
}

if (!empty($_SESSION['user']) && isset($_SESSION['user']['id'])) {
    try {
        $pdo = App\Database::connection();
        if ($pdo) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(array('id' => (int) $_SESSION['user']['id']));
            $freshUser = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($freshUser) {
                $_SESSION['user'] = array_merge($_SESSION['user'], $freshUser);
            } else {
                unset($_SESSION['user']);
            }
        }
    } catch (Exception $refreshException) {
        error_log('[Bootstrap] Oturum kullanıcısı yenilenemedi: ' . $refreshException->getMessage());
    }
}
