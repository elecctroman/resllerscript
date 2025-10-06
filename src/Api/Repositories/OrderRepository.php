<?php declare(strict_types=1);

namespace App\Api\Repositories;

use App\Database;
use PDO;

/**
 * Sipariş kayıtlarını kullanıcı bazında okuyan depo sınıfı.
 */
final class OrderRepository
{
    /**
     * @return array<string,mixed>|null
     */
    public function findForUserById(int $orderId, int $userId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT po.*, p.name AS product_name, p.sku AS product_sku ' .
            'FROM product_orders po INNER JOIN products p ON p.id = po.product_id ' .
            'WHERE po.id = :id AND po.user_id = :user_id LIMIT 1'
        );
        $stmt->execute(array('id' => $orderId, 'user_id' => $userId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->normalizeOrderRow($row);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findForUserByReference(string $externalReference, int $userId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT po.*, p.name AS product_name, p.sku AS product_sku ' .
            'FROM product_orders po INNER JOIN products p ON p.id = po.product_id ' .
            'WHERE po.external_reference = :reference AND po.user_id = :user_id LIMIT 1'
        );
        $stmt->execute(array('reference' => $externalReference, 'user_id' => $userId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->normalizeOrderRow($row);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeOrderRow(array $row): array
    {
        $metadata = array();
        if (!empty($row['external_metadata'])) {
            $decoded = json_decode((string) $row['external_metadata'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        return array(
            'id' => (int) $row['id'],
            'product_id' => (int) $row['product_id'],
            'user_id' => (int) $row['user_id'],
            'api_token_id' => isset($row['api_token_id']) ? (int) $row['api_token_id'] : null,
            'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : 1,
            'price' => isset($row['price']) ? (float) $row['price'] : 0.0,
            'total_amount' => isset($row['total_amount']) ? (float) $row['total_amount'] : ((isset($row['price']) ? (float) $row['price'] : 0.0) * (isset($row['quantity']) ? (int) $row['quantity'] : 1)),
            'status' => isset($row['status']) ? (string) $row['status'] : 'pending',
            'note' => isset($row['note']) ? (string) $row['note'] : null,
            'admin_note' => isset($row['admin_note']) ? (string) $row['admin_note'] : null,
            'external_reference' => isset($row['external_reference']) ? (string) $row['external_reference'] : null,
            'product_name' => isset($row['product_name']) ? (string) $row['product_name'] : null,
            'product_sku' => isset($row['product_sku']) ? (string) $row['product_sku'] : null,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            'metadata' => $metadata,
        );
    }
}
