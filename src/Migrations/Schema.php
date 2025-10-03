<?php declare(strict_types=1);

namespace App\Migrations;

use App\Db;
use PDO;
use PDOException;

final class Schema
{
    private const PROVIDER_NAME = 'Lotus';

    public static function ensure(): void
    {
        try {
            $pdo = Db::pdo();
        } catch (PDOException $exception) {
            error_log('[Schema] PDO bağlantısı alınamadı: ' . $exception->getMessage());
            return;
        }

        self::createProvidersTable($pdo);
        self::createLotusProductsMapTable($pdo);
        self::ensureProductsTable($pdo);
        self::createProductStockTable($pdo);
        self::seedProviderRow($pdo);
    }

    private static function createProvidersTable(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS providers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(64) NOT NULL UNIQUE,
            api_url VARCHAR(255) NOT NULL,
            api_key VARCHAR(255) NOT NULL,
            timeout_ms INT NOT NULL DEFAULT 20000,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $pdo->exec($sql);
    }

    private static function createLotusProductsMapTable(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS lotus_products_map (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lotus_product_id BIGINT NOT NULL UNIQUE,
            local_product_id BIGINT NULL,
            title VARCHAR(255) NULL,
            snapshot MEDIUMTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $pdo->exec($sql);
    }

    private static function ensureProductsTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            sku VARCHAR(150) NULL,
            description MEDIUMTEXT NULL,
            cost_price_try DECIMAL(12,2) NULL,
            price DECIMAL(12,2) NOT NULL DEFAULT 0,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            provider_code VARCHAR(100) NULL,
            provider_product_id VARCHAR(100) NULL,
            lotus_product_id BIGINT NULL UNIQUE,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX (lotus_product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!self::columnExists($pdo, 'products', 'lotus_product_id')) {
            $pdo->exec("ALTER TABLE products ADD COLUMN lotus_product_id BIGINT NULL UNIQUE");
        }
    }

    private static function createProductStockTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_stock_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            content MEDIUMTEXT NOT NULL,
            content_hash CHAR(64) NOT NULL,
            status ENUM('available','reserved','delivered') NOT NULL DEFAULT 'available',
            order_id INT NULL,
            reserved_at DATETIME NULL,
            delivered_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_product_stock_hash (product_id, content_hash),
            INDEX idx_stock_status (product_id, status),
            INDEX idx_stock_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'product_stock_items', 'content_hash', "CHAR(64) NOT NULL AFTER content");
        self::ensureColumn($pdo, 'product_stock_items', 'reserved_at', 'DATETIME NULL');
        self::ensureColumn($pdo, 'product_stock_items', 'delivered_at', 'DATETIME NULL');
        self::ensureColumn($pdo, 'product_stock_items', 'updated_at', 'DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP');

        try {
            $pdo->exec('ALTER TABLE product_stock_items ADD UNIQUE KEY uniq_product_stock_hash (product_id, content_hash)');
        } catch (\PDOException $exception) {
            // index already exists
        }

        try {
            $pdo->exec('ALTER TABLE product_stock_items ADD INDEX idx_stock_status (product_id, status)');
        } catch (\PDOException $exception) {
            // index already exists
        }

        try {
            $pdo->exec('ALTER TABLE product_stock_items ADD INDEX idx_stock_order (order_id)');
        } catch (\PDOException $exception) {
            // index already exists
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE :column');
        $stmt->execute(array(':column' => $column));
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (self::columnExists($pdo, $table, $column)) {
            return;
        }

        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    private static function seedProviderRow(PDO $pdo): void
    {
        self::ensureColumn($pdo, 'providers', 'timeout_ms', 'INT NOT NULL DEFAULT 20000');

        $stmt = $pdo->prepare('SELECT id FROM providers WHERE name = :name LIMIT 1');
        $stmt->execute(array(':name' => self::PROVIDER_NAME));
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            return;
        }

        $insert = $pdo->prepare('INSERT INTO providers (name, api_url, api_key, timeout_ms, status) VALUES (:name, :url, :key, 20000, 0)');
        $insert->execute(array(
            ':name' => self::PROVIDER_NAME,
            ':url' => 'https://partner.lotuslisans.com.tr',
            ':key' => '',
        ));
    }
}
