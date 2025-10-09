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

    public static function create(string $title, string $body, string $audience = 'admin', bool $pinned = false, ?int $createdBy = null): ?int
    {
        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return null;
        }

        $stmt = $pdo->prepare('INSERT INTO announcements (title, body, audience, is_active, pinned, created_by, created_at) VALUES (:title, :body, :audience, 1, :pinned, :created_by, NOW())');
        $stmt->execute(array(
            'title' => $title,
            'body' => $body,
            'audience' => in_array($audience, array('admin', 'reseller', 'all'), true) ? $audience : 'admin',
            'pinned' => $pinned ? 1 : 0,
            'created_by' => $createdBy,
        ));

        return (int) $pdo->lastInsertId();
    }

    public static function deactivate(int $announcementId): void
    {
        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return;
        }

        $pdo->prepare('UPDATE announcements SET is_active = 0, updated_at = NOW() WHERE id = :id')->execute(array('id' => $announcementId));
    }
}
