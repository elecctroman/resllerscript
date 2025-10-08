<?php
/**
 * Storefront bootstrap.
 *
 * How to add a new theme: copy themes/store/default to themes/store/<new-name>,
 * edit theme.json as needed, then update the store_active_theme setting in the
 * admin panel (Ayarlar → Mağaza Ayarları) or set STORE_ACTIVE_THEME in the
 * environment. The system will automatically fall back to the "default" theme
 * whenever a requested theme is missing.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/config.php';

use App\Auth;
use App\Settings;

if (!function_exists('get_setting')) {
    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function get_setting($key, $default = '')
    {
        $value = Settings::get($key);

        if ($value === null) {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('set_setting')) {
    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    function set_setting($key, $value)
    {
        Settings::set($key, $value);
    }
}

if (!function_exists('settings_cache_invalidate')) {
    /**
     * @param string|array<int,string>|null $keys
     * @return void
     */
    function settings_cache_invalidate($keys = null)
    {
        Settings::clearCache($keys);
    }
}

if (!function_exists('is_admin')) {
    /**
     * @return bool
     */
    function is_admin()
    {
        if (class_exists(Auth::class)) {
            return Auth::hasRole('admin');
        }

        return false;
    }
}

if (!function_exists('is_reseller')) {
    /**
     * @return bool
     */
    function is_reseller()
    {
        if (class_exists(Auth::class)) {
            return Auth::hasRole('reseller');
        }

        return false;
    }
}

if (!function_exists('is_customer')) {
    /**
     * @return bool
     */
    function is_customer()
    {
        if (class_exists(Auth::class)) {
            return Auth::check();
        }

        return isset($_SESSION['customer_id']);
    }
}

if (!function_exists('store_base_url')) {
    /**
     * @return string
     */
    function store_base_url()
    {
        $base = envStr('STORE_BASE_URL', '/');
        $base = $base !== null ? trim($base) : '';

        if ($base === '') {
            $base = '/';
        }

        if ($base !== '/' && substr($base, -1) === '/') {
            $base = rtrim($base, '/');
        }

        return $base;
    }
}

if (!function_exists('store_url')) {
    /**
     * @param string $path
     * @return string
     */
    function store_url($path = '')
    {
        $base = store_base_url();
        $path = (string) $path;

        if ($base === '/') {
            return $path === '' ? '/' : '/' . ltrim($path, '/');
        }

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('store_setting_array')) {
    /**
     * @param string $key
     * @param array<int,mixed> $default
     * @return array<int,mixed>
     */
    function store_setting_array($key, array $default = array())
    {
        $raw = get_setting($key, '');
        if (!is_string($raw)) {
            return $default;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return $default;
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return $default;
        }

        return $decoded;
    }
}

if (!function_exists('store_media_url')) {
    /**
     * @param string $path
     * @param string $fallback
     * @return string
     */
    function store_media_url($path, $fallback = '')
    {
        $path = trim((string) $path);
        if ($path === '') {
            return $fallback;
        }

        if (preg_match('/^https?:/i', $path)) {
            return $path;
        }

        if ($path[0] === '/') {
            return store_url(ltrim($path, '/'));
        }

        return store_url($path);
    }
}

if (!function_exists('admin_base_url')) {
    /**
     * @return string
     */
    function admin_base_url()
    {
        $appBase = envStr('APP_BASE_URL', '');
        $appBase = $appBase !== null ? trim($appBase) : '';
        $admin = envStr('ADMIN_BASE_URL', '');
        $admin = $admin !== null ? trim($admin) : '';

        if ($admin === '' && $appBase !== '') {
            $admin = rtrim($appBase, '/') . '/admin';
        }

        if ($admin === '') {
            $admin = '/admin';
        }

        return $admin;
    }
}

if (!function_exists('reseller_base_url')) {
    /**
     * @return string
     */
    function reseller_base_url()
    {
        $appBase = envStr('APP_BASE_URL', '');
        $appBase = $appBase !== null ? trim($appBase) : '';
        $reseller = envStr('BAYI_BASE_URL', '');
        $reseller = $reseller !== null ? trim($reseller) : '';

        if ($reseller === '' && $appBase !== '') {
            $reseller = rtrim($appBase, '/') . '/bayi';
        }

        if ($reseller === '') {
            $reseller = '/bayi';
        }

        return $reseller;
    }
}

if (!function_exists('store_themes_path')) {
    /**
     * @return string
     */
    function store_themes_path()
    {
        return __DIR__ . '/../themes/store';
    }
}

if (!function_exists('store_sanitize_theme_name')) {
    /**
     * @param string $name
     * @return string
     */
    function store_sanitize_theme_name($name)
    {
        $name = trim((string) $name);
        if ($name === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            return '';
        }

        return $name;
    }
}

if (!function_exists('store_active_theme')) {
    /**
     * @return string
     */
    function store_active_theme()
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $candidates = array();
        $dbTheme = get_setting('store_active_theme');
        if ($dbTheme) {
            $candidates[] = $dbTheme;
        }

        $envTheme = envStr('STORE_ACTIVE_THEME');
        if ($envTheme) {
            $candidates[] = $envTheme;
        }

        if (isset($GLOBALS['STORE_ACTIVE_THEME'])) {
            $candidates[] = $GLOBALS['STORE_ACTIVE_THEME'];
        }

        $candidates[] = 'default';

        $themesDir = store_themes_path();

        foreach ($candidates as $candidate) {
            $candidate = store_sanitize_theme_name($candidate);
            if ($candidate === '') {
                continue;
            }

            if (is_dir($themesDir . '/' . $candidate)) {
                $cached = $candidate;
                return $cached;
            }
        }

        $cached = 'default';

        return $cached;
    }
}

