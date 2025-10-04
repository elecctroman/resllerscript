<?php declare(strict_types=1);

namespace App\Migrations;

use App\Db;
use PDO;
use PDOException;

final class Schema
{
    public static function ensure(): void
    {
        try {
            $pdo = Db::pdo();
        } catch (PDOException $exception) {
            error_log('[Schema] PDO bağlantısı alınamadı: ' . $exception->getMessage());
            return;
        }

        self::ensureProductsTable($pdo);
        self::ensureProductStockTable($pdo);
        self::ensureResellerFavoritesTable($pdo);
        self::ensureStockWatchersTable($pdo);
        self::ensureApiTokens($pdo);
        self::ensureApiRateLimitTable($pdo);
        self::ensureApiRequestLogTable($pdo);
        self::ensureAutoTopupTable($pdo);
        self::ensureUserLocaleColumns($pdo);
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
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'products', 'provider_code', "VARCHAR(100) NULL");
        self::ensureColumn($pdo, 'products', 'provider_product_id', "VARCHAR(100) NULL");
    }

    private static function ensureProductStockTable(PDO $pdo): void
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

        self::addIndex($pdo, 'product_stock_items', 'idx_stock_status', 'ADD INDEX idx_stock_status (product_id, status)');
        self::addIndex($pdo, 'product_stock_items', 'idx_stock_order', 'ADD INDEX idx_stock_order (order_id)');
    }

    private static function ensureResellerFavoritesTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_reseller_favorite (user_id, product_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private static function ensureStockWatchersTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_stock_watchers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            notified_at DATETIME NULL,
            UNIQUE KEY uniq_stock_watch (user_id, product_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private static function ensureApiTokens(PDO $pdo): void
    {
        self::ensureColumn($pdo, 'api_tokens', 'status', "ENUM('active','disabled') NOT NULL DEFAULT 'active'");
        self::ensureColumn($pdo, 'api_tokens', 'scopes', 'TEXT NULL');
        self::ensureColumn($pdo, 'api_tokens', 'ip_whitelist', 'TEXT NULL');
        self::ensureColumn($pdo, 'api_tokens', 'otp_secret', 'VARCHAR(64) NULL');
        self::ensureColumn($pdo, 'api_tokens', 'last_rotated_at', 'DATETIME NULL');
    }

    private static function ensureApiRateLimitTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_rate_limits (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            token_id INT NULL,
            ip_address VARCHAR(45) NOT NULL,
            bucket VARCHAR(64) NOT NULL,
            hits INT NOT NULL DEFAULT 0,
            period_start DATETIME NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_rate_bucket (token_id, ip_address, bucket),
            INDEX idx_rate_period (period_start),
            FOREIGN KEY (token_id) REFERENCES api_tokens(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private static function ensureApiRequestLogTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_request_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            token_id INT NULL,
            ip_address VARCHAR(45) NOT NULL,
            method VARCHAR(10) NOT NULL,
            endpoint VARCHAR(191) NOT NULL,
            status_code INT NOT NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (token_id) REFERENCES api_tokens(id) ON DELETE SET NULL,
            INDEX idx_api_logs_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private static function ensureAutoTopupTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS balance_auto_topups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            threshold DECIMAL(12,2) NOT NULL,
            topup_amount DECIMAL(12,2) NOT NULL,
            payment_method VARCHAR(100) NOT NULL,
            status ENUM('active','paused') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private static function ensureUserLocaleColumns(PDO $pdo): void
    {
        self::ensureColumn($pdo, 'users', 'locale', "VARCHAR(5) NULL");
        self::ensureColumn($pdo, 'users', 'currency', "VARCHAR(3) NULL");
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (self::columnExists($pdo, $table, $column)) {
            return;
        }

        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    private static function addIndex(PDO $pdo, string $table, string $indexName, string $statement): void
    {
        $stmt = $pdo->prepare('SHOW INDEX FROM ' . $table . ' WHERE Key_name = :name');
        $stmt->execute(array(':name' => $indexName));
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return;
        }

        $pdo->exec('ALTER TABLE ' . $table . ' ' . $statement);
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE :column');
        $stmt->execute(array(':column' => $column));
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
