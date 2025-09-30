<?php

namespace App;

class ErrorLogger
{
    /** @var bool */
    private static $registered = false;

    public static function register(string $logFile): void
    {
        if (self::$registered) {
            return;
        }

        $directory = dirname($logFile);
        if (!is_dir($directory)) {
            return;
        }

        self::$registered = true;

        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);

        set_error_handler(function ($severity, $message, $file, $line) {
            $entry = sprintf(
                '[%s] %s: %s in %s on line %d',
                date('Y-m-d H:i:s'),
                self::severityToString((int)$severity),
                $message,
                $file,
                $line
            );

            error_log($entry);

            return true;
        });

        set_exception_handler(function ($exception) {
            $entry = sprintf(
                "[%s] Uncaught %s: %s in %s on line %d\n%s",
                date('Y-m-d H:i:s'),
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            );

            error_log($entry);

            if (!headers_sent()) {
                http_response_code(500);
            }

            if (PHP_SAPI !== 'cli') {
                echo 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
            }
        });

        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $entry = sprintf(
                    '[%s] Fatal %s: %s in %s on line %d',
                    date('Y-m-d H:i:s'),
                    self::severityToString((int)$error['type']),
                    $error['message'],
                    $error['file'],
                    $error['line']
                );

                error_log($entry);
            }
        });
    }

    private static function severityToString(int $severity): string
    {
        $map = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];

        return $map[$severity] ?? 'E_' . $severity;
    }
}
