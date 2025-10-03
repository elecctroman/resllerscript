<?php

namespace App\Models;

use App\Database;
use PDO;

class PremiumModule
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function all(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM premium_modules ORDER BY created_at DESC');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function active(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM premium_modules WHERE status = 1 ORDER BY name ASC');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
    }

    /**
     * @param int $moduleId
     * @return array<string,mixed>|null
     */
    public static function find($moduleId)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM premium_modules WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => (int) $moduleId));
        $module = $stmt->fetch(PDO::FETCH_ASSOC);

        return $module ? $module : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return int
     */
    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO premium_modules (name, description, price, file_path, status, created_at) VALUES (:name, :description, :price, :file_path, :status, NOW())');
        $stmt->execute(array(
            'name' => (string) $data['name'],
            'description' => (string) $data['description'],
            'price' => (float) $data['price'],
            'file_path' => (string) $data['file_path'],
            'status' => isset($data['status']) && (int)$data['status'] === 1 ? 1 : 0,
        ));

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param int  $moduleId
     * @param bool $active
     * @return bool
     */
    public static function setStatus($moduleId, $active)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE premium_modules SET status = :status, updated_at = NOW() WHERE id = :id');
        return $stmt->execute(array(
            'status' => $active ? 1 : 0,
            'id' => (int) $moduleId,
        ));
    }

    /**
     * @param int $moduleId
     * @param string $path
     * @return void
     */
    public static function updateFilePath($moduleId, $path)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE premium_modules SET file_path = :file_path, updated_at = NOW() WHERE id = :id');
        $stmt->execute(array(
            'file_path' => $path,
            'id' => (int) $moduleId,
        ));
    }

    /**
     * @param int $moduleId
     * @return void
     */
    public static function delete($moduleId)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM premium_modules WHERE id = :id');
        $stmt->execute(array('id' => (int) $moduleId));
    }
}
