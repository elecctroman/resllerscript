<?php

namespace App\Blog;

use App\Database;
use App\Helpers;
use PDO;

class BlogRepository
{
    /**
     * @param bool $onlyPublished
     * @return array<int,array<string,mixed>>
     */
    public static function categories($onlyPublished = true)
    {
        $pdo = Database::connection();

        $sql = "SELECT c.id, c.name, c.slug, c.description, c.meta_title, c.meta_description, c.created_at, c.updated_at,"
            . " COUNT(p.id) AS post_count"
            . " FROM blog_categories AS c"
            . " LEFT JOIN blog_posts AS p ON p.category_id = c.id";

        if ($onlyPublished) {
            $sql .= " AND p.status = 'published'";
        }

        $sql .= " GROUP BY c.id ORDER BY c.name ASC";

        $stmt = $pdo->query($sql);
        if (!$stmt) {
            return array();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    /**
     * @param string $slug
     * @return array<string,mixed>|null
     */
    public static function findCategoryBySlug($slug)
    {
        if ($slug === '') {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM blog_categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(array(':slug' => $slug));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function findCategoryById($id)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM blog_categories WHERE id = :id LIMIT 1');
        $stmt->execute(array(':id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    public static function latestPosts($limit = 5)
    {
        $limit = max(1, (int)$limit);
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug"
            . " FROM blog_posts AS p"
            . " LEFT JOIN blog_categories AS c ON c.id = p.category_id"
            . " WHERE p.status = 'published'"
            . " ORDER BY COALESCE(p.published_at, p.created_at) DESC"
            . " LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    /**
     * @param int $limit
     * @param int $offset
     * @param int|null $categoryId
     * @param bool $publishedOnly
     * @return array<int,array<string,mixed>>
     */
    public static function listPosts($limit, $offset = 0, $categoryId = null, $publishedOnly = true)
    {
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        $pdo = Database::connection();

        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug"
            . " FROM blog_posts AS p"
            . " LEFT JOIN blog_categories AS c ON c.id = p.category_id"
            . " WHERE 1=1";

        $params = array();

        if ($publishedOnly) {
            $sql .= " AND p.status = 'published'";
        }

        if ($categoryId) {
            $sql .= " AND p.category_id = :category";
            $params[':category'] = (int)$categoryId;
        }

        $sql .= " ORDER BY COALESCE(p.published_at, p.created_at) DESC"
            . " LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    /**
     * @param int|null $categoryId
     * @param bool $publishedOnly
     * @return int
     */
    public static function countPosts($categoryId = null, $publishedOnly = true)
    {
        $pdo = Database::connection();

        $sql = "SELECT COUNT(*) FROM blog_posts WHERE 1=1";
        $params = array();

        if ($publishedOnly) {
            $sql .= " AND status = 'published'";
        }

        if ($categoryId) {
            $sql .= " AND category_id = :category";
            $params[':category'] = (int)$categoryId;
        }

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /**
     * @param string $slug
     * @param bool $publishedOnly
     * @return array<string,mixed>|null
     */
    public static function findPostBySlug($slug, $publishedOnly = true)
    {
        if ($slug === '') {
            return null;
        }

        $pdo = Database::connection();
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug"
            . " FROM blog_posts AS p"
            . " LEFT JOIN blog_categories AS c ON c.id = p.category_id"
            . " WHERE p.slug = :slug";

        if ($publishedOnly) {
            $sql .= " AND p.status = 'published'";
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(':slug' => $slug));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param int $postId
     * @param int|null $categoryId
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    public static function relatedPosts($postId, $categoryId = null, $limit = 3)
    {
        $pdo = Database::connection();

        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug"
            . " FROM blog_posts AS p"
            . " LEFT JOIN blog_categories AS c ON c.id = p.category_id"
            . " WHERE p.status = 'published' AND p.id != :id";

        $params = array(':id' => (int)$postId);

        if ($categoryId) {
            $sql .= " AND p.category_id = :category";
            $params[':category'] = (int)$categoryId;
        }

        $sql .= " ORDER BY COALESCE(p.published_at, p.created_at) DESC"
            . " LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', max(1, (int)$limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    /**
     * @param string $title
     * @param int|null $ignoreId
     * @return string
     */
    public static function uniquePostSlug($title, $ignoreId = null)
    {
        $slug = Helpers::slugify($title);
        if ($slug === '') {
            $slug = 'yazi';
        }

        $pdo = Database::connection();
        $base = $slug;
        $suffix = 2;

        while (true) {
            $query = 'SELECT id FROM blog_posts WHERE slug = :slug';
            $params = array(':slug' => $slug);
            if ($ignoreId) {
                $query .= ' AND id != :ignore';
                $params[':ignore'] = (int)$ignoreId;
            }

            $stmt = $pdo->prepare($query . ' LIMIT 1');
            $stmt->execute($params);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return $slug;
            }

            $slug = $base . '-' . $suffix;
            $suffix++;
        }
    }

    /**
     * @param string $name
     * @param int|null $ignoreId
     * @return string
     */
    public static function uniqueCategorySlug($name, $ignoreId = null)
    {
        $slug = Helpers::slugify($name);
        if ($slug === '') {
            $slug = 'kategori';
        }

        $pdo = Database::connection();
        $base = $slug;
        $suffix = 2;

        while (true) {
            $query = 'SELECT id FROM blog_categories WHERE slug = :slug';
            $params = array(':slug' => $slug);
            if ($ignoreId) {
                $query .= ' AND id != :ignore';
                $params[':ignore'] = (int)$ignoreId;
            }

            $stmt = $pdo->prepare($query . ' LIMIT 1');
            $stmt->execute($params);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return $slug;
            }

            $slug = $base . '-' . $suffix;
            $suffix++;
        }
    }
}
