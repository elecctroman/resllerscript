<?php declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Helpers;
use App\Settings;
use App\Services\AnnouncementService;
use PDO;
use PDOException;
use RuntimeException;

final class ProviderSyncService
{
    private const PROVIDER_NAME = 'WooCommerce Sağlayıcı';
    private const DEFAULT_ROOT_CATEGORY = 'Sağlayıcı Ürünleri';
    private const MAX_PAGES = 10;
    private const PER_PAGE = 50;

    /**
     * @return array<string,mixed>
     */
    public static function getSource(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM provider_sources ORDER BY id ASC LIMIT 1');
        $source = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($source) {
            return $source;
        }

        $insert = $pdo->prepare('INSERT INTO provider_sources (name, base_url, status, created_at) VALUES (:name, :base_url, :status, NOW())');
        $insert->execute(array(
            'name' => self::PROVIDER_NAME,
            'base_url' => '',
            'status' => 'inactive',
        ));

        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM provider_sources WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $id));

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: array();
    }

    /**
     * @param array<string,string> $input
     * @return array{success:bool,message:string}
     */
    public static function saveCredentials(array $input): array
    {
        $pdo = Database::connection();
        $source = self::getSource();
        $id = isset($source['id']) ? (int) $source['id'] : 0;
        if ($id <= 0) {
            throw new RuntimeException('Sağlayıcı kaydı oluşturulamadı.');
        }

        $baseUrl = isset($input['base_url']) ? trim((string) $input['base_url']) : '';
        $consumerKey = isset($input['consumer_key']) ? trim((string) $input['consumer_key']) : '';
        $consumerSecret = isset($input['consumer_secret']) ? trim((string) $input['consumer_secret']) : '';
        $status = isset($input['status']) && $input['status'] === 'active' ? 'active' : 'inactive';

        if ($baseUrl !== '' && !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return array('success' => false, 'message' => 'Geçerli bir WordPress site adresi giriniz.');
        }

        if ($baseUrl !== '') {
            $baseUrl = rtrim($baseUrl, '/');
        }

        $update = $pdo->prepare('UPDATE provider_sources SET name = :name, base_url = :base_url, consumer_key = :consumer_key, consumer_secret = :consumer_secret, status = :status, updated_at = NOW() WHERE id = :id');
        $update->execute(array(
            'name' => self::PROVIDER_NAME,
            'base_url' => $baseUrl,
            'consumer_key' => $consumerKey !== '' ? $consumerKey : null,
            'consumer_secret' => $consumerSecret !== '' ? $consumerSecret : null,
            'status' => $status,
            'id' => $id,
        ));

        return array('success' => true, 'message' => 'Sağlayıcı ayarları güncellendi.');
    }

    /**
     * @return array{success:bool,message:string,data?:array<string,mixed>}
     */
    public static function testConnection(): array
    {
        $source = self::getSource();
        $response = self::request($source, 'system_status');

        if (!$response['success']) {
            return array('success' => false, 'message' => $response['message']);
        }

        $decoded = isset($response['decoded']) && is_array($response['decoded']) ? $response['decoded'] : array();
        $environment = isset($decoded['environment']) && is_array($decoded['environment']) ? $decoded['environment'] : array();
        $siteName = isset($environment['site_title']) ? (string) $environment['site_title'] : '';
        $version = isset($environment['version']) ? (string) $environment['version'] : '';

        $message = 'Bağlantı başarılı.';
        if ($siteName !== '' || $version !== '') {
            $message .= ' ' . trim(sprintf('%s %s', $siteName, $version));
        }

        return array('success' => true, 'message' => $message, 'data' => $decoded);
    }

    /**
     * @return array{success:bool,message:string,counts?:array<string,int>}
     */
    public static function syncCategories(): array
    {
        $source = self::requireActiveSource();
        $pdo = Database::connection();

        $created = 0;
        $updated = 0;
        $page = 1;
        $lastSync = date('Y-m-d H:i:s');

        while ($page <= self::MAX_PAGES) {
            $response = self::request($source, 'products/categories', array(
                'per_page' => self::PER_PAGE,
                'page' => $page,
                'orderby' => 'id',
                'order' => 'asc',
                'hide_empty' => 'false',
            ));

            if (!$response['success']) {
                return array('success' => false, 'message' => $response['message']);
            }

            $data = isset($response['decoded']) && is_array($response['decoded']) ? $response['decoded'] : array();
            if (!$data) {
                break;
            }

            foreach ($data as $category) {
                if (!is_array($category) || !isset($category['id'])) {
                    continue;
                }

                $remoteId = (int) $category['id'];
                $parentRemote = isset($category['parent']) ? (int) $category['parent'] : null;
                $name = isset($category['name']) ? trim((string) $category['name']) : '';
                $slug = isset($category['slug']) ? trim((string) $category['slug']) : null;

                $existing = self::findRemoteCategory($source['id'], $remoteId);
                $mappedId = null;
                if ($existing && isset($existing['mapped_category_id']) && $existing['mapped_category_id']) {
                    $mappedId = (int) $existing['mapped_category_id'];
                } elseif ($name !== '') {
                    $mappedId = self::findLocalCategoryIdByName($name);
                }

                if ($existing) {
                    $updateStmt = $pdo->prepare('UPDATE provider_remote_categories SET parent_remote_id = :parent_remote_id, name = :name, slug = :slug, last_synced_at = :last_synced_at, updated_at = NOW() WHERE id = :id');
                    $updateStmt->execute(array(
                        'parent_remote_id' => $parentRemote ?: null,
                        'name' => $name,
                        'slug' => $slug,
                        'last_synced_at' => $lastSync,
                        'id' => $existing['id'],
                    ));

                    if ($mappedId && (int) $existing['mapped_category_id'] !== $mappedId) {
                        $pdo->prepare('UPDATE provider_remote_categories SET mapped_category_id = :mapped WHERE id = :id')->execute(array(
                            'mapped' => $mappedId,
                            'id' => $existing['id'],
                        ));
                    }
                    $updated++;
                } else {
                    $insert = $pdo->prepare('INSERT INTO provider_remote_categories (provider_id, remote_id, parent_remote_id, name, slug, mapped_category_id, last_synced_at, created_at) VALUES (:provider_id, :remote_id, :parent_remote_id, :name, :slug, :mapped_category_id, :last_synced_at, NOW())');
                    $insert->execute(array(
                        'provider_id' => $source['id'],
                        'remote_id' => $remoteId,
                        'parent_remote_id' => $parentRemote ?: null,
                        'name' => $name,
                        'slug' => $slug,
                        'mapped_category_id' => $mappedId ?: null,
                        'last_synced_at' => $lastSync,
                    ));
                    $created++;
                }
            }

            if (count($data) < self::PER_PAGE) {
                break;
            }

            $page++;
        }

        self::touchSourceSyncTime((int) $source['id']);

        return array(
            'success' => true,
            'message' => 'Kategoriler senkronize edildi.',
            'counts' => array('created' => $created, 'updated' => $updated),
        );
    }

    /**
     * @return array{success:bool,message:string,counts?:array<string,int>,new_products?:array<int,array<string,mixed>>}
     */
    public static function syncProducts(): array
    {
        $source = self::requireActiveSource();
        $pdo = Database::connection();

        $created = 0;
        $updated = 0;
        $newProducts = array();
        $page = 1;
        $lastSync = date('Y-m-d H:i:s');

        while ($page <= self::MAX_PAGES) {
            $response = self::request($source, 'products', array(
                'per_page' => self::PER_PAGE,
                'page' => $page,
                'orderby' => 'date',
                'order' => 'desc',
                'status' => 'publish',
            ));

            if (!$response['success']) {
                return array('success' => false, 'message' => $response['message']);
            }

            $data = isset($response['decoded']) && is_array($response['decoded']) ? $response['decoded'] : array();
            if (!$data) {
                break;
            }

            foreach ($data as $product) {
                if (!is_array($product) || !isset($product['id'])) {
                    continue;
                }

                $remoteId = (int) $product['id'];
                $name = isset($product['name']) ? trim((string) $product['name']) : 'Ürün';
                $slug = isset($product['slug']) ? trim((string) $product['slug']) : null;
                $status = isset($product['status']) ? (string) $product['status'] : null;
                $priceField = isset($product['price']) ? $product['price'] : (isset($product['regular_price']) ? $product['regular_price'] : null);
                $price = $priceField !== null && $priceField !== '' ? (float) $priceField : null;
                $stockQuantity = isset($product['stock_quantity']) && $product['stock_quantity'] !== '' ? (int) $product['stock_quantity'] : null;
                $stockStatus = isset($product['stock_status']) ? (string) $product['stock_status'] : null;

                $remoteCategoryId = null;
                $remoteCategoryName = null;
                if (isset($product['categories']) && is_array($product['categories']) && $product['categories']) {
                    $primaryCategory = $product['categories'][0];
                    if (is_array($primaryCategory)) {
                        $remoteCategoryId = isset($primaryCategory['id']) ? (int) $primaryCategory['id'] : null;
                        $remoteCategoryName = isset($primaryCategory['name']) ? (string) $primaryCategory['name'] : null;
                    }
                }

                $existing = self::findRemoteProduct($source['id'], $remoteId);

                if ($existing) {
                    $updateStmt = $pdo->prepare('UPDATE provider_remote_products SET name = :name, slug = :slug, price = :price, currency = :currency, status = :status, remote_category_id = :remote_category_id, remote_category_name = :remote_category_name, stock_quantity = :stock_quantity, stock_status = :stock_status, last_synced_at = :last_synced_at, updated_at = NOW() WHERE id = :id');
                    $updateStmt->execute(array(
                        'name' => $name,
                        'slug' => $slug,
                        'price' => $price,
                        'currency' => self::detectCurrency($product),
                        'status' => $status,
                        'remote_category_id' => $remoteCategoryId ?: null,
                        'remote_category_name' => $remoteCategoryName,
                        'stock_quantity' => $stockQuantity,
                        'stock_status' => $stockStatus,
                        'last_synced_at' => $lastSync,
                        'id' => $existing['id'],
                    ));
                    $updated++;
                } else {
                    $insert = $pdo->prepare('INSERT INTO provider_remote_products (provider_id, remote_id, name, slug, price, currency, status, remote_category_id, remote_category_name, stock_quantity, stock_status, last_synced_at, created_at) VALUES (:provider_id, :remote_id, :name, :slug, :price, :currency, :status, :remote_category_id, :remote_category_name, :stock_quantity, :stock_status, :last_synced_at, NOW())');
                    $insert->execute(array(
                        'provider_id' => $source['id'],
                        'remote_id' => $remoteId,
                        'name' => $name,
                        'slug' => $slug,
                        'price' => $price,
                        'currency' => self::detectCurrency($product),
                        'status' => $status,
                        'remote_category_id' => $remoteCategoryId ?: null,
                        'remote_category_name' => $remoteCategoryName,
                        'stock_quantity' => $stockQuantity,
                        'stock_status' => $stockStatus,
                        'last_synced_at' => $lastSync,
                    ));

                    $created++;
                    $newProducts[] = array('remote_id' => $remoteId, 'name' => $name);
                    self::createProductAnnouncement((int) $source['id'], $remoteId, $name);
                }
            }

            if (count($data) < self::PER_PAGE) {
                break;
            }

            $page++;
        }

        self::touchSourceSyncTime((int) $source['id']);

        return array(
            'success' => true,
            'message' => 'Ürünler senkronize edildi.',
            'counts' => array('created' => $created, 'updated' => $updated),
            'new_products' => $newProducts,
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listRemoteProducts(?string $filter = null): array
    {
        $source = self::getSource();
        if (!$source) {
            return array();
        }

        $pdo = Database::connection();
        $sql = 'SELECT prp.*, cat.name AS mapped_category_name FROM provider_remote_products prp LEFT JOIN provider_remote_categories prc ON prp.provider_id = prc.provider_id AND prp.remote_category_id = prc.remote_id LEFT JOIN categories cat ON prc.mapped_category_id = cat.id WHERE prp.provider_id = :provider_id';
        $params = array('provider_id' => $source['id']);

        if ($filter === 'unimported') {
            $sql .= ' AND (prp.imported_product_id IS NULL OR prp.imported_product_id = 0)';
        } elseif ($filter === 'imported') {
            $sql .= ' AND prp.imported_product_id IS NOT NULL AND prp.imported_product_id <> 0';
        }

        $sql .= ' ORDER BY prp.updated_at DESC, prp.created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listRemoteCategories(): array
    {
        $source = self::getSource();
        if (!$source) {
            return array();
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT prc.*, cat.name AS mapped_category_name FROM provider_remote_categories prc LEFT JOIN categories cat ON prc.mapped_category_id = cat.id WHERE prc.provider_id = :provider_id ORDER BY prc.name ASC');
        $stmt->execute(array('provider_id' => $source['id']));

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    /**
     * @return array{success:bool,message:string,product_id?:int}
     */
    public static function importProduct(int $remoteId, int $adminId): array
    {
        $source = self::requireActiveSource();
        $pdo = Database::connection();

        $remote = self::findRemoteProduct($source['id'], $remoteId);
        if (!$remote) {
            return array('success' => false, 'message' => 'Sağlayıcıda bu ürün bulunamadı.');
        }

        if (!empty($remote['imported_product_id'])) {
            return array('success' => false, 'message' => 'Bu ürün zaten içeri aktarılmış.');
        }

        $productResponse = self::request($source, 'products/' . $remoteId);
        if (!$productResponse['success']) {
            return array('success' => false, 'message' => $productResponse['message']);
        }

        $product = isset($productResponse['decoded']) && is_array($productResponse['decoded']) ? $productResponse['decoded'] : array();
        if (!$product) {
            return array('success' => false, 'message' => 'Ürün bilgileri alınamadı.');
        }

        $name = isset($product['name']) ? trim((string) $product['name']) : 'Sağlayıcı Ürünü';
        $description = isset($product['description']) && $product['description'] !== ''
            ? (string) $product['description']
            : (isset($product['short_description']) ? (string) $product['short_description'] : '');
        $sku = isset($product['sku']) ? trim((string) $product['sku']) : null;
        $priceField = isset($product['price']) ? $product['price'] : (isset($product['regular_price']) ? $product['regular_price'] : null);
        $price = $priceField !== null && $priceField !== '' ? (float) $priceField : 0.0;

        $remoteCategoryId = isset($product['categories'][0]['id']) ? (int) $product['categories'][0]['id'] : (isset($remote['remote_category_id']) ? (int) $remote['remote_category_id'] : 0);
        $categoryId = self::ensureLocalCategory((int) $source['id'], $remoteCategoryId);

        $automaticDelivery = 1;
        if (isset($product['manage_stock']) && $product['manage_stock'] === false) {
            $automaticDelivery = 0;
        }

        $stmt = $pdo->prepare('INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status, automatic_delivery, created_at) VALUES (:category_id, :name, :sku, :description, :cost_price_try, :price, :status, :automatic_delivery, NOW())');
        $stmt->execute(array(
            'category_id' => $categoryId,
            'name' => $name,
            'sku' => $sku !== '' ? $sku : null,
            'description' => $description,
            'cost_price_try' => null,
            'price' => $price,
            'status' => 'inactive',
            'automatic_delivery' => $automaticDelivery,
        ));

        $productId = (int) $pdo->lastInsertId();

        $pdo->prepare('UPDATE provider_remote_products SET imported_product_id = :product_id, updated_at = NOW() WHERE id = :id')->execute(array(
            'product_id' => $productId,
            'id' => $remote['id'],
        ));

        if (!empty($remote['announcement_id'])) {
            AnnouncementService::deactivate((int) $remote['announcement_id']);
        }

        return array('success' => true, 'message' => 'Ürün içeri aktarıldı ve taslak olarak kaydedildi.', 'product_id' => $productId);
    }

    /**
     * @return array<string,mixed>
     */
    private static function requireActiveSource(): array
    {
        $source = self::getSource();
        if (!$source || empty($source['base_url'])) {
            throw new RuntimeException('Sağlayıcı URL bilgisi eksik.');
        }

        if (empty($source['consumer_key']) || empty($source['consumer_secret'])) {
            throw new RuntimeException('Sağlayıcı API anahtarı eksik.');
        }

        return $source;
    }

    /**
     * @param array<string,mixed> $source
     * @return array{success:bool,message:string,status?:int,body?:string,decoded?:mixed}
     */
    private static function request(array $source, string $endpoint, array $query = array(), string $method = 'GET', ?array $body = null): array
    {
        $baseUrl = isset($source['base_url']) ? trim((string) $source['base_url']) : '';
        if ($baseUrl === '') {
            return array('success' => false, 'message' => 'Sağlayıcı adresi yapılandırılmamış.');
        }

        if (empty($source['consumer_key']) || empty($source['consumer_secret'])) {
            return array('success' => false, 'message' => 'Sağlayıcı API anahtarı eksik.');
        }

        $url = rtrim($baseUrl, '/') . '/wp-json/wc/v3/' . ltrim($endpoint, '/');
        $query['consumer_key'] = $source['consumer_key'];
        $query['consumer_secret'] = $source['consumer_secret'];

        $url .= '?' . http_build_query($query);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'User-Agent: Authero-ProviderSync/1.0'));

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-Type: application/json', 'User-Agent: Authero-ProviderSync/1.0'));
            }
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            return array('success' => false, 'message' => 'Sağlayıcı isteği başarısız: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($response, 0, $headerSize);
        $bodyString = substr($response, $headerSize);

        $decoded = json_decode((string) $bodyString, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return array('success' => false, 'message' => 'API yanıtı çözümlenemedi: ' . json_last_error_msg(), 'status' => $status, 'body' => $bodyString);
        }

        if ($status < 200 || $status >= 300) {
            $message = 'Sağlayıcı isteği başarısız (' . $status . ')';
            if (is_array($decoded) && isset($decoded['message'])) {
                $message .= ': ' . (string) $decoded['message'];
            }

            return array('success' => false, 'message' => $message, 'status' => $status, 'body' => $bodyString, 'decoded' => $decoded);
        }

        return array('success' => true, 'message' => 'OK', 'status' => $status, 'body' => $bodyString, 'decoded' => $decoded, 'headers' => self::parseHeaders($rawHeaders));
    }

    /**
     * @param string $headers
     * @return array<string,string>
     */
    private static function parseHeaders(string $headers): array
    {
        $result = array();
        foreach (explode("\r\n", $headers) as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            list($name, $value) = array_map('trim', explode(':', $line, 2));
            if ($name !== '') {
                $result[strtolower($name)] = $value;
            }
        }

        return $result;
    }

    /**
     * @param int $providerId
     * @param int $remoteId
     * @return array<string,mixed>|null
     */
    private static function findRemoteCategory(int $providerId, int $remoteId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM provider_remote_categories WHERE provider_id = :provider_id AND remote_id = :remote_id LIMIT 1');
        $stmt->execute(array('provider_id' => $providerId, 'remote_id' => $remoteId));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param int $providerId
     * @param int $remoteId
     * @return array<string,mixed>|null
     */
    private static function findRemoteProduct(int $providerId, int $remoteId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM provider_remote_products WHERE provider_id = :provider_id AND remote_id = :remote_id LIMIT 1');
        $stmt->execute(array('provider_id' => $providerId, 'remote_id' => $remoteId));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private static function touchSourceSyncTime(int $providerId): void
    {
        $pdo = Database::connection();
        $pdo->prepare('UPDATE provider_sources SET last_synced_at = NOW(), updated_at = NOW() WHERE id = :id')->execute(array('id' => $providerId));
    }

    private static function detectCurrency(array $product): ?string
    {
        if (isset($product['currency']) && $product['currency'] !== '') {
            return (string) $product['currency'];
        }

        $defaultCurrency = Settings::get('platform_currency');
        if ($defaultCurrency) {
            return $defaultCurrency;
        }

        return 'TRY';
    }

    private static function findLocalCategoryIdByName(string $name): ?int
    {
        if ($name === '') {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(:name) LIMIT 1');
        $stmt->execute(array('name' => $name));
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private static function ensureLocalCategory(int $providerId, int $remoteCategoryId): int
    {
        $pdo = Database::connection();

        if ($remoteCategoryId <= 0) {
            return self::ensureDefaultCategory();
        }

        $remote = self::findRemoteCategory($providerId, $remoteCategoryId);
        if (!$remote) {
            return self::ensureDefaultCategory();
        }

        if (!empty($remote['mapped_category_id'])) {
            return (int) $remote['mapped_category_id'];
        }

        $parentId = isset($remote['parent_remote_id']) ? (int) $remote['parent_remote_id'] : 0;
        $localParentId = $parentId > 0 ? self::ensureLocalCategory($providerId, $parentId) : self::ensureDefaultCategory();

        $existing = self::findCategoryByNameAndParent((string) $remote['name'], $localParentId);
        if ($existing) {
            $pdo->prepare('UPDATE provider_remote_categories SET mapped_category_id = :mapped, updated_at = NOW() WHERE id = :id')->execute(array(
                'mapped' => $existing,
                'id' => $remote['id'],
            ));

            return $existing;
        }

        $insert = $pdo->prepare('INSERT INTO categories (parent_id, name, description, created_at) VALUES (:parent_id, :name, NULL, NOW())');
        $insert->execute(array(
            'parent_id' => $localParentId ?: null,
            'name' => $remote['name'],
        ));

        $newId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE provider_remote_categories SET mapped_category_id = :mapped, updated_at = NOW() WHERE id = :id')->execute(array(
            'mapped' => $newId,
            'id' => $remote['id'],
        ));

        return $newId;
    }

    private static function ensureDefaultCategory(): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
        $stmt->execute(array('name' => self::DEFAULT_ROOT_CATEGORY));
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $insert = $pdo->prepare('INSERT INTO categories (parent_id, name, description, created_at) VALUES (NULL, :name, NULL, NOW())');
        $insert->execute(array('name' => self::DEFAULT_ROOT_CATEGORY));

        return (int) $pdo->lastInsertId();
    }

    private static function findCategoryByNameAndParent(string $name, int $parentId): ?int
    {
        $pdo = Database::connection();
        if ($parentId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE parent_id = :parent_id AND name = :name LIMIT 1');
            $stmt->execute(array('parent_id' => $parentId, 'name' => $name));
        } else {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE parent_id IS NULL AND name = :name LIMIT 1');
            $stmt->execute(array('name' => $name));
        }

        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private static function createProductAnnouncement(int $providerId, int $remoteId, string $name): void
    {
        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return;
        }

        $announcementId = AnnouncementService::create(
            'Yeni sağlayıcı ürünü: ' . $name,
            sprintf(
                'Yeni bir sağlayıcı ürünü bulundu: <strong>%s</strong>.<br><a href="/admin/providers.php?highlight=%d">Sağlayıcılar sayfasından hemen içeri aktarın.</a>',
                Helpers::sanitize($name),
                $remoteId
            ),
            'admin'
        );

        if ($announcementId) {
            $update = $pdo->prepare('UPDATE provider_remote_products SET announcement_id = :announcement_id WHERE provider_id = :provider_id AND remote_id = :remote_id');
            $update->execute(array(
                'announcement_id' => $announcementId,
                'provider_id' => $providerId,
                'remote_id' => $remoteId,
            ));
        }
    }
}
