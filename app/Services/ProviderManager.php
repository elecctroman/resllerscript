<?php declare(strict_types=1);

namespace App\Services;

use App\AuditLog;
use App\Database;
use App\Helpers;
use PDO;

final class ProviderManager
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listProviders(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM providers ORDER BY name ASC');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM providers WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * @param string $slug
     * @return array<string,mixed>|null
     */
    public static function findActiveBySlug(string $slug): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM providers WHERE slug = :slug AND status = "active" LIMIT 1');
        $stmt->execute(array('slug' => $slug));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $currentUser
     * @return array<string,mixed>
     */
    public static function saveProvider(array $data, array $currentUser): array
    {
        $pdo = Database::connection();
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $name = isset($data['name']) ? trim((string) $data['name']) : '';
        $slug = isset($data['slug']) ? strtolower(trim((string) $data['slug'])) : '';
        $apiUrl = isset($data['api_url']) ? trim((string) $data['api_url']) : '';
        $apiKey = isset($data['api_key']) ? trim((string) $data['api_key']) : '';
        $status = isset($data['status']) && $data['status'] === 'active' ? 'active' : 'inactive';

        if ($name === '' || $slug === '' || $apiUrl === '') {
            return array('success' => false, 'message' => 'Sağlayıcı adı, kısa kodu ve API adresi zorunludur.', 'id' => $id);
        }

        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            return array('success' => false, 'message' => 'Slug yalnızca küçük harf ve tire içermelidir.', 'id' => $id);
        }

        $normalizedUrl = rtrim($apiUrl, '/');
        if (!preg_match('#^https?://#i', $normalizedUrl)) {
            return array('success' => false, 'message' => 'Geçerli bir API adresi giriniz.', 'id' => $id);
        }

        $pdo->beginTransaction();
        try {
            $dupStmt = $pdo->prepare('SELECT id FROM providers WHERE slug = :slug AND id <> :id LIMIT 1');
            $dupStmt->execute(array('slug' => $slug, 'id' => $id));
            if ($dupStmt->fetch(PDO::FETCH_ASSOC)) {
                $pdo->rollBack();
                return array('success' => false, 'message' => 'Bu kısa kod zaten kullanılıyor.', 'id' => $id);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE providers SET name = :name, slug = :slug, api_url = :api_url, api_key = :api_key, status = :status, updated_at = NOW() WHERE id = :id');
                $stmt->execute(array(
                    'id' => $id,
                    'name' => $name,
                    'slug' => $slug,
                    'api_url' => $normalizedUrl,
                    'api_key' => $apiKey,
                    'status' => $status,
                ));

                AuditLog::record($currentUser['id'], 'provider.update', 'provider', $id, sprintf('Sağlayıcı güncellendi: %s', $name));
            } else {
                $stmt = $pdo->prepare('INSERT INTO providers (name, slug, api_url, api_key, status, created_at) VALUES (:name, :slug, :api_url, :api_key, :status, NOW())');
                $stmt->execute(array(
                    'name' => $name,
                    'slug' => $slug,
                    'api_url' => $normalizedUrl,
                    'api_key' => $apiKey,
                    'status' => $status,
                ));

                $id = (int) $pdo->lastInsertId();
                AuditLog::record($currentUser['id'], 'provider.create', 'provider', $id, sprintf('Sağlayıcı oluşturuldu: %s', $name));
            }

            $pdo->commit();
            return array('success' => true, 'message' => 'Sağlayıcı bilgileri kaydedildi.', 'id' => $id);
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            return array('success' => false, 'message' => 'Sağlayıcı kaydedilemedi: ' . $exception->getMessage(), 'id' => $id);
        }
    }

    public static function deleteProvider(int $id, array $currentUser): array
    {
        if ($id <= 0) {
            return array('success' => false, 'message' => 'Geçersiz sağlayıcı.');
        }

        $pdo = Database::connection();
        try {
            $stmt = $pdo->prepare('DELETE FROM providers WHERE id = :id');
            $stmt->execute(array('id' => $id));
            AuditLog::record($currentUser['id'], 'provider.delete', 'provider', $id, 'Sağlayıcı silindi');
            return array('success' => true, 'message' => 'Sağlayıcı silindi.');
        } catch (\Throwable $exception) {
            return array('success' => false, 'message' => 'Sağlayıcı silinemedi: ' . $exception->getMessage());
        }
    }

    public static function testConnection(int $id): array
    {
        $provider = self::findById($id);
        if (!$provider) {
            return array('success' => false, 'message' => 'Sağlayıcı bulunamadı.');
        }

        if ((string) $provider['api_key'] === '') {
            return array('success' => false, 'message' => 'API anahtarı tanımlanmamış.');
        }

        try {
            $client = new ProviderApiClient((string) $provider['api_url'], (string) $provider['api_key']);
            $response = $client->fetchUser();
        } catch (\Throwable $exception) {
            self::storeTestResult($id, array('success' => false, 'message' => $exception->getMessage()));
            return array('success' => false, 'message' => $exception->getMessage());
        }

        self::storeTestResult($id, $response);

        if (!empty($response['success'])) {
            return array('success' => true, 'message' => 'Bağlantı başarılı.', 'data' => $response);
        }

        $message = isset($response['message']) ? (string) $response['message'] : 'Sağlayıcı bağlantısı başarısız oldu.';
        return array('success' => false, 'message' => $message, 'data' => $response);
    }

    /**
     * @param int $providerId
     * @return array<string,mixed>
     */
    public static function syncProducts(int $providerId): array
    {
        $provider = self::findById($providerId);
        if (!$provider) {
            return array('success' => false, 'message' => 'Sağlayıcı bulunamadı.');
        }

        if ((string) $provider['api_key'] === '') {
            return array('success' => false, 'message' => 'API anahtarı tanımlanmamış.');
        }

        try {
            $client = new ProviderApiClient((string) $provider['api_url'], (string) $provider['api_key']);
            $response = $client->fetchProducts();
        } catch (\Throwable $exception) {
            return array('success' => false, 'message' => $exception->getMessage());
        }

        if (empty($response['success'])) {
            $message = isset($response['message']) ? (string) $response['message'] : 'Sağlayıcı ürünleri alınamadı.';
            return array('success' => false, 'message' => $message, 'data' => $response);
        }

        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();
        if (!$data) {
            return array('success' => true, 'message' => 'Sağlayıcı ürün listesi boş döndü.');
        }

        $pdo = Database::connection();
        $inserted = 0;
        $updated = 0;

        $stmt = $pdo->prepare('INSERT INTO provider_products (provider_id, remote_id, remote_title, remote_price, remote_stock, remote_available, payload, synced_at)
            VALUES (:provider_id, :remote_id, :remote_title, :remote_price, :remote_stock, :remote_available, :payload, NOW())
            ON DUPLICATE KEY UPDATE remote_title = VALUES(remote_title), remote_price = VALUES(remote_price), remote_stock = VALUES(remote_stock), remote_available = VALUES(remote_available), payload = VALUES(payload), synced_at = NOW()');

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $remoteId = isset($item['id']) ? (string) $item['id'] : '';
            $remoteTitle = isset($item['title']) ? (string) $item['title'] : '';
            if ($remoteId === '' || $remoteTitle === '') {
                continue;
            }

            $remotePrice = isset($item['amount']) ? (float) $item['amount'] : 0.0;
            $remoteStock = isset($item['stock']) ? (int) $item['stock'] : 0;
            $remoteAvailable = !empty($item['available']) ? 1 : 0;
            $payload = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $stmt->execute(array(
                'provider_id' => $providerId,
                'remote_id' => $remoteId,
                'remote_title' => $remoteTitle,
                'remote_price' => $remotePrice,
                'remote_stock' => $remoteStock,
                'remote_available' => $remoteAvailable,
                'payload' => $payload,
            ));

            if ($stmt->rowCount() === 1) {
                $inserted++;
            } else {
                $updated++;
            }
        }

        $pdo->prepare('UPDATE providers SET last_synced_at = NOW(), updated_at = NOW() WHERE id = :id')->execute(array('id' => $providerId));

        return array('success' => true, 'message' => sprintf('Sağlayıcı ürünleri güncellendi. Yeni: %d, Güncellenen: %d', $inserted, $updated));
    }

    /**
     * @param int $providerId
     * @return array<int,array<string,mixed>>
     */
    public static function listProviderProducts(int $providerId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT pp.*, p.name AS local_name FROM provider_products pp LEFT JOIN products p ON pp.product_id = p.id WHERE pp.provider_id = :provider_id ORDER BY pp.remote_title ASC');
        $stmt->execute(array('provider_id' => $providerId));
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
    }

    /**
     * @param int $providerProductId
     * @param int $categoryId
     * @param array<string,mixed> $currentUser
     * @return array<string,mixed>
     */
    public static function importProviderProduct(int $providerProductId, int $categoryId, array $currentUser): array
    {
        if ($providerProductId <= 0 || $categoryId <= 0) {
            return array('success' => false, 'message' => 'Geçersiz ürün veya kategori seçildi.');
        }

        $pdo = Database::connection();
        $categoryCheck = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
        $categoryCheck->execute(array('id' => $categoryId));
        if (!$categoryCheck->fetch(PDO::FETCH_ASSOC)) {
            return array('success' => false, 'message' => 'Seçilen kategori bulunamadı.');
        }

        $stmt = $pdo->prepare('SELECT pp.*, pr.slug FROM provider_products pp INNER JOIN providers pr ON pr.id = pp.provider_id WHERE pp.id = :id LIMIT 1');
        $stmt->execute(array('id' => $providerProductId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return array('success' => false, 'message' => 'Sağlayıcı ürünü bulunamadı.');
        }

        if (!empty($row['product_id'])) {
            return array('success' => false, 'message' => 'Bu sağlayıcı ürünü zaten kataloğa aktarılmış.');
        }

        $name = (string) $row['remote_title'];
        $providerCode = (string) $row['slug'];
        $remoteId = (string) $row['remote_id'];
        $costPrice = isset($row['remote_price']) ? (float) $row['remote_price'] : 0.0;
        if ($costPrice <= 0) {
            $costPrice = 1.0;
        }

        $salePrice = Helpers::priceFromCostTry($costPrice);

        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare('INSERT INTO products (category_id, name, cost_price_try, price, description, status, provider_code, provider_product_id, created_at) VALUES (:category_id, :name, :cost_price_try, :price, :description, :status, :provider_code, :provider_product_id, NOW())');
            $insert->execute(array(
                'category_id' => $categoryId,
                'name' => $name,
                'cost_price_try' => $costPrice,
                'price' => $salePrice,
                'description' => null,
                'status' => 'active',
                'provider_code' => $providerCode,
                'provider_product_id' => $remoteId,
            ));

            $newProductId = (int) $pdo->lastInsertId();

            $update = $pdo->prepare('UPDATE provider_products SET product_id = :product_id WHERE id = :id');
            $update->execute(array('product_id' => $newProductId, 'id' => $providerProductId));

            AuditLog::record($currentUser['id'], 'provider.import', 'product', $newProductId, sprintf('Sağlayıcı ürünü içe aktarıldı: %s', $name));

            $pdo->commit();
            return array('success' => true, 'message' => 'Ürün kataloğa aktarıldı.');
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            return array('success' => false, 'message' => 'Ürün içe aktarılırken hata oluştu: ' . $exception->getMessage());
        }
    }

    /**
     * @param int $providerId
     * @param array<string,mixed> $payload
     * @return void
     */
    private static function storeTestResult(int $providerId, array $payload): void
    {
        $pdo = Database::connection();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $pdo->prepare('UPDATE providers SET last_tested_at = NOW(), last_test_response = :response WHERE id = :id');
        $stmt->execute(array('response' => $json, 'id' => $providerId));
    }
}