if (!function_exists('theme_root')) {
    /**
     * @param string|null $theme
     * @return string
     */
    function theme_root($theme = null)
    {
        $theme = $theme ? store_sanitize_theme_name($theme) : store_active_theme();
        if ($theme === '') {
            $theme = 'default';
        }

        $themesDir = store_themes_path();
        $path = $themesDir . '/' . $theme;

        if (!is_dir($path)) {
            $path = $themesDir . '/default';
        }

        return $path;
    }
}

if (!function_exists('theme_view')) {
    /**
     * @param string $view
     * @return string
     */
    function theme_view($view)
    {
        $clean = trim(str_replace('..', '', str_replace('\\', '/', $view)), '/');
        if ($clean === '') {
            return '';
        }

        $path = theme_root() . '/views/' . $clean . '.php';
        if (is_file($path)) {
            return $path;
        }

        $fallback = store_themes_path() . '/default/views/' . $clean . '.php';
        if (is_file($fallback)) {
            return $fallback;
        }

        return '';
    }
}

if (!function_exists('theme_partial')) {
    /**
     * @param string $name
     * @return string
     */
    function theme_partial($name)
    {
        $clean = trim(str_replace('..', '', str_replace('\\', '/', $name)), '/');
        if ($clean === '') {
            return '';
        }

        $path = theme_root() . '/partials/' . $clean . '.php';
        if (is_file($path)) {
            return $path;
        }

        $fallback = store_themes_path() . '/default/partials/' . $clean . '.php';
        if (is_file($fallback)) {
            return $fallback;
        }

        return '';
    }
}

if (!function_exists('theme_layout')) {
    /**
     * @return string
     */
    function theme_layout()
    {
        $path = theme_root() . '/layout.php';
        if (is_file($path)) {
            return $path;
        }

        $fallback = store_themes_path() . '/default/layout.php';
        if (is_file($fallback)) {
            return $fallback;
        }

        return '';
    }
}

if (!function_exists('theme_asset')) {
    /**
     * @param string $asset
     * @return string
     */
    function theme_asset($asset)
    {
        $asset = ltrim(str_replace('..', '', str_replace('\\', '/', (string) $asset)), '/');
        if ($asset === '') {
            return '';
        }

        $active = store_active_theme();
        $activePath = theme_root($active) . '/assets/' . $asset;
        if (is_file($activePath)) {
            return store_url('themes/store/' . $active . '/assets/' . $asset);
        }

        $defaultPath = theme_root('default') . '/assets/' . $asset;
        if (is_file($defaultPath)) {
            return store_url('themes/store/default/assets/' . $asset);
        }

        if (preg_match('/\.(png|jpe?g|gif|svg|webp)$/i', $asset)) {
            return store_url('themes/store/default/assets/img/placeholder-16x9.svg');
        }

        return '';
    }
}

if (!function_exists('theme_manifest')) {
    /**
     * @return array<string,mixed>
     */
    function theme_manifest()
    {
        static $manifest = null;

        if ($manifest !== null) {
            return $manifest;
        }

        $manifestFile = theme_root() . '/theme.json';
        if (!is_file($manifestFile)) {
            $manifestFile = store_themes_path() . '/default/theme.json';
        }

        if (is_file($manifestFile)) {
            $json = file_get_contents($manifestFile);
            $data = json_decode($json, true);
            if (is_array($data)) {
                $manifest = $data;
                return $manifest;
            }
        }

        $manifest = array();

        return $manifest;
    }
}

