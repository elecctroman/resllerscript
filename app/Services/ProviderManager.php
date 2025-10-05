<?php declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Helpers;
use PDO;

final class ProviderManager
{
    private const ALLOWED_DRIVERS = array('generic', 'netgsm', 'turkpin', 'pinabi');

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function all(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM providers ORDER BY name ASC');
        $providers = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();

        return array_map(static function (array $provider): array {
            return self::hydrate($provider);
        }, $providers);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function active(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM providers WHERE status = :status ORDER BY name ASC');
        $stmt->execute(array('status' => 'active'));
        $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $provider): array {
            return self::hydrate($provider);
        }, $providers ?: array());
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM providers WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $id));
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);

        return $provider ? self::hydrate($provider) : null;
    }

    /**
     * @param string $code
     * @return array<string,mixed>|null
     */
    public static function findByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM providers WHERE code = :code LIMIT 1');
        $stmt->execute(array('code' => $code));
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);

        return $provider ? self::hydrate($provider) : null;
    }

    /**
     * @param array<string,mixed> $provider
     * @return array<string,mixed>
     */
    public static function syncProducts(array $provider): array
    {
        $provider = self::hydrate($provider);
        if (empty($provider['id'])) {
            return array('success' => false, 'error' => 'Sağlayıcı seçilemedi.');
        }

        if (($provider['status'] ?? '') !== 'active') {
            return array('success' => false, 'error' => 'Sağlayıcı pasif durumdayken ürünler senkronize edilemez.');
        }

        $apiResult = ProviderApiClient::fetchProducts($provider);
        $pdo = Database::connection();

        if (empty($apiResult['success'])) {
            $pdo->prepare('UPDATE providers SET last_synced_at = NOW(), last_sync_status = :status, last_sync_error = :error WHERE id = :id')->execute(array(
                'status' => 'error',
                'error' => isset($apiResult['error']) ? (string) $apiResult['error'] : 'Sağlayıcı ürünleri alınamadı.',
                'id' => $provider['id'],
            ));

            return array(
                'success' => false,
                'error' => isset($apiResult['error']) ? (string) $apiResult['error'] : 'Sağlayıcı ürünleri alınamadı.',
            );
        }

        $items = self::extractProductItems($apiResult);

        $syncTimestamp = date('Y-m-d H:i:s');
        $syncedCount = 0;

        try {
            $pdo->beginTransaction();

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $externalId = '';
                if (isset($item['id'])) {
                    $externalId = (string) $item['id'];
                } elseif (isset($item['product_id'])) {
                    $externalId = (string) $item['product_id'];
                } elseif (isset($item['productId'])) {
                    $externalId = (string) $item['productId'];
                } elseif (isset($item['product_code'])) {
                    $externalId = (string) $item['product_code'];
                } elseif (isset($item['productCode'])) {
                    $externalId = (string) $item['productCode'];
                } elseif (isset($item['code'])) {
                    $externalId = (string) $item['code'];
                } elseif (isset($item['sku'])) {
                    $externalId = (string) $item['sku'];
                }

                if ($externalId === '') {
                    continue;
                }

                $name = isset($item['name']) ? (string) $item['name'] : (string) ($item['title'] ?? ($item['product_name'] ?? ($item['label'] ?? $externalId)));
                $description = null;
                if (isset($item['description']) && $item['description'] !== '') {
                    $description = (string) $item['description'];
                } elseif (isset($item['details']) && $item['details'] !== '') {
                    $description = (string) $item['details'];
                } elseif (isset($item['content']) && $item['content'] !== '') {
                    $description = (string) $item['content'];
                } elseif (isset($item['explanation']) && $item['explanation'] !== '') {
                    $description = (string) $item['explanation'];
                }
                $price = null;
                if (isset($item['price'])) {
                    $price = (float) $item['price'];
                } elseif (isset($item['amount'])) {
                    $price = (float) $item['amount'];
                } elseif (isset($item['sale_price'])) {
                    $price = (float) $item['sale_price'];
                } elseif (isset($item['unit_price'])) {
                    $price = (float) $item['unit_price'];
                }
                $currency = isset($item['currency']) ? (string) $item['currency'] : null;
                if ($currency === null && isset($item['currency_code'])) {
                    $currency = (string) $item['currency_code'];
                }
                if ($currency === null && isset($item['currencySymbol'])) {
                    $currency = (string) $item['currencySymbol'];
                }
                $stock = null;
                if (isset($item['stock'])) {
                    $stock = (int) $item['stock'];
                } elseif (isset($item['stock_quantity'])) {
                    $stock = (int) $item['stock_quantity'];
                } elseif (isset($item['available_quantity'])) {
                    $stock = (int) $item['available_quantity'];
                } elseif (isset($item['quantity'])) {
                    $stock = (int) $item['quantity'];
                } elseif (isset($item['balance'])) {
                    $stock = (int) $item['balance'];
                }
                $available = null;
                if (isset($item['is_available'])) {
                    $available = (int) $item['is_available'] === 1;
                } elseif (isset($item['available'])) {
                    $available = (bool) $item['available'];
                } elseif (isset($item['is_active'])) {
                    $available = (bool) $item['is_active'];
                } elseif (isset($item['status'])) {
                    $statusValue = strtolower((string) $item['status']);
                    if (in_array($statusValue, array('active', '1', 'available', 'true', 'open'), true)) {
                        $available = true;
                    } elseif (in_array($statusValue, array('0', 'inactive', 'pasif', 'closed', 'false'), true)) {
                        $available = false;
                    }
                }

                $payload = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $existingStmt = $pdo->prepare('SELECT id FROM provider_products WHERE provider_id = :provider_id AND external_id = :external_id LIMIT 1');
                $existingStmt->execute(array(
                    'provider_id' => $provider['id'],
                    'external_id' => $externalId,
                ));

                $data = array(
                    'provider_id' => $provider['id'],
                    'external_id' => $externalId,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'currency' => $currency ?? 'TRY',
                    'stock' => $stock,
                    'is_available' => $available === null ? 0 : ($available ? 1 : 0),
                    'payload' => $payload,
                    'last_synced_at' => $syncTimestamp,
                );

                if ($existingId = $existingStmt->fetchColumn()) {
                    $update = $pdo->prepare('UPDATE provider_products SET name = :name, description = :description, price = :price, currency = :currency, stock = :stock, is_available = :is_available, payload = :payload, last_synced_at = :last_synced_at, updated_at = NOW() WHERE id = :id');
                    $update->execute(array(
                        'id' => (int) $existingId,
                        'name' => $data['name'],
                        'description' => $data['description'],
                        'price' => $data['price'],
                        'currency' => $data['currency'],
                        'stock' => $data['stock'],
                        'is_available' => $data['is_available'],
                        'payload' => $data['payload'],
                        'last_synced_at' => $data['last_synced_at'],
                    ));
                } else {
                    $insert = $pdo->prepare('INSERT INTO provider_products (provider_id, external_id, name, description, price, currency, stock, is_available, payload, last_synced_at, created_at) VALUES (:provider_id, :external_id, :name, :description, :price, :currency, :stock, :is_available, :payload, :last_synced_at, NOW())');
                    $insert->execute($data);
                }

                $syncedCount++;
            }

            $cleanup = $pdo->prepare('UPDATE provider_products SET is_available = 0 WHERE provider_id = :provider_id AND (last_synced_at IS NULL OR last_synced_at < :synced_at)');
            $cleanup->execute(array(
                'provider_id' => $provider['id'],
                'synced_at' => $syncTimestamp,
            ));

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $pdo->prepare('UPDATE providers SET last_synced_at = NOW(), last_sync_status = :status, last_sync_error = :error WHERE id = :id')->execute(array(
                'status' => 'error',
                'error' => $exception->getMessage(),
                'id' => $provider['id'],
            ));

            return array('success' => false, 'error' => 'Senkronizasyon sırasında hata oluştu.');
        }

        $pdo->prepare('UPDATE providers SET last_synced_at = :synced_at, last_sync_status = :status, last_sync_error = NULL WHERE id = :id')->execute(array(
            'synced_at' => $syncTimestamp,
            'status' => 'success',
            'id' => $provider['id'],
        ));

        return array(
            'success' => true,
            'count' => $syncedCount,
        );
    }

    /**
     * @param int $providerId
     * @return array<int,array<string,mixed>>
     */
    public static function cachedProducts(int $providerId): array
    {
        if ($providerId <= 0) {
            return array();
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM provider_products WHERE provider_id = :provider_id ORDER BY name ASC');
        $stmt->execute(array('provider_id' => $providerId));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return array();
        }

        return array_map(static function (array $row): array {
            if (isset($row['payload']) && is_string($row['payload']) && $row['payload'] !== '') {
                $decoded = json_decode($row['payload'], true);
                if (is_array($decoded)) {
                    $row['payload'] = $decoded;
                }
            }

            return $row;
        }, $rows);
    }

    /**
     * @param array<string,mixed> $provider
     * @param array<int|string> $externalIds
     * @param int $categoryId
     * @param float $markupPercent
     * @param bool $activate
     * @return array<string,mixed>
     */
    public static function importProducts(array $provider, array $externalIds, int $categoryId, float $markupPercent = 0.0, bool $activate = true): array
    {
        $provider = self::hydrate($provider);
        $externalIds = array_values(array_filter(array_map(static function ($value) {
            return (string) $value;
        }, $externalIds), static function ($value) {
            return $value !== '';
        }));

        if (!$externalIds) {
            return array('success' => false, 'error' => 'İçe aktarılacak ürün seçilmedi.');
        }

        if ($categoryId <= 0) {
            return array('success' => false, 'error' => 'Kategori seçimi zorunludur.');
        }

        $pdo = Database::connection();
        $placeholders = implode(',', array_fill(0, count($externalIds), '?'));
        $params = array_merge(array($provider['id']), $externalIds);

        $stmt = $pdo->prepare('SELECT * FROM provider_products WHERE provider_id = ? AND external_id IN (' . $placeholders . ')');
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return array('success' => false, 'error' => 'Seçilen sağlayıcı ürünleri bulunamadı.');
        }

        $imported = array();
        $skipped = array();
        $errors = array();

        try {
            $pdo->beginTransaction();

            foreach ($rows as $row) {
                $externalId = (string) $row['external_id'];

                $check = $pdo->prepare('SELECT id FROM products WHERE provider_code = :provider_code AND provider_product_id = :external_id LIMIT 1');
                $check->execute(array(
                    'provider_code' => $provider['code'],
                    'external_id' => $externalId,
                ));

                if ($check->fetch(PDO::FETCH_ASSOC)) {
                    $skipped[] = $row['name'];
                    continue;
                }

                $cost = isset($row['price']) ? (float) $row['price'] : 0.0;
                if ($cost <= 0) {
                    $errors[] = sprintf('%s ürünü için geçerli fiyat bulunamadı.', (string) $row['name']);
                    continue;
                }

                $salePrice = Helpers::priceFromCostTry($cost);
                if ($markupPercent !== 0.0) {
                    $salePrice = $salePrice * (1 + ($markupPercent / 100));
                }

                $insert = $pdo->prepare('INSERT INTO products (name, category_id, cost_price_try, price, description, sku, status, automatic_delivery, provider_code, provider_product_id, created_at) VALUES (:name, :category_id, :cost_price_try, :price, :description, :sku, :status, :automatic_delivery, :provider_code, :provider_product_id, NOW())');
                $insert->execute(array(
                    'name' => $row['name'],
                    'category_id' => $categoryId,
                    'cost_price_try' => $cost,
                    'price' => round($salePrice, 2),
                    'description' => isset($row['description']) ? $row['description'] : null,
                    'sku' => strtoupper($provider['code']) . '-' . $externalId,
                    'status' => $activate ? 'active' : 'inactive',
                    'automatic_delivery' => 1,
                    'provider_code' => $provider['code'],
                    'provider_product_id' => $externalId,
                ));

                $imported[] = array(
                    'id' => (int) $pdo->lastInsertId(),
                    'name' => $row['name'],
                    'external_id' => $externalId,
                );
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return array('success' => false, 'error' => 'Ürünler içe aktarılırken hata oluştu: ' . $exception->getMessage());
        }

        return array(
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        );
    }

    /**
     * Sağlayıcı kimlik doğrulamasını test eder ve sonucu kaydeder.
     *
     * @param array<string,mixed> $provider
     * @return array<string,mixed>
     */
    public static function testConnection(array $provider): array
    {
        $provider = self::hydrate($provider);
        if (empty($provider['id'])) {
            return array('success' => false, 'error' => 'Sağlayıcı bulunamadı.');
        }

        $result = ProviderApiClient::testConnection($provider);

        $settings = isset($provider['settings']) && is_array($provider['settings']) ? $provider['settings'] : array();
        $settings['connection_test'] = array(
            'checked_at' => date('c'),
            'status' => !empty($result['success']) ? 'success' : 'error',
            'message' => isset($result['message']) && is_string($result['message']) ? $result['message'] : (isset($result['error']) ? (string) $result['error'] : ''),
        );

        $pdo = Database::connection();
        $pdo->prepare('UPDATE providers SET settings = :settings, updated_at = NOW() WHERE id = :id')->execute(array(
            'id' => (int) $provider['id'],
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));

        return $result;
    }

    /**
     * @param array<string,mixed> $apiResult
     * @return array<int,array<string,mixed>>
     */
    private static function extractProductItems(array $apiResult): array
    {
        $candidates = array();

        if (isset($apiResult['data'])) {
            $candidates[] = $apiResult['data'];
        }

        if (isset($apiResult['body']) && is_array($apiResult['body'])) {
            $candidates[] = $apiResult['body'];
        }

        foreach ($candidates as $candidate) {
            $items = self::normaliseProductList($candidate);
            if ($items) {
                return $items;
            }
        }

        return array();
    }

    /**
     * @param mixed $payload
     * @return array<int,array<string,mixed>>
     */
    private static function normaliseProductList($payload): array
    {
        if (!is_array($payload)) {
            return array();
        }

        if (self::isListArray($payload)) {
            return $payload;
        }

        $keys = array('data', 'items', 'products', 'result', 'results', 'list', 'catalog');

        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $subset = $payload[$key];
                if (self::isListArray($subset)) {
                    return $subset;
                }

                return array($subset);
            }
        }

        if (!empty($payload)) {
            return array($payload);
        }

        return array();
    }

    /**
     * @param array<int|string,mixed> $value
     */
    private static function isListArray(array $value): bool
    {
        if ($value === array()) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * @param array<string,mixed> $provider
     * @return array<string,mixed>
     */
    private static function hydrate(array $provider): array
    {
        if (isset($provider['settings']) && is_string($provider['settings']) && $provider['settings'] !== '') {
            $decoded = json_decode($provider['settings'], true);
            if (is_array($decoded)) {
                $provider['settings'] = $decoded;
            } else {
                $provider['settings'] = array();
            }
        } elseif (!isset($provider['settings']) || !is_array($provider['settings'])) {
            $provider['settings'] = array();
        }

        if (isset($provider['base_url'])) {
            $provider['base_url'] = rtrim((string) $provider['base_url']);
        }

        if (!isset($provider['code'])) {
            $provider['code'] = '';
        }

        $provider['driver'] = self::normaliseDriver($provider['driver'] ?? ($provider['settings']['driver'] ?? null), (string) $provider['code']);

        return $provider;
    }

    private static function normaliseDriver($driver, string $code): string
    {
        $driver = is_string($driver) ? strtolower(trim($driver)) : '';
        if ($driver === '' && $code !== '') {
            $code = strtolower(trim($code));
            if (in_array($code, array('netgsm', 'turkpin', 'pinabi'), true)) {
                $driver = $code;
            }
        }

        if (!in_array($driver, self::ALLOWED_DRIVERS, true)) {
            $driver = 'generic';
        }

        return $driver;
    }
}
