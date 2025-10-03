<?php

namespace App\Models;

use App\Database;
use PDO;

class UserPurchase
{
    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function find($id)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM user_purchases WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => (int) $id));
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        return $purchase ? $purchase : null;
    }

    /**
     * @param int $userId
     * @param int $moduleId
     * @return array<string,mixed>|null
     */
    public static function findByUserAndModule($userId, $moduleId)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM user_purchases WHERE user_id = :user_id AND module_id = :module_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(array(
            'user_id' => (int) $userId,
            'module_id' => (int) $moduleId,
        ));
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        return $purchase ? $purchase : null;
    }

    /**
     * @param int $userId
     * @return array<int,array<string,mixed>>
     */
    public static function forUser($userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT up.*, pm.name AS module_name, pm.description AS module_description, pm.file_path, pm.price FROM user_purchases up INNER JOIN premium_modules pm ON up.module_id = pm.id WHERE up.user_id = :user_id ORDER BY up.created_at DESC');
        $stmt->execute(array('user_id' => (int) $userId));

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function allWithUsers(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT up.*, u.name AS user_name, u.email AS user_email, pm.name AS module_name FROM user_purchases up INNER JOIN users u ON up.user_id = u.id INNER JOIN premium_modules pm ON up.module_id = pm.id ORDER BY up.created_at DESC');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
    }

    /**
     * @param array<string,mixed> $data
     * @return int
     */
    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO user_purchases (user_id, module_id, payment_status, license_key, created_at) VALUES (:user_id, :module_id, :payment_status, :license_key, NOW())');
        $stmt->execute(array(
            'user_id' => (int) $data['user_id'],
            'module_id' => (int) $data['module_id'],
            'payment_status' => (string) $data['payment_status'],
            'license_key' => isset($data['license_key']) ? $data['license_key'] : null,
        ));

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param int $purchaseId
     * @param string $status
     * @return void
     */
    public static function updateStatus($purchaseId, $status)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE user_purchases SET payment_status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(array(
            'status' => (string) $status,
            'id' => (int) $purchaseId,
        ));
    }

    /**
     * @param int $purchaseId
     * @param string $licenseKey
     * @return void
     */
    public static function setLicenseKey($purchaseId, $licenseKey)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE user_purchases SET license_key = :license_key, updated_at = NOW() WHERE id = :id');
        $stmt->execute(array(
            'license_key' => $licenseKey,
            'id' => (int) $purchaseId,
        ));
    }
}
