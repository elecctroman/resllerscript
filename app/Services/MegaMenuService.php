<?php declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Helpers;
use PDO;
use PDOException;

final class MegaMenuService
{
    /** @var string */
    private const CACHE_FILE = __DIR__ . '/../../storage/cache/mega_menu_tree.json';

    /** @var int */
    private const CACHE_TTL = 300;

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function getActiveTree(): array
    {
        $cached = self::readCache();
        if ($cached !== null) {
            return $cached;
        }

        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            error_log('[MegaMenuService] Veritabanı bağlantısı alınamadı: ' . $exception->getMessage());

            return array();
        }

        $sql = "SELECT
                    g.id AS group_id,
                    g.name AS group_name,
                    g.slug AS group_slug,
                    g.sort_order AS group_sort,
                    g.is_active AS group_active,
                    i.id AS item_id,
                    i.category_id,
                    i.custom_label,
                    i.custom_url,
                    i.icon_key AS item_icon,
                    i.custom_image,
                    i.custom_image,
                    i.sort_order AS item_sort,
                    i.is_active AS item_active,
                    c.name AS category_name,
                    c.slug AS category_slug,
                    c.icon_key AS category_icon
                FROM mega_menu_groups g
                LEFT JOIN mega_menu_items i ON i.group_id = g.id AND i.is_active = 1
                LEFT JOIN categories c ON c.id = i.category_id
                WHERE g.is_active = 1
                ORDER BY g.sort_order ASC, g.id ASC, i.sort_order ASC, i.id ASC";

        try {
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        } catch (PDOException $exception) {
            error_log('[MegaMenuService] Aktif mega menü okunamadı: ' . $exception->getMessage());

            return array();
        }

        $tree = array();
        foreach ($rows as $row) {
            $groupId = (int) $row['group_id'];
            if (!isset($tree[$groupId])) {
                $tree[$groupId] = array(
                    'id' => $groupId,
                    'name' => (string) $row['group_name'],
                    'slug' => (string) $row['group_slug'],
                    'items' => array(),
                );
            }

            if ($row['item_id'] === null) {
                continue;
            }

            $item = array(
                'id' => (int) $row['item_id'],
                'label' => '',
                'url' => '',
                'icon' => '',
                'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
                'image' => self::resolveImagePath($row['custom_image'] ?? null),
            );

            if ($row['category_id'] !== null) {
                $slug = isset($row['category_slug']) ? (string) $row['category_slug'] : '';
                $categoryId = (int) $row['category_id'];
                $item['label'] = (string) ($row['category_name'] ?? 'Kategori');
                $item['url'] = slugify_category_url($slug, $categoryId);
                $item['icon'] = self::resolveIconKey((string) ($row['item_icon'] ?? ''), (string) ($row['category_icon'] ?? ''));
            } else {
                $item['label'] = (string) ($row['custom_label'] ?? 'Bağlantı');
                $item['url'] = self::sanitizeCustomUrl($row['custom_url']);
                $item['icon'] = self::resolveIconKey((string) ($row['item_icon'] ?? ''), '');
            }

            if ($item['label'] === '' || $item['url'] === '') {
                continue;
            }

            $tree[$groupId]['items'][] = $item;
        }

        $tree = array_values($tree);
        self::writeCache($tree);

        return $tree;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function adminTree(): array
    {
        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return array();
        }

        $sql = "SELECT
                    g.id AS group_id,
                    g.name AS group_name,
                    g.slug AS group_slug,
                    g.sort_order AS group_sort,
                    g.is_active AS group_active,
                    g.created_at AS group_created,
                    g.updated_at AS group_updated,
                    i.id AS item_id,
                    i.category_id,
                    i.custom_label,
                    i.custom_url,
                    i.icon_key AS item_icon,
                    i.sort_order AS item_sort,
                    i.is_active AS item_active,
                    c.name AS category_name,
                    c.slug AS category_slug,
                    c.icon_key AS category_icon
                FROM mega_menu_groups g
                LEFT JOIN mega_menu_items i ON i.group_id = g.id
                LEFT JOIN categories c ON c.id = i.category_id
                ORDER BY g.sort_order ASC, g.id ASC, i.sort_order ASC, i.id ASC";

