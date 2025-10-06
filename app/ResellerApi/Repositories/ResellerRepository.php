<?php

declare(strict_types=1);

namespace App\ResellerApi\Repositories;

use App\Database;
use PDO;

final class ResellerRepository
{
    public function ensureFromUser(array $user): array
    {
        $pdo = Database::connection();
        $email = isset($user['email']) ? (string) $user['email'] : '';
        $name = isset($user['name']) ? (string) $user['name'] : $email;
        $status = isset($user['status']) && $user['status'] === 'inactive' ? 'suspended' : 'active';
        $passwordHash = isset($user['password_hash']) ? (string) $user['password_hash'] : '';

        $stmt = $pdo->prepare('SELECT * FROM resellers WHERE email = :email LIMIT 1');
        $stmt->execute(array('email' => $email));
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $updates = array();
            $params = array('id' => $existing['id']);

            if ($existing['name'] !== $name) {
                $updates[] = 'name = :name';
                $params['name'] = $name;
            }
            if ($existing['password_hash'] !== $passwordHash && $passwordHash !== '') {
                $updates[] = 'password_hash = :password_hash';
                $params['password_hash'] = $passwordHash;
            }
            if ($existing['status'] !== $status) {
                $updates[] = 'status = :status';
                $params['status'] = $status;
            }

            if ($updates) {
                $sql = 'UPDATE resellers SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = :id';
                $pdo->prepare($sql)->execute($params);
                $stmt = $pdo->prepare('SELECT * FROM resellers WHERE id = :id LIMIT 1');
                $stmt->execute(array('id' => $existing['id']));
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            return $existing ?: array();
        }

        $insert = $pdo->prepare('INSERT INTO resellers (name, email, password_hash, status, created_at) VALUES (:name, :email, :password_hash, :status, NOW())');
        $insert->execute(array(
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'status' => $status,
        ));

        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM resellers WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $id));

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: array();
    }

    public function findById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM resellers WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM resellers WHERE email = :email LIMIT 1');
        $stmt->execute(array('email' => $email));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function mapResellerToUserId(array $reseller): ?int
    {
        $email = isset($reseller['email']) ? (string) $reseller['email'] : '';
        if ($email === '') {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(array('email' => $email));
        $userId = $stmt->fetchColumn();

        return $userId !== false ? (int) $userId : null;
    }
}
