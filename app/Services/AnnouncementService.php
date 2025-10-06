<?php declare(strict_types=1);

namespace App\Services;

use App\Auth;
use App\Database;
use PDO;
use PDOException;

final class AnnouncementService
{
    /**
     * Fetch active announcements for the given user audience.
     *
     * @param array<string,mixed> $user
     * @param int $limit
     * @param array<int> $excludeIds
     * @return array<int,array<string,mixed>>
     */
    public static function activeForUser(array $user, int $limit = 5, array $excludeIds = array()): array
    {
        if (!$user) {
            return array();
        }

        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return array();
        }

        $audience = Auth::isAdminRole(isset($user['role']) ? (string)$user['role'] : '') ? 'admin' : 'reseller';
        $now = date('Y-m-d H:i:s');

        $sql = "SELECT id, title, body, audience, pinned, starts_at, ends_at, created_at FROM announcements
                WHERE is_active = 1
                  AND (audience = :audience OR audience = 'all')
                  AND (starts_at IS NULL OR starts_at <= :now)
                  AND (ends_at IS NULL OR ends_at >= :now)";

        $params = array(
            'audience' => $audience,
            'now' => $now,
        );

        if ($excludeIds) {
            $excludePlaceholders = array();
            foreach ($excludeIds as $index => $excludeId) {
                $key = 'exclude' . $index;
                $excludePlaceholders[] = ':' . $key;
                $params[$key] = (int) $excludeId;
            }

            if ($excludePlaceholders) {
                $sql .= ' AND id NOT IN (' . implode(',', $excludePlaceholders) . ')';
            }
        }

        $sql .= " ORDER BY pinned DESC, COALESCE(starts_at, created_at) DESC, created_at DESC";

        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $exception) {
            return array();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function latest(int $limit = 10): array
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("SELECT * FROM announcements ORDER BY created_at DESC LIMIT :limit");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        } catch (PDOException $exception) {
            return array();
        }
    }
}
