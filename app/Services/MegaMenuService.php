<?php declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use PDOException;

final class MegaMenuService
{
    private const CACHE_FILE = __DIR__ . '/../../storage/cache/mega_menu_tree.json';
    private const CACHE_TTL = 300;

    /**
     * @return array<int, array<string, mixed>>
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
            error_log('[MegaMenuService] Veritabanı bağlantısı kurulamadı: ' . $exception->getMessage());

            return array();
        }

        $sql = "SELECT
                    g.id AS group_id,
                    g.name AS group_name,
                    g.slug AS group_slug,
                    g.sort_order AS group_sort,
                    i.id AS item_id,
                    i.category_id,
                    i.custom_label,
                    i.custom_url,
                    i.icon_key AS item_icon,
                    i.custom_image,
                    i.sort_order AS item_sort,
                    c.name AS category_name,
                    c.slug AS category_slug,
                    c.icon_key AS category_icon
                FROM mega_menu_groups g
                LEFT JOIN mega_menu_items i ON i.group_id = g.id AND i.is_active = 1
                LEFT JOIN categories c ON c.id = i.category_id
                WHERE g.is_active = 1
                ORDER BY g.sort_order ASC, g.id ASC, i.sort_order ASC, i.id ASC";

        try {
            $statement = $pdo->query($sql);
            $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : array();
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

            $tree[$groupId]['items'][] = self::formatItem($row);
        }

        $tree = array_values($tree);
        self::writeCache($tree);

        return $tree;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function adminTree(): array
    {
        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            error_log('[MegaMenuService] Yönetim mega menü bağlantısı kurulamadı: ' . $exception->getMessage());

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
            $statement = $pdo->query($sql);
            $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : array();
        } catch (PDOException $exception) {
            error_log('[MegaMenuService] Yönetim mega menü sorgusu başarısız: ' . $exception->getMessage());

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
                    'sort_order' => (int) $row['group_sort'],
                    'is_active' => (int) $row['group_active'] === 1,
                    'items' => array(),
                );
            }

            if ($row['item_id'] === null) {
                continue;
            }

            $tree[$groupId]['items'][] = self::formatAdminItem($row);
        }

        return array_values($tree);
    }

    public static function createGroup(string $name, bool $active = true): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Grup adı boş olamaz.');
        }

        $slug = self::generateUniqueSlug($name);

        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO mega_menu_groups (name, slug, sort_order, is_active) VALUES (:name, :slug, :sort_order, :is_active)');
        $sortOrder = self::nextGroupSortOrder($pdo);
        $stmt->execute(array(
            ':name' => $name,
            ':slug' => $slug,
            ':sort_order' => $sortOrder,
            ':is_active' => $active ? 1 : 0,
        ));

        self::flushCache();
    }

    public static function updateGroup(int $groupId, string $name, bool $active): void
    {
        if ($groupId <= 0) {
            return;
        }

        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Grup adı boş olamaz.');
        }

        $pdo = Database::connection();
        $current = self::findGroup($pdo, $groupId);
        if (!$current) {
            return;
        }

        $slug = (string) $current['slug'];
        if (strtolower((string) $current['name']) !== strtolower($name)) {
            $slug = self::generateUniqueSlug($name, $groupId);
        }

        $stmt = $pdo->prepare('UPDATE mega_menu_groups SET name = :name, slug = :slug, is_active = :active, updated_at = NOW() WHERE id = :id');
        $stmt->execute(array(
            ':name' => $name,
            ':slug' => $slug,
            ':active' => $active ? 1 : 0,
            ':id' => $groupId,
        ));

        self::flushCache();
    }

    public static function deleteGroup(int $groupId): void
    {
        if ($groupId <= 0) {
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM mega_menu_groups WHERE id = :id');
        $stmt->execute(array(':id' => $groupId));

        self::flushCache();
    }

    /**
     * @param array<int,array{id:int,sort_order:int}> $orders
     */
    public static function sortGroups(array $orders): void
    {
        if (!$orders) {
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE mega_menu_groups SET sort_order = :sort_order, updated_at = NOW() WHERE id = :id');
        foreach ($orders as $row) {
            $stmt->execute(array(
                ':sort_order' => (int) $row['sort_order'],
                ':id' => (int) $row['id'],
            ));
        }

        self::flushCache();
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function createItem(array $payload): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO mega_menu_items (group_id, category_id, custom_label, custom_url, icon_key, custom_image, sort_order, is_active)
            VALUES (:group_id, :category_id, :custom_label, :custom_url, :icon_key, :custom_image, :sort_order, :is_active)');
        $sortOrder = self::nextItemSortOrder($pdo, (int) ($payload['group_id'] ?? 0));
        $stmt->execute(array(
            ':group_id' => (int) ($payload['group_id'] ?? 0),
            ':category_id' => $payload['category_id'] !== null ? (int) $payload['category_id'] : null,
            ':custom_label' => self::sanitizeLabel((string) ($payload['custom_label'] ?? '')),
            ':custom_url' => self::sanitizeUrl((string) ($payload['custom_url'] ?? '')),
            ':icon_key' => self::sanitizeIcon((string) ($payload['icon_key'] ?? '')),
            ':custom_image' => self::sanitizeImagePath($payload['custom_image'] ?? null),
            ':sort_order' => $sortOrder,
            ':is_active' => !empty($payload['is_active']) ? 1 : 0,
        ));

        self::flushCache();
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function updateItem(int $itemId, array $payload): void
    {
        if ($itemId <= 0) {
            return;
        }

        $pdo = Database::connection();
        $fields = array(
            'category_id = :category_id',
            'custom_label = :custom_label',
            'custom_url = :custom_url',
            'icon_key = :icon_key',
            'is_active = :is_active',
        );
        $params = array(
            ':category_id' => $payload['category_id'] !== null ? (int) $payload['category_id'] : null,
            ':custom_label' => self::sanitizeLabel((string) ($payload['custom_label'] ?? '')),
            ':custom_url' => self::sanitizeUrl((string) ($payload['custom_url'] ?? '')),
            ':icon_key' => self::sanitizeIcon((string) ($payload['icon_key'] ?? '')),
            ':is_active' => !empty($payload['is_active']) ? 1 : 0,
            ':id' => $itemId,
        );

        if (array_key_exists('custom_image', $payload)) {
            $fields[] = 'custom_image = :custom_image';
            $params[':custom_image'] = self::sanitizeImagePath($payload['custom_image']);
        }

        $sql = 'UPDATE mega_menu_items SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        self::flushCache();
    }

    public static function deleteItem(int $itemId): void
    {
        if ($itemId <= 0) {
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM mega_menu_items WHERE id = :id');
        $stmt->execute(array(':id' => $itemId));

        self::flushCache();
    }

    /**
     * @param array<int,array{id:int,sort_order:int}> $orders
     */
    public static function sortItems(int $groupId, array $orders): void
    {
        if ($groupId <= 0 || !$orders) {
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE mega_menu_items SET sort_order = :sort_order, updated_at = NOW() WHERE id = :id AND group_id = :group_id');
        foreach ($orders as $row) {
            $stmt->execute(array(
                ':sort_order' => (int) $row['sort_order'],
                ':id' => (int) $row['id'],
                ':group_id' => $groupId,
            ));
        }

        self::flushCache();
    }

    /**
     * @param array<string,mixed>|null $file
     * @return array{status:string,message?:string,path?:string}
     */
    public static function handleItemImageUpload(?array $file): array
    {
        if ($file === null || !isset($file['tmp_name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return array('status' => 'empty');
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return array('status' => 'error', 'message' => 'Dosya yükleme hatası.');
        }

        $tmpName = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmpName)) {
            return array('status' => 'error', 'message' => 'Geçersiz dosya yüklemesi.');
        }

        $allowedMime = array('image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg');
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $detected = $finfo ? finfo_file($finfo, $tmpName) : null;
        if ($finfo) {
            finfo_close($finfo);
        }
        $mime = is_string($detected) ? strtolower($detected) : strtolower((string) ($file['type'] ?? ''));
        $extension = $allowedMime[$mime] ?? null;
        if ($extension === null) {
            return array('status' => 'error', 'message' => 'Desteklenmeyen dosya formatı.');
        }

        $targetDir = self::uploadsDirectory();
        if (!self::ensureDirectory($targetDir)) {
            return array('status' => 'error', 'message' => 'Yükleme klasörü oluşturulamadı.');
        }

        $filename = self::generateFileName($extension);
        $targetPath = $targetDir . '/' . $filename;
        if (!@move_uploaded_file($tmpName, $targetPath)) {
            return array('status' => 'error', 'message' => 'Dosya taşınamadı.');
        }

        return array(
            'status' => 'success',
            'path' => '/uploads/mega-menu/' . $filename,
        );
    }

    public static function flushCache(): void
    {
        $file = self::cacheFilePath();
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private static function formatItem(array $row): array
    {
        $label = $row['custom_label'] !== null && $row['custom_label'] !== ''
            ? (string) $row['custom_label']
            : (string) ($row['category_name'] ?? '');
        $url = self::resolveItemUrl($row);

        return array(
            'id' => (int) $row['item_id'],
            'label' => $label,
            'url' => $url,
            'icon' => self::resolveIcon($row['item_icon'] ?? $row['category_icon'] ?? null),
            'image' => self::resolveImagePath($row['custom_image'] ?? null),
        );
    }

    private static function formatAdminItem(array $row): array
    {
        return array(
            'id' => (int) $row['item_id'],
            'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
            'category_name' => $row['category_name'],
            'category_slug' => $row['category_slug'],
            'custom_label' => $row['custom_label'],
            'custom_url' => $row['custom_url'],
            'icon_key' => $row['item_icon'],
            'custom_image' => $row['custom_image'],
            'sort_order' => (int) $row['item_sort'],
            'is_active' => (int) $row['item_active'] === 1,
        );
    }

    private static function resolveItemUrl(array $row): string
    {
        if (!empty($row['custom_url'])) {
            return (string) $row['custom_url'];
        }

        if ($row['category_slug'] !== null) {
            return '/kategori/' . rawurlencode((string) $row['category_slug']) . '/' . (int) $row['category_id'];
        }

        if ($row['category_id'] !== null) {
            return '/kategori/' . (int) $row['category_id'];
        }

        return '#';
    }

    private static function resolveIcon($iconKey): string
    {
        $iconKey = self::sanitizeIcon((string) $iconKey);
        return $iconKey !== '' ? $iconKey : 'folder';
    }

    private static function resolveImagePath($path): ?string
    {
        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        return $path;
    }

    private static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = self::slugify($name);
        if ($base === '') {
            $base = 'mega-menu';
        }

        $pdo = Database::connection();
        $slug = $base;
        $index = 1;

        while (self::slugExists($pdo, $slug, $ignoreId)) {
            $slug = $base . '-' . ++$index;
        }

        return $slug;
    }

    private static function slugExists(PDO $pdo, string $slug, ?int $ignoreId): bool
    {
        $sql = 'SELECT id FROM mega_menu_groups WHERE slug = :slug';
        $params = array(':slug' => $slug);
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private static function nextGroupSortOrder(PDO $pdo): int
    {
        $value = (int) $pdo->query('SELECT MAX(sort_order) FROM mega_menu_groups')->fetchColumn();

        return $value + 1;
    }

    private static function nextItemSortOrder(PDO $pdo, int $groupId): int
    {
        $stmt = $pdo->prepare('SELECT MAX(sort_order) FROM mega_menu_items WHERE group_id = :group_id');
        $stmt->execute(array(':group_id' => $groupId));
        $value = (int) $stmt->fetchColumn();

        return $value + 1;
    }

    private static function findGroup(PDO $pdo, int $groupId): ?array
    {
        $stmt = $pdo->prepare('SELECT id, name, slug FROM mega_menu_groups WHERE id = :id LIMIT 1');
        $stmt->execute(array(':id' => $groupId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private static function sanitizeLabel(string $value): string
    {
        return trim($value);
    }

    private static function sanitizeUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $value) && strpos($value, '/') !== 0) {
            $value = '/' . ltrim($value, '/');
        }

        return $value;
    }

    private static function sanitizeIcon(string $value): string
    {
        $value = trim($value);
        return strtolower($value);
    }

    /**
     * @param mixed $value
     */
    private static function sanitizeImagePath($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : null;
    }

    private static function slugify(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value);
        $value = trim((string) $value, '-');

        return $value;
    }

    private static function readCache(): ?array
    {
        $file = self::cacheFilePath();
        if (!is_file($file)) {
            return null;
        }

        if (filemtime($file) !== false && (time() - (int) filemtime($file)) > self::CACHE_TTL) {
            @unlink($file);
            return null;
        }

        $json = @file_get_contents($file);
        if (!is_string($json) || $json === '') {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @param array<int,array<string,mixed>> $data
     */
    private static function writeCache(array $data): void
    {
        $file = self::cacheFilePath();
        $dir = dirname($file);
        if (!self::ensureDirectory($dir)) {
            return;
        }

        @file_put_contents($file, json_encode($data));
    }

    private static function ensureDirectory(string $directory): bool
    {
        if (is_dir($directory)) {
            return is_writable($directory);
        }

        return @mkdir($directory, 0775, true);
    }

    private static function uploadsDirectory(): string
    {
        return rtrim(__DIR__ . '/../../uploads/mega-menu', '/');
    }

    private static function cacheFilePath(): string
    {
        return self::CACHE_FILE;
    }

    private static function generateFileName(string $extension): string
    {
        return 'mega-menu-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    }
}
