<?php

namespace App\Models;

use App\Database;
use PDO;

class UserProfile
{
    /**
     * @param int $userId
     * @return array<string, mixed>
     */
    public static function get($userId)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM user_profiles WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(array('user_id' => (int)$userId));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row : array();
    }

    /**
     * @param int $userId
     * @param array<string, mixed> $data
     * @return void
     */
    public static function save($userId, array $data)
    {
        $pdo = Database::connection();

        $payload = array(
            'user_id' => (int)$userId,
            'first_name' => isset($data['first_name']) ? trim((string)$data['first_name']) : '',
            'last_name' => isset($data['last_name']) ? trim((string)$data['last_name']) : '',
            'phone' => isset($data['phone']) ? trim((string)$data['phone']) : null,
            'country' => isset($data['country']) ? trim((string)$data['country']) : null,
            'city' => isset($data['city']) ? trim((string)$data['city']) : null,
            'district' => isset($data['district']) ? trim((string)$data['district']) : null,
            'address' => isset($data['address']) ? trim((string)$data['address']) : null,
        );

        $sql = 'INSERT INTO user_profiles (user_id, first_name, last_name, phone, country, city, district, address, created_at, updated_at)
                VALUES (:user_id, :first_name, :last_name, :phone, :country, :city, :district, :address, NOW(), NOW())
                ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), phone = VALUES(phone),
                country = VALUES(country), city = VALUES(city), district = VALUES(district), address = VALUES(address), updated_at = NOW()';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($payload);
    }

    /**
     * @param string $fullName
     * @return array{first_name: string, last_name: string}
     */
    public static function splitName($fullName)
    {
        $fullName = trim((string)$fullName);

        if ($fullName === '') {
            return array('first_name' => '', 'last_name' => '');
        }

        $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || !$parts) {
            return array('first_name' => $fullName, 'last_name' => '');
        }

        $first = array_shift($parts);
        $last = $parts ? implode(' ', $parts) : '';

        return array('first_name' => (string)$first, 'last_name' => (string)$last);
    }

    /**
     * @param string $first
     * @param string $last
     * @return string
     */
    public static function buildFullName($first, $last)
    {
        $first = trim((string)$first);
        $last = trim((string)$last);

        if ($last === '') {
            return $first;
        }

        if ($first === '') {
            return $last;
        }

        return $first . ' ' . $last;
    }
}
