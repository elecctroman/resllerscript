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
require_once __DIR__ . '/lib/url_helpers.php';
require_once __DIR__ . '/lib/cart.php';

use App\Auth;
use App\Settings;
use App\Services\MegaMenuService;

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

if (!function_exists('store_mega_menu')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function store_mega_menu(): array
    {
        static $tree = null;

        if ($tree !== null) {
            return $tree;
        }

        if (!class_exists(MegaMenuService::class)) {
            return array();
        }

        $tree = MegaMenuService::getActiveTree();

        return $tree;
    }
}

if (!function_exists('store_home_showcase_settings')) {
    /**
     * @return array<string,mixed>
     */
    function store_home_showcase_settings(): array
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $default = array(
            'categories' => array(),
            'limit' => 5,
            'sort' => 'custom',
        );

        $raw = get_setting('store_home_showcase', '');
        if (!is_string($raw)) {
            $cached = $default;

            return $cached;
        }

        $decoded = json_decode(trim($raw), true);
        if (!is_array($decoded)) {
            $cached = $default;

            return $cached;
        }

        $categories = array();
        if (isset($decoded['categories']) && is_array($decoded['categories'])) {
            $seen = array();
            foreach ($decoded['categories'] as $row) {
                if (!is_array($row) || !isset($row['id'])) {
                    continue;
                }
                $id = (int) $row['id'];
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $categories[] = array(
                    'id' => $id,
                    'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : (count($categories) + 1),
                );
            }
        }

        $limit = isset($decoded['limit']) ? (int) $decoded['limit'] : 5;
        if ($limit < 1) {
            $limit = 1;
        } elseif ($limit > 20) {
            $limit = 20;
        }

        $sort = isset($decoded['sort']) ? strtolower((string) $decoded['sort']) : 'custom';
        $allowedSorts = array('custom', 'alphabetical', 'latest');
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'custom';
        }

        $cached = array(
            'categories' => $categories,
            'limit' => $limit,
            'sort' => $sort,
        );

        return $cached;
    }
}

if (!function_exists('store_category_icon_key')) {
    /**
     * @param array<string,mixed> $category
     */
    function store_category_icon_key(array $category): string
    {
        $icon = '';

        if (isset($category['icon_key']) && $category['icon_key'] !== '') {
            $icon = strtolower((string) $category['icon_key']);
        }

        if ($icon === '' && isset($category['slug']) && $category['slug'] !== '') {
            $icon = strtolower((string) $category['slug']);
        }

        $map = array(
            'windows' => 'windows',
            'pubg' => 'gamepad',
            'valorant' => 'swords',
            'adobe' => 'adobe',
            'freepik' => 'stars',
            'canva' => 'palette',
            'shutterstock' => 'camera',
            'elementor' => 'layers',
        );

        if ($icon !== '' && isset($map[$icon])) {
            return $map[$icon];
        }

        if ($icon !== '') {
            return preg_replace('/[^a-z0-9-]+/i', '-', $icon);
        }

        return 'default';
    }
}