        try {
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        } catch (PDOException $exception) {
            error_log('[MegaMenuService] Yönetim mega menü sorgusu başarısız: ' . $exception->getMessage());

            return array();
        }

        $result = array();
        foreach ($rows as $row) {
            $groupId = (int) $row['group_id'];
            if (!isset($result[$groupId])) {
                $result[$groupId] = array(
                    'id' => $groupId,
                    'name' => (string) $row['group_name'],
                    'slug' => (string) $row['group_slug'],
                    'sort_order' => (int) $row['group_sort'],
                    'is_active' => (int) $row['group_active'] === 1,
                    'items' => array(),
                );
            }

            if ($row['item_id'] === null) {
                continue;
            }

            $result[$groupId]['items'][] = array(
                'id' => (int) $row['item_id'],
                'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
                'custom_label' => $row['custom_label'],
                'custom_url' => $row['custom_url'],
                'icon_key' => $row['item_icon'],
                'custom_image' => $row['custom_image'],
                'sort_order' => (int) $row['item_sort'],
                'is_active' => (int) $row['item_active'] === 1,
                'category_name' => $row['category_name'],
                'category_slug' => $row['category_slug'],
                'category_icon' => $row['category_icon'],
            );
        }

        return array_values($result);
    }

    public static function createGroup(string $name, bool $active = true): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return null;
        }

        $slug = Helpers::slugify($name);
        if ($slug === '') {
            $slug = 'grup';
        }

        $baseSlug = $slug;
        $suffix = 2;
        $check = $pdo->prepare('SELECT COUNT(*) FROM mega_menu_groups WHERE slug = :slug');
        do {
            $check->execute(array('slug' => $slug));
            $exists = (int) $check->fetchColumn() > 0;
            if ($exists) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
            }
        } while ($exists);

        $stmt = $pdo->prepare('INSERT INTO mega_menu_groups (name, slug, sort_order, is_active) VALUES (:name, :slug, :sort, :active)');
        $stmt->execute(array(
            'name' => $name,
            'slug' => $slug,
            'sort' => (int) self::nextGroupSort($pdo),
            'active' => $active ? 1 : 0,
        ));

        self::flushCache();

        return (int) $pdo->lastInsertId();
    }

    public static function updateGroup(int $id, string $name, bool $active): bool
    {
        if ($id <= 0) {
            return false;
        }

        $name = trim($name);
        if ($name === '') {
            return false;
        }

        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return false;
        }

        $stmt = $pdo->prepare('UPDATE mega_menu_groups SET name = :name, is_active = :active, updated_at = NOW() WHERE id = :id');
        $stmt->execute(array(
            'name' => $name,
            'active' => $active ? 1 : 0,
            'id' => $id,
        ));

        self::flushCache();

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<int,array<string,int>> $orders
     */
    public static function sortGroups(array $orders): void
    {
        if (!$orders) {
            return;
        }

        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return;
        }

        $stmt = $pdo->prepare('UPDATE mega_menu_groups SET sort_order = :sort, updated_at = NOW() WHERE id = :id');
        foreach ($orders as $order) {
            if (!isset($order['id'], $order['sort_order'])) {
                continue;
            }
            $stmt->execute(array('id' => (int) $order['id'], 'sort' => (int) $order['sort_order']));
        }

        self::flushCache();
    }

    public static function deleteGroup(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return false;
        }

        $stmt = $pdo->prepare('DELETE FROM mega_menu_groups WHERE id = :id');
        $stmt->execute(array('id' => $id));

        $deleted = $stmt->rowCount() > 0;
        if ($deleted) {
            self::flushCache();
        }

        return $deleted;
    }

    public static function toggleGroup(int $id, bool $active): bool
    {
        if ($id <= 0) {
            return false;
        }

        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return false;
        }

        $stmt = $pdo->prepare('UPDATE mega_menu_groups SET is_active = :active, updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id, 'active' => $active ? 1 : 0));

        $changed = $stmt->rowCount() > 0;
        if ($changed) {
            self::flushCache();
        }

        return $changed;
    }

    public static function createItem(array $payload): ?int
    {
        $groupId = isset($payload['group_id']) ? (int) $payload['group_id'] : 0;
        if ($groupId <= 0) {
            return null;
        }

        $categoryId = isset($payload['category_id']) && $payload['category_id'] !== ''
            ? (int) $payload['category_id']
            : null;

        $customLabel = isset($payload['custom_label']) ? trim((string) $payload['custom_label']) : '';
        $customUrl = isset($payload['custom_url']) ? trim((string) $payload['custom_url']) : '';

        if ($categoryId === null && ($customLabel === '' || $customUrl === '')) {
            return null;
        }

        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return null;
        }

        $iconKey = isset($payload['icon_key']) ? trim((string) $payload['icon_key']) : '';
        $isActive = isset($payload['is_active']) ? (bool) $payload['is_active'] : true;
        $customImage = isset($payload['custom_image']) && $payload['custom_image'] !== ''
            ? (string) $payload['custom_image']
            : null;

        $stmt = $pdo->prepare('INSERT INTO mega_menu_items (group_id, category_id, custom_label, custom_url, icon_key, custom_image, sort_order, is_active)
            VALUES (:group_id, :category_id, :custom_label, :custom_url, :icon_key, :custom_image, :sort_order, :is_active)');
        $stmt->execute(array(
            'group_id' => $groupId,
            'category_id' => $categoryId,
            'custom_label' => $categoryId !== null ? null : ($customLabel !== '' ? $customLabel : null),
            'custom_url' => $categoryId !== null ? null : ($customUrl !== '' ? self::sanitizeCustomUrl($customUrl) : null),
            'icon_key' => $iconKey !== '' ? $iconKey : null,
            'custom_image' => $customImage,
            'sort_order' => self::nextItemSort($pdo, $groupId),
            'is_active' => $isActive ? 1 : 0,
        ));

        self::flushCache();

        return (int) $pdo->lastInsertId();
    }

    public static function updateItem(int $id, array $payload): bool
    {
        if ($id <= 0) {
            return false;
        }

        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return false;
        }

        $categoryId = isset($payload['category_id']) && $payload['category_id'] !== ''
            ? (int) $payload['category_id']
            : null;
        $customLabel = isset($payload['custom_label']) ? trim((string) $payload['custom_label']) : '';
        $customUrl = isset($payload['custom_url']) ? trim((string) $payload['custom_url']) : '';
        $iconKey = isset($payload['icon_key']) ? trim((string) $payload['icon_key']) : '';
        $isActive = isset($payload['is_active']) ? (bool) $payload['is_active'] : true;

        $existingImage = self::lookupItemImage($pdo, $id);
        $customImageValue = $existingImage;
        $customImageChanged = false;

        if (array_key_exists('custom_image', $payload)) {
            $incomingImage = $payload['custom_image'];
            if ($incomingImage === null || $incomingImage === '') {
                if ($existingImage !== null && $existingImage !== '') {
                    $customImageValue = null;
                    $customImageChanged = true;
                }
            } else {
                $incomingImage = (string) $incomingImage;
                if ($incomingImage !== $existingImage) {
                    $customImageValue = $incomingImage;
                    $customImageChanged = true;
                }
            }
        }

        if ($categoryId === null && ($customLabel === '' || $customUrl === '')) {
            return false;
        }

        $stmt = $pdo->prepare('UPDATE mega_menu_items SET category_id = :category_id, custom_label = :custom_label, custom_url = :custom_url,
            icon_key = :icon_key, custom_image = :custom_image, is_active = :is_active, updated_at = NOW() WHERE id = :id');
        $stmt->execute(array(
            'category_id' => $categoryId,
            'custom_label' => $categoryId !== null ? null : ($customLabel !== '' ? $customLabel : null),
            'custom_url' => $categoryId !== null ? null : ($customUrl !== '' ? self::sanitizeCustomUrl($customUrl) : null),
            'icon_key' => $iconKey !== '' ? $iconKey : null,
            'custom_image' => $customImageValue,
            'is_active' => $isActive ? 1 : 0,
            'id' => $id,
        ));

        $updated = $stmt->rowCount() > 0 || $customImageChanged;
        if ($updated) {
            self::flushCache();
        }

        if ($customImageChanged && $existingImage && $existingImage !== $customImageValue) {
            self::deleteImageFile($existingImage);
        }

        return $updated;
    }

    /**
     * @param array<int,array<string,int>> $orders
     */
    public static function sortItems(int $groupId, array $orders): void
    {
        if ($groupId <= 0 || !$orders) {
            return;
        }

        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return;
        }

        $stmt = $pdo->prepare('UPDATE mega_menu_items SET sort_order = :sort, updated_at = NOW() WHERE id = :id AND group_id = :group_id');
        foreach ($orders as $order) {
            if (!isset($order['id'], $order['sort_order'])) {
                continue;
            }
            $stmt->execute(array(
                'id' => (int) $order['id'],
                'group_id' => $groupId,
                'sort' => (int) $order['sort_order'],
            ));
        }

        self::flushCache();
    }

    public static function toggleItem(int $id, bool $active): bool
    {
        if ($id <= 0) {
            return false;
        }

        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return false;
        }

        $stmt = $pdo->prepare('UPDATE mega_menu_items SET is_active = :active, updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id, 'active' => $active ? 1 : 0));

        $changed = $stmt->rowCount() > 0;
        if ($changed) {
            self::flushCache();
        }

        return $changed;
    }

    public static function deleteItem(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return false;
        }

        $stmt = $pdo->prepare('DELETE FROM mega_menu_items WHERE id = :id');
        $stmt->execute(array('id' => $id));

        $deleted = $stmt->rowCount() > 0;
        if ($deleted) {
            self::flushCache();
        }

        return $deleted;
    }

    public static function flushCache(): void
    {
        static $cached = false;
        $cached = false;

        $path = self::cacheFilePath();
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @return array<int,array<string,mixed>>|null
     */
    private static function readCache(): ?array
    {
        static $memoryCache = null;

        if ($memoryCache !== null) {
            return $memoryCache;
        }

        $path = self::cacheFilePath();
        if ($path === '' || !is_file($path)) {
            return null;
        }

        if ((int) @filemtime($path) + self::CACHE_TTL < time()) {
            @unlink($path);
            return null;
        }

        $json = @file_get_contents($path);
        if (!is_string($json) || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $memoryCache = $decoded;

        return $decoded;
    }

    /**
     * @param array<int,array<string,mixed>> $data
     */
    private static function writeCache(array $data): void
    {
        $path = self::cacheFilePath();
        if ($path === '') {
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        @file_put_contents($path, json_encode($data));
    }

    private static function cacheFilePath(): string
    {
        return self::CACHE_FILE;
    }

    private static function nextGroupSort(PDO $pdo): int
    {
        try {
            $value = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM mega_menu_groups')->fetchColumn();

            return $value;
        } catch (PDOException $exception) {
            return 0;
        }
    }

    private static function nextItemSort(PDO $pdo, int $groupId): int
    {
        try {
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM mega_menu_items WHERE group_id = :group_id');
            $stmt->execute(array('group_id' => $groupId));

            return (int) $stmt->fetchColumn();
        } catch (PDOException $exception) {
            return 0;
        }
    }

    /**
     * @param array|null $file
     * @return array{status:string,message?:string,path?:string}
     */
    public static function handleItemImageUpload($file): array
    {
        if (!is_array($file) || !isset($file['error'])) {
            return array('status' => 'empty');
        }

        $errorCode = (int) $file['error'];
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return array('status' => 'empty');
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            return array('status' => 'error', 'message' => 'Görsel yüklenemedi (hata kodu ' . $errorCode . ').');
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return array('status' => 'error', 'message' => 'Geçersiz görsel yüklemesi.');
        }

        $tmpName = (string) $file['tmp_name'];
        $fileSize = isset($file['size']) ? (int) $file['size'] : 0;
        if ($fileSize <= 0) {
            return array('status' => 'error', 'message' => 'Görsel dosyası boş görünüyor.');
        }

        $maxSize = 4 * 1024 * 1024; // 4 MB
        if ($fileSize > $maxSize) {
            return array('status' => 'error', 'message' => 'Görsel dosyası 4 MB boyutunu aşamaz.');
        }

        $detectedMime = self::detectMimeType($file, $tmpName);
        $allowedMimes = array(
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/webp' => 'webp',
        );

        $extension = '';
        if ($detectedMime !== '' && isset($allowedMimes[$detectedMime])) {
            $extension = $allowedMimes[$detectedMime];
        } else {
            $originalExtension = isset($file['name']) ? strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION)) : '';
            if ($originalExtension === 'jpeg') {
                $originalExtension = 'jpg';
            }
            if (in_array($originalExtension, array('png', 'jpg', 'webp'), true)) {
                $extension = $originalExtension;
            }
        }

        if ($extension === '') {
            return array('status' => 'error', 'message' => 'Yalnızca PNG, JPG veya WebP formatındaki görseller desteklenir.');
        }

        $targetDirectory = self::absolutePath(self::IMAGE_DIRECTORY);
        if (!self::ensureDirectory($targetDirectory)) {
            return array('status' => 'error', 'message' => 'Görsel klasörü oluşturulamadı.');
        }

        $fileName = self::generateFileName($extension);
        $destination = rtrim($targetDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($tmpName, $destination)) {
            return array('status' => 'error', 'message' => 'Görsel kaydedilirken hata oluştu.');
        }

        $storedPath = rtrim(self::IMAGE_DIRECTORY, '/') . '/' . $fileName;

        return array(
            'status' => 'success',
            'path' => $storedPath,
        );
    }

    private static function lookupItemImage(PDO $pdo, int $id): ?string
    {
        try {
            $stmt = $pdo->prepare('SELECT custom_image FROM mega_menu_items WHERE id = :id LIMIT 1');
            $stmt->execute(array('id' => $id));
            $value = $stmt->fetchColumn();

            if ($value === false || $value === null) {
                return null;
            }

            $value = trim((string) $value);

            return $value !== '' ? $value : null;
        } catch (PDOException $exception) {
            return null;
        }
    }

    private static function deleteImageFile(?string $path): void
    {
        if (!is_string($path) || $path === '') {
            return;
        }

        $normalized = self::resolveImagePath($path);
        if ($normalized === '' || stripos($normalized, self::IMAGE_DIRECTORY) !== 0) {
            return;
        }

        $absolute = self::absolutePath($normalized);
        if ($absolute === '') {
            return;
        }

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private static function resolveImagePath($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^https?:/i', $value)) {
            return $value;
        }

        if ($value[0] !== '/') {
            $value = rtrim(self::IMAGE_DIRECTORY, '/') . '/' . ltrim($value, '/');
        }

        return $value;
    }

    private static function resolveIconKey(string $itemIcon, string $fallback): string
    {
        $icon = trim($itemIcon);
        if ($icon !== '') {
            return strtolower($icon);
        }

        $fallback = trim($fallback);
        if ($fallback !== '') {
            return strtolower($fallback);
        }

        return 'folder';
    }

    /**
     * @param mixed $value
     */
    private static function sanitizeCustomUrl($value): string
    {
        $url = trim((string) $value);
        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url) && strpos($url, '/') !== 0) {
            $url = '/' . ltrim($url, '/');
        }

        return $url;
    }

    private static function detectMimeType(array $file, string $tmpName): string
    {
        if (isset($file['type']) && is_string($file['type']) && $file['type'] !== '') {
            return strtolower($file['type']);
        }

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = @finfo_file($finfo, $tmpName);
                @finfo_close($finfo);
                if (is_string($detected) && $detected !== '') {
                    return strtolower($detected);
                }
            }
        }

        return '';
    }

    private static function ensureDirectory(string $directory): bool
    {
        if (is_dir($directory)) {
            return is_writable($directory);
        }

        return @mkdir($directory, 0775, true);
    }

    private static function absolutePath(string $relative): string
    {
        $root = dirname(__DIR__, 2);
        $relative = trim($relative);
        if ($relative === '') {
            return $root;
        }

        $relative = str_replace(array('\', '/'), DIRECTORY_SEPARATOR, $relative);

        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
    }

    private static function generateFileName(string $extension): string
    {
        $base = 'mega-menu';

        try {
            $random = bin2hex(random_bytes(6));
        } catch (\Throwable $exception) {
            $random = substr(md5((string) microtime(true)), 0, 12);
        }

        return $base . '-' . date('YmdHis') . '-' . $random . '.' . $extension;
    }
}

if (!function_exists('slugify_category_url')) {
    function slugify_category_url(string $slug, int $id): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            $slug = 'kategori';
        }

        $path = 'kategori/' . rawurlencode($slug) . '/' . $id;

        if (function_exists('store_url')) {
            return store_url($path);
        }

        return '/' . ltrim($path, '/');
    }
}
