<?php declare(strict_types=1);

namespace App\ResellerApi\Repositories;

use App\Database;
use PDO;

final class ResellerRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM resellers WHERE email = :email LIMIT 1');
        $stmt->execute(array('email' => $email));
        $reseller = $stmt->fetch(PDO::FETCH_ASSOC);
        return $reseller ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM resellers WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $id));
        $reseller = $stmt->fetch(PDO::FETCH_ASSOC);
        return $reseller ?: null;
    }

    public function findByApiKey(string $apiKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT r.* FROM api_keys k INNER JOIN resellers r ON r.id = k.reseller_id WHERE k.api_key = :key LIMIT 1');
        $stmt->execute(array('key' => $apiKey));
        $reseller = $stmt->fetch(PDO::FETCH_ASSOC);
        return $reseller ?: null;
    }

    public function ensureForUser(array $user): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM resellers WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(array('user_id' => $user['id']));
        $reseller = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($reseller) {
            return $reseller;
        }

        $insert = $this->pdo->prepare('INSERT INTO resellers (user_id, name, email, password_hash, status, created_at, updated_at) VALUES (:user_id, :name, :email, :password_hash, :status, NOW(), NOW())');
        $insert->execute(array(
            'user_id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'password_hash' => $user['password_hash'],
            'status' => $user['status'] === 'active' ? 'active' : 'suspended',
        ));

        return $this->findById((int) $this->pdo->lastInsertId());
    }

    public function updatePasswordHash(int $resellerId, string $hash): void
    {
        $stmt = $this->pdo->prepare('UPDATE resellers SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('hash' => $hash, 'id' => $resellerId));
    }
}
