<?php

namespace App;

class Helpers
{
    /**
     * @return string
     */
    public static function csrfToken()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * @param string $token
     * @return bool
     */
    public static function verifyCsrf($token)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * @param string $path
     * @return void
     */
    public static function redirect($path)
    {
        header('Location: ' . $path);
        exit;
    }

    /**
     * @param string $value
     * @return string
     */
    public static function sanitize($value)
    {
        if (is_string($value)) {
            Lang::boot();
            $value = Lang::line($value);
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param string $text
     * @param string|null $key
     * @return string
     */
    public static function translate($text, $key = null)
    {
        Lang::boot();
        return Lang::line($text, $key);
    }

    /**
     * @return string
     */
    public static function activeCurrency()
    {
        Lang::boot();
        return Lang::locale() === 'tr' ? 'TRY' : 'USD';
    }

    /**
     * @param float $amount
     * @param string $baseCurrency
     * @return string
     */
    public static function formatCurrency($amount, $baseCurrency = 'USD')
    {
        Lang::boot();
        $activeCurrency = self::activeCurrency();

        if ($activeCurrency !== $baseCurrency) {
            $amount = Currency::convert((float)$amount, $baseCurrency, $activeCurrency);
        }

        return Currency::format((float)$amount, $activeCurrency);
    }

    /**
     * @return string
     */
    public static function currencySymbol()
    {
        Lang::boot();
        return Currency::symbol(self::activeCurrency());
    }

    /**
     * @param string|null $value
     * @param int $limit
     * @param string $suffix
     * @return string
     */
    public static function truncate($value = null, $limit = 100, $suffix = '…')
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

    /**
     * @return string
     */
    public static function defaultProductDescription()
    {
        return 'Bu ürün için detaylı bilgiye ihtiyaç duyarsanız destek ekibimizle iletişime geçebilirsiniz.';
    }

    /**
     * @return string
     */
    public static function currentPath()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($uri, PHP_URL_PATH);

        return $path ?: '/';
    }

    /**
     * @param string $pattern
     * @return bool
     */
    public static function isActive($pattern)
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
