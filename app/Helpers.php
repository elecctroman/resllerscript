<?php

namespace App;

class Helpers
{
    /**
     * @return string
     */
    public static function csrfToken()
    {
        self::ensureSession();

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
        self::ensureSession();

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
     * @return void
     */
    private static function ensureSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function setFlash($key, $value)
    {
        self::ensureSession();

        if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            $_SESSION['flash'] = array();
        }

        $_SESSION['flash'][$key] = $value;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getFlash($key, $default = null)
    {
        self::ensureSession();

        if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            return $default;
        }

        if (!array_key_exists($key, $_SESSION['flash'])) {
            return $default;
        }

        $value = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);

        if (!$_SESSION['flash']) {
            unset($_SESSION['flash']);
        }

        return $value;
    }

    /**
     * @param string $path
     * @param array $flashes
     * @return void
     */
    public static function redirectWithFlash($path, $flashes = array())
    {
        if (!is_array($flashes)) {
            $flashes = array();
        }

        foreach ($flashes as $key => $value) {
            self::setFlash($key, $value);
        }

        self::redirect($path);
    }

    /**
     * @param string $path
     * @param string $default
     * @return string
     */
    public static function normalizeRedirectPath($path, $default = '/')
    {
        if (!$path) {
            return $default;
        }

        $path = trim($path);

        if ($path === '' || $path === '#') {
            return $default;
        }

        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $default;
        }

        if ($path[0] !== '/') {
            return $default;
        }

        if (strpos($path, '//') === 0) {
            return $default;
        }

        $path = strtok($path, "\r\n");

        return $path ?: $default;
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
