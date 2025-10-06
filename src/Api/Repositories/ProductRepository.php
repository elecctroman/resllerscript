<?php declare(strict_types=1);

namespace App\Api\Repositories;

use App\Database;
use PDO;

/**
 * Ürün verilerini API katmanı için okuyan depo sınıfı.
 */
final class ProductRepository
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function listActive(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT p.id, p.name, p.sku, p.description, p.price, p.status, p.automatic_delivery, p.provider_code, ' .
            'p.provider_product_id, p.updated_at, c.id AS category_id, c.name AS category_name ' .
            'FROM products p INNER JOIN categories c ON c.id = p.category_id ' .
            "WHERE p.status = 'active' ORDER BY c.name ASC, p.name ASC"
        );

        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
        if (!$rows) {
            return array();
        }

        return array_map(static function ($row) {
            return array(
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'sku' => isset($row['sku']) ? (string) $row['sku'] : null,
                'description' => isset($row['description']) ? (string) $row['description'] : null,
                'price' => isset($row['price']) ? (float) $row['price'] : 0.0,
                'status' => (string) $row['status'],
                'automatic_delivery' => isset($row['automatic_delivery']) ? (int) $row['automatic_delivery'] === 1 : false,
                'provider_code' => isset($row['provider_code']) ? (string) $row['provider_code'] : null,
                'provider_product_id' => isset($row['provider_product_id']) ? (string) $row['provider_product_id'] : null,
                'category' => array(
                    'id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
                    'name' => isset($row['category_name']) ? (string) $row['category_name'] : null,
                ),
                'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            );
        }, $rows);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findActiveById(int $productId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT p.*, c.name AS category_name FROM products p INNER JOIN categories c ON c.id = p.category_id ' .
            "WHERE p.id = :id AND p.status = 'active' LIMIT 1"
        );
        $stmt->execute(array('id' => $productId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->normalizeProductRow($row);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findActiveBySku(string $sku): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT p.*, c.name AS category_name FROM products p INNER JOIN categories c ON c.id = p.category_id ' .
            "WHERE p.sku = :sku AND p.status = 'active' LIMIT 1"
        );
        $stmt->execute(array('sku' => $sku));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->normalizeProductRow($row);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeProductRow(array $row): array
    {
        return array(
            'id' => (int) $row['id'],
            'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
            'name' => (string) $row['name'],
            'sku' => isset($row['sku']) ? (string) $row['sku'] : null,
            'description' => isset($row['description']) ? (string) $row['description'] : null,
            'price' => isset($row['price']) ? (float) $row['price'] : 0.0,
            'status' => isset($row['status']) ? (string) $row['status'] : 'inactive',
            'automatic_delivery' => isset($row['automatic_delivery']) ? (int) $row['automatic_delivery'] === 1 : false,
            'provider_code' => isset($row['provider_code']) ? (string) $row['provider_code'] : null,
            'provider_product_id' => isset($row['provider_product_id']) ? (string) $row['provider_product_id'] : null,
            'category_name' => isset($row['category_name']) ? (string) $row['category_name'] : null,
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}