if (!function_exists('theme_enqueue_assets')) {
    /**
     * @return void
     */
    function theme_enqueue_assets()
    {
        $manifest = theme_manifest();

        foreach ($manifest['assets']['css'] ?? array() as $css) {
            $href = theme_asset($css);
            if ($href !== '') {
                echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
            }
        }

        foreach ($manifest['assets']['js'] ?? array() as $js) {
            $src = theme_asset($js);
            if ($src !== '') {
                echo '<script defer src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>';
            }
        }
    }
}

if (!function_exists('money_format_try')) {
    /**
     * @param float|int|string $amount
     * @param string|null $currency
     * @return string
     */
    function money_format_try($amount, $currency = null)
    {
        if (!is_numeric($amount)) {
            $amount = 0;
        }

        if ($currency === null || $currency === '') {
            $currency = (string) get_setting('currency', 'TRY');
        }

        $symbol = '₺';
        $upperCurrency = strtoupper((string) $currency);
        $symbols = array(
            'TRY' => '₺',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        );

        if (isset($symbols[$upperCurrency])) {
            $symbol = $symbols[$upperCurrency];
        }

        $formatted = number_format((float) $amount, 2, ',', '.');

        return $symbol . ' ' . $formatted;
    }
}

if (!function_exists('seo_meta')) {
    /**
     * @param string $title
     * @param string $description
     * @return string
     */
    function seo_meta($title = '', $description = '')
    {
        $siteName = trim((string) get_setting('site_name', 'OyunHesap.com.tr'));
        if ($siteName === '') {
            $siteName = 'OyunHesap.com.tr';
        }

        $configuredTitle = trim((string) get_setting('seo_title', ''));
        $baseTitle = $configuredTitle !== '' ? $configuredTitle : $siteName;
        $fullTitle = $title !== '' ? $title . ' | ' . $baseTitle : $baseTitle;

        $defaultDesc = trim((string) get_setting('seo_description', 'En sevilen dijital oyun hesapları ve yazılım lisansları.'));
        if ($defaultDesc === '') {
            $defaultDesc = 'En sevilen dijital oyun hesapları ve yazılım lisansları.';
        }

        $desc = $description !== '' ? $description : $defaultDesc;

        return '<title>' . htmlspecialchars($fullTitle, ENT_QUOTES, 'UTF-8') . '</title>' . "\n"
            . '<meta name="description" content="' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('store_render')) {
    /**
     * @param string $view
     * @param array<string,mixed> $data
     * @return void
     */
    function store_render($view, array $data = array())
    {
        $maintenance = (int) get_setting('maintenance_mode', '0') === 1;
        if ($maintenance && !is_admin()) {
            $view = 'maintenance';
        }

        $context = (object) array(
            'view' => $view,
            'viewPath' => theme_view($view),
            'data' => $data,
        );

        $layout = theme_layout();
        if ($layout === '' || !is_file($layout)) {
            echo '<p>Theme layout bulunamadı.</p>';
            return;
        }

        $storeViewContext = $context;
        include $layout;
    }
}

if (!function_exists('store_include')) {
    /**
     * @param string $file
     * @param array<string,mixed> $data
     * @return void
     */
    function store_include($file, array $data = array())
    {
        if (!is_file($file)) {
            return;
        }

        if ($data) {
            extract($data, EXTR_SKIP);
        }

        include $file;
    }
}

if (!function_exists('list_themes')) {
    /**
     * @return array<int,array<string,string>>
     */
    function list_themes()
    {
        $themes = array();
        $dir = store_themes_path();
        if (!is_dir($dir)) {
            return $themes;
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return $themes;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $slug = store_sanitize_theme_name($entry);
            if ($slug === '') {
                continue;
            }

            $manifestPath = $dir . '/' . $slug . '/theme.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $json = file_get_contents($manifestPath);
            $manifest = json_decode($json, true);
            if (!is_array($manifest)) {
                $manifest = array();
            }

            $themes[] = array(
                'slug' => $slug,
                'name' => isset($manifest['name']) ? (string) $manifest['name'] : $slug,
                'version' => isset($manifest['version']) ? (string) $manifest['version'] : '1.0.0',
                'author' => isset($manifest['author']) ? (string) $manifest['author'] : '',
            );
        }

        return $themes;
    }
}
