<?php declare(strict_types=1);

namespace App\Api\Repositories;

use App\Database;
use PDO;

/**
 * Bayi kullanıcı kayıtlarını sağlayan depo sınıfı.
 */
final class UserRepository
{
    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $userId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, email, balance, status, role, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $userId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return array(
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'balance' => isset($row['balance']) ? (float) $row['balance'] : 0.0,
            'status' => isset($row['status']) ? (string) $row['status'] : 'inactive',
            'role' => isset($row['role']) ? (string) $row['role'] : 'reseller',
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}
