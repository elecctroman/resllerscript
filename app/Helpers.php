<?php

namespace App;

class Helpers
{
    /**
     * @var string|null
     */
    private static $basePath;

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
        if (!$path) {
            $path = '/';
        }

        if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) {
            header('Location: ' . $path);
            exit;
        }

        $normalized = self::normalizeRedirectPath($path, '/');
        $target = self::url(ltrim($normalized, '/'));

        header('Location: ' . $target);
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
     * @return string
     */
    public static function siteName()
    {
        $value = Settings::get('site_name');
        $value = $value !== null ? trim($value) : '';

        return $value !== '' ? $value : 'Bayi Yönetim Sistemi';
    }

    /**
     * @return string
     */
    public static function siteTagline()
    {
        $value = Settings::get('site_tagline');
        $value = $value !== null ? trim($value) : '';

        return $value !== '' ? $value : 'Reseller automation platform';
    }

    /**
     * @return string
     */
    public static function seoDescription()
    {
        $value = Settings::get('seo_meta_description');
        $value = $value !== null ? trim($value) : '';

        if ($value !== '') {
            return $value;
        }

        return 'Manage reseller orders, balance, and WooCommerce synchronisation from a single dashboard.';
    }

    /**
     * @return string
     */
    public static function seoKeywords()
    {
        $value = Settings::get('seo_meta_keywords');
        $value = $value !== null ? trim($value) : '';

        return $value !== '' ? $value : 'reseller panel, smm panel, automation';
    }

    /**
     * @return string
     */
    public static function basePath()
    {
        if (self::$basePath !== null) {
            return self::$basePath;
        }

        $script = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';

        if ($script === '') {
            self::$basePath = '';

            return self::$basePath;
        }

        $directory = str_replace('\\', '/', dirname($script));

        if ($directory === '.' || $directory === '/' || $directory === '\\') {
            $directory = '';
        } else {
            $directory = rtrim($directory, '/');
        }

        self::$basePath = $directory;

        return self::$basePath;
    }

    /**
     * Build an application-relative URL.
     *
     * @param string $path
     * @param bool   $absolute
     * @return string
     */
    public static function url($path = '', $absolute = false)
    {
        $relative = $path !== '' ? '/' . ltrim((string)$path, '/') : '';
        $base = self::basePath();
        $uri = ($base ? $base : '') . $relative;

        if ($uri === '') {
            $uri = '/';
        }

        if ($absolute) {
            $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

            return $scheme . '://' . $host . $uri;
        }

        return $uri;
    }

    /**
     * @param string $path
     * @return string
     */
    public static function asset($path)
    {
        $clean = ltrim((string)$path, '/');

        return self::url('assets/' . $clean);
    }

    /**
     * Safely include a template from the templates directory.
     *
     * @param string $template
     * @param array<string,mixed> $data
     * @return void
     */
    public static function includeTemplate($template, array $data = [])
    {
        $cleanTemplate = ltrim((string)$template, '/');
        $cleanTemplate = str_replace(['..', '\\'], '', $cleanTemplate);

        $path = dirname(__DIR__) . '/templates/' . $cleanTemplate;

        if (!is_file($path)) {
            $message = sprintf('Template not found: %s', $cleanTemplate);
            error_log('[Template] ' . $message);

            if (PHP_SAPI !== 'cli') {
                echo '<!-- ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . ' -->';
            }

            return;
        }

        if ($data) {
            extract($data, EXTR_SKIP);
        }

        include $path;
    }

    /**
     * @param string $feature
     * @return bool
     */
    public static function featureEnabled($feature)
    {
        return FeatureToggle::isEnabled($feature);
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
     * @return float
     */
    public static function commissionRate()
    {
        $stored = Settings::get('pricing_commission_rate');
        $rate = $stored !== null ? (float)$stored : 0.0;

        if ($rate < 0) {
            $rate = 0.0;
        }

        return $rate;
    }

    /**
     * @param float $costTry
     * @return float
     */
    public static function priceFromCostTry($costTry)
    {
        $cost = max(0.0, (float)$costTry);
        $usd = Currency::convert($cost, 'TRY', 'USD');
        $rate = self::commissionRate();

        if ($rate > 0) {
            $usd += $usd * ($rate / 100);
        }

        return round($usd, 2);
    }

    /**
     * @param float $salePriceUsd
     * @return float
     */
    public static function costTryFromSalePrice($salePriceUsd)
    {
        $price = max(0.0, (float)$salePriceUsd);
        $rate = self::commissionRate();

        if ($rate > 0) {
            $price = $price / (1 + ($rate / 100));
        }

        $costTry = Currency::convert($price, 'USD', 'TRY');

        return round($costTry, 2);
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
        return 'For more information about this service please contact our support team.';
    }

    /**
     * @return string
     */
    public static function currentPath()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($uri, PHP_URL_PATH);

        if (!$path) {
            $path = '/';
        }

        $base = self::basePath();
        if ($base && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base));
            if ($path === '' || $path === false) {
                $path = '/';
            }
        }

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

    /**
     * Detect the most reliable client IP address.
     *
     * @return string|null
     */
    public static function ipAddress()
    {
        $candidates = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');

        foreach ($candidates as $key) {
            if (!isset($_SERVER[$key]) || !$_SERVER[$key]) {
                continue;
            }

            $value = trim((string)$_SERVER[$key]);

            if ($key === 'HTTP_X_FORWARDED_FOR' && strpos($value, ',') !== false) {
                $parts = explode(',', $value);
                $value = trim($parts[0]);
            }

            if (filter_var($value, FILTER_VALIDATE_IP)) {
                return $value;
            }
        }

        return null;
    }
}
