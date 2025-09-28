<?php

namespace App;

class Helpers
{
    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    public static function sanitize(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function truncate(?string $value, int $limit = 100, string $suffix = '…'): string
    {
        $value = (string)$value;

        if ($limit <= 0) {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') <= $limit) {
                return $value;
            }

            return rtrim(mb_substr($value, 0, $limit, 'UTF-8')) . $suffix;
        }

        if (strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(substr($value, 0, $limit)) . $suffix;
    }

    public static function defaultProductDescription(): string
    {
        return 'Bu ürün için detaylı bilgiye ihtiyaç duyarsanız destek ekibimizle iletişime geçebilirsiniz.';
    }

    public static function currentPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        return $path ?: '/';
    }

    public static function isActive(string $pattern): bool
    {
        $current = self::currentPath();

        if (function_exists('fnmatch')) {
            return fnmatch($pattern, $current);
        }

        if ($pattern === $current) {
            return true;
        }

        $escaped = preg_quote($pattern, '#');
        $escaped = str_replace(['\*', '\?'], ['.*', '.'], $escaped);

        return (bool)preg_match('#^' . $escaped . '$#', $current);
    }
}
