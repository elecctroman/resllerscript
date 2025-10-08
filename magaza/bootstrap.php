<?php
/**
 * Storefront theme bootstrap.
 * How to add a new theme: copy magaza/themes/default to magaza/themes/<new-name>,
 * adjust theme.json metadata and assets, then set STORE_ACTIVE_THEME or the
 * store_active_theme setting to the new folder name.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/config.store.php';

use App\Settings;

/**
 * @return string
 */
function store_root()
{
    return __DIR__;
}

/**
 * @param string $name
 * @return string
 */
function store_sanitize_theme_name($name)
{
    $name = trim((string) $name);
    if ($name === '') {
        return '';
    }

    if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
        return '';
    }

    return $name;
}

/**
 * @return string
 */
function store_active_theme()
{
    static $activeTheme = null;

    if ($activeTheme !== null) {
        return $activeTheme;
    }

    $candidates = array();

    if (class_exists(Settings::class)) {
        $dbTheme = Settings::get('store_active_theme');
        if ($dbTheme) {
            $candidates[] = $dbTheme;
        }
    }

    $envTheme = envStr('STORE_ACTIVE_THEME');
    if ($envTheme) {
        $candidates[] = $envTheme;
    }

    if (isset($GLOBALS['STORE_ACTIVE_THEME'])) {
        $candidates[] = $GLOBALS['STORE_ACTIVE_THEME'];
    }

    $candidates[] = 'default';

    foreach ($candidates as $candidate) {
        $candidate = store_sanitize_theme_name($candidate);
        if ($candidate === '') {
            continue;
        }

        $themePath = store_root() . '/themes/' . $candidate;
        if (is_dir($themePath)) {
            $activeTheme = $candidate;
            return $activeTheme;
        }
    }

    $activeTheme = 'default';

    return $activeTheme;
}

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

    $path = store_root() . '/themes/' . $theme;
    if (!is_dir($path)) {
        $path = store_root() . '/themes/default';
    }

    return $path;
}

/**
 * @param string $view
 * @return string
 */
function theme_view($view)
{
    $view = trim($view, '/');
    $view = str_replace('..', '', $view);
    $view = str_replace('\\', '/', $view);

    $path = theme_root() . '/views/' . $view . '.php';
    if (is_file($path)) {
        return $path;
    }

    $fallback = store_root() . '/themes/default/views/' . $view . '.php';
    if (is_file($fallback)) {
        return $fallback;
    }

    return '';
}

/**
 * @param string $name
 * @return string
 */
function theme_partial($name)
{
    $name = trim($name, '/');
    $name = str_replace('..', '', $name);
    $name = str_replace('\\', '/', $name);

    $path = theme_root() . '/partials/' . $name . '.php';
    if (is_file($path)) {
        return $path;
    }

    $fallback = store_root() . '/themes/default/partials/' . $name . '.php';
    if (is_file($fallback)) {
        return $fallback;
    }

    return '';
}

/**
 * @return string
 */
function theme_layout()
{
    $path = theme_root() . '/layout.php';
    if (is_file($path)) {
        return $path;
    }

    $fallback = store_root() . '/themes/default/layout.php';
    if (is_file($fallback)) {
        return $fallback;
    }

    return '';
}

/**
 * @param string $asset
 * @return string
 */
function theme_asset($asset)
{
    $asset = ltrim($asset, '/');
    $asset = str_replace('..', '', $asset);
    $asset = str_replace('\\', '/', $asset);

    $activeTheme = store_active_theme();
    $candidatePath = theme_root($activeTheme) . '/assets/' . $asset;
    if (is_file($candidatePath)) {
        return '/magaza/themes/' . $activeTheme . '/assets/' . $asset;
    }

    $defaultPath = theme_root('default') . '/assets/' . $asset;
    if (is_file($defaultPath)) {
        return '/magaza/themes/default/assets/' . $asset;
    }

    if (preg_match('/\.(png|jpe?g|gif|svg|webp)$/i', $asset)) {
        return '/magaza/themes/default/assets/img/placeholder-16x9.svg';
    }

    return '';
}

/**
 * @return array
 */
function theme_manifest()
{
    static $manifest = null;

    if ($manifest !== null) {
        return $manifest;
    }

    $manifestFile = theme_root() . '/theme.json';
    if (!is_file($manifestFile)) {
        $manifestFile = theme_root('default') . '/theme.json';
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

/**
 * @param string $view
 * @param array $data
 * @return void
 */
function store_render($view, array $data = array())
{
    $context = (object) array(
        'view' => $view,
        'viewPath' => theme_view($view),
        'data' => $data,
        'title' => isset($data['pageTitle']) ? (string) $data['pageTitle'] : 'Mağaza',
    );

    $layout = theme_layout();

    if ($layout === '' || !is_file($layout)) {
        echo '<p>Theme layout bulunamadı.</p>';
        return;
    }

    $storeViewContext = $context;
    include $layout;
}

/**
 * @param string $file
 * @param array $data
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

/**
 * @return array<int,array<string,string>>
 */
function list_themes()
{
    $themes = array();
    $themesDir = store_root() . '/themes';

    if (!is_dir($themesDir)) {
        return $themes;
    }

    $directories = scandir($themesDir);
    if ($directories === false) {
        return $themes;
    }

    foreach ($directories as $directory) {
        if ($directory === '.' || $directory === '..') {
            continue;
        }

        $name = store_sanitize_theme_name($directory);
        if ($name === '') {
            continue;
        }

        $manifestPath = $themesDir . '/' . $name . '/theme.json';
        if (!is_file($manifestPath)) {
            continue;
        }

        $json = file_get_contents($manifestPath);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $data = array();
        }

        $themes[] = array(
            'slug' => $name,
            'name' => isset($data['name']) ? (string) $data['name'] : $name,
            'version' => isset($data['version']) ? (string) $data['version'] : '1.0.0',
            'author' => isset($data['author']) ? (string) $data['author'] : '',
        );
    }

    return $themes;
}
