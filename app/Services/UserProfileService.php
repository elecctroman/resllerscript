<?php

namespace App\Services;

use App\Database;
use PDO;
use PDOException;

class UserProfileService
{
    /**
     * Fetch the extended profile for a user.
     *
     * @param int $userId
     * @return array<string,string>
     */
    public static function fetch(int $userId): array
    {
        $defaults = array(
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
            'country' => '',
            'city' => '',
            'district' => '',
            'address' => '',
        );

        if ($userId <= 0) {
            return $defaults;
        }

        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return $defaults;
        }

        try {
            $stmt = $pdo->prepare('SELECT first_name, last_name, phone, country, city, district, address FROM user_profiles WHERE user_id = :user_id LIMIT 1');
            $stmt->execute(array('user_id' => $userId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return $defaults;
            }

            return array_merge($defaults, array_map('strval', $row));
        } catch (PDOException $exception) {
            return $defaults;
        }
    }

    /**
     * Persist profile details.
     *
     * @param int   $userId
     * @param array<string,string> $data
     * @return void
     */
    public static function save(int $userId, array $data): void
    {
        if ($userId <= 0) {
            return;
        }

        $firstName = isset($data['first_name']) ? trim((string) $data['first_name']) : '';
        $lastName  = isset($data['last_name']) ? trim((string) $data['last_name']) : '';
        $phone     = isset($data['phone']) ? trim((string) $data['phone']) : '';
        $country   = isset($data['country']) ? trim((string) $data['country']) : '';
        $city      = isset($data['city']) ? trim((string) $data['city']) : '';
        $district  = isset($data['district']) ? trim((string) $data['district']) : '';
        $address   = isset($data['address']) ? trim((string) $data['address']) : '';

        $fullName = trim($firstName . ' ' . $lastName);

        $payload = array(
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'country' => $country,
            'city' => $city,
            'district' => $district,
            'address' => $address,
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $existing = $pdo->prepare('SELECT id FROM user_profiles WHERE user_id = :user_id LIMIT 1');
            $existing->execute(array('user_id' => $userId));
            $row = $existing->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $update = $pdo->prepare('UPDATE user_profiles SET first_name = :first_name, last_name = :last_name, phone = :phone, country = :country, city = :city, district = :district, address = :address, updated_at = NOW() WHERE user_id = :user_id');
                $update->execute(array_merge($payload, array('user_id' => $userId)));
            } else {
                $insert = $pdo->prepare('INSERT INTO user_profiles (user_id, first_name, last_name, phone, country, city, district, address, created_at, updated_at) VALUES (:user_id, :first_name, :last_name, :phone, :country, :city, :district, :address, NOW(), NOW())');
                $insert->execute(array_merge(array('user_id' => $userId), $payload));
            }

            if ($fullName !== '') {
                $updateUser = $pdo->prepare('UPDATE users SET name = :name, updated_at = NOW() WHERE id = :id');
                $updateUser->execute(array('name' => $fullName, 'id' => $userId));
            }

            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