if (!function_exists('store_homepage_sections')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function store_homepage_sections(): array
    {
        static $sections = null;

        if ($sections !== null) {
            return $sections;
        }

        $sections = array();

        try {
            $pdo = \App\Database::connection();
        } catch (\Throwable $exception) {
            return $sections;
        }

        $settings = store_home_showcase_settings();
        $limit = isset($settings['limit']) ? (int) $settings['limit'] : 5;
        if ($limit < 1) {
            $limit = 1;
        } elseif ($limit > 20) {
            $limit = 20;
        }

        $sort = isset($settings['sort']) ? (string) $settings['sort'] : 'custom';
        $allowedSorts = array('custom', 'alphabetical', 'latest');
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'custom';
        }

        $selected = array();
        if (isset($settings['categories']) && is_array($settings['categories'])) {
            foreach ($settings['categories'] as $row) {
                if (!is_array($row) || !isset($row['id'])) {
                    continue;
                }
                $id = (int) $row['id'];
                if ($id > 0) {
                    $selected[] = $id;
                }
            }
        }

        $selected = array_values(array_unique($selected));

        $categoryRows = array();
        try {
            if ($selected) {
                $placeholders = implode(',', array_fill(0, count($selected), '?'));
                $orderSql = '';
                if ($sort === 'custom') {
                    $orderSql = ' ORDER BY FIELD(c.id,' . implode(',', array_map('intval', $selected)) . ')';
                } elseif ($sort === 'alphabetical') {
                    $orderSql = ' ORDER BY c.name ASC';
                } else {
                    $orderSql = ' ORDER BY c.created_at DESC';
                }
                $stmt = $pdo->prepare('SELECT c.id, c.name, c.slug, c.description, c.icon_key, c.created_at
                    FROM categories c WHERE c.id IN (' . $placeholders . ')' . $orderSql);
                $stmt->execute($selected);
                $categoryRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
            } else {
                $orderSql = $sort === 'alphabetical' ? ' ORDER BY c.name ASC' : ' ORDER BY c.created_at DESC';
                $stmt = $pdo->query('SELECT c.id, c.name, c.slug, c.description, c.icon_key, c.created_at
                    FROM categories c' . $orderSql . ' LIMIT 6');
                if ($stmt !== false) {
                    $categoryRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
                }
            }
        } catch (\PDOException $exception) {
            error_log('[Storefront] Kategoriler yüklenemedi: ' . $exception->getMessage());
            $categoryRows = array();
        }

        if (!$categoryRows) {
            return $sections;
        }

        $currency = get_setting('currency', 'TRY');
        $productOrder = $sort === 'alphabetical' ? 'p.name ASC' : 'p.created_at DESC';

        $productStmt = $pdo->prepare('SELECT p.id, p.name, p.price, p.image, p.automatic_delivery, p.created_at,
                (SELECT COUNT(*) FROM product_stock_items psi WHERE psi.product_id = p.id AND psi.status = "available") AS stock_available
            FROM products p
            WHERE p.status = "active" AND p.category_id = :category_id
            ORDER BY ' . $productOrder . '
            LIMIT :limit');

        foreach ($categoryRows as $category) {
            $categoryId = isset($category['id']) ? (int) $category['id'] : 0;
            if ($categoryId <= 0) {
                continue;
            }

            $products = array();
            try {
                $productStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                $productStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $productStmt->execute();
                $products = $productStmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
            } catch (\PDOException $productException) {
                $products = array();
                error_log('[Storefront] Ürünler yüklenemedi: ' . $productException->getMessage());
            }

            $formattedProducts = array();
            foreach ($products as $product) {
                $productId = isset($product['id']) ? (int) $product['id'] : 0;
                if ($productId <= 0) {
                    continue;
                }

                $productName = isset($product['name']) ? (string) $product['name'] : 'Ürün';
                $productImage = isset($product['image']) ? (string) $product['image'] : '';
                $productUrl = url_product(array('id' => $productId, 'name' => $productName));
                $stockAvailable = isset($product['stock_available']) ? (int) $product['stock_available'] : 0;

                $formattedProducts[] = array(
                    'id' => $productId,
                    'name' => $productName,
                    'price' => isset($product['price']) ? (float) $product['price'] : 0.0,
                    'currency' => $currency,
                    'image' => $productImage,
                    'automatic_delivery' => !empty($product['automatic_delivery']),
                    'stock_available' => $stockAvailable,
                    'url' => $productUrl,
                    'category_name' => isset($category['name']) ? (string) $category['name'] : '',
                );
            }

            if (!$formattedProducts) {
                continue;
            }

            $sections[] = array(
                'category' => array(
                    'id' => $categoryId,
                    'name' => isset($category['name']) ? (string) $category['name'] : 'Kategori',
                    'slug' => isset($category['slug']) ? (string) $category['slug'] : '',
                    'icon_key' => store_category_icon_key($category),
                    'description' => isset($category['description']) ? (string) $category['description'] : '',
                    'url' => url_category($category),
                ),
                'products' => $formattedProducts,
            );
        }

        return $sections;
    }
}

if (!function_exists('store_homepage_groups')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function store_homepage_groups(): array
    {
        return store_mega_menu();
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
