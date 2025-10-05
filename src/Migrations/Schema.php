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

        self::ensureLanguagesTable($pdo);
        self::ensureLanguageTranslationsTable($pdo);
        self::ensureCurrenciesTable($pdo);
        self::ensureProvidersTable($pdo);
        self::ensureProviderProductsTable($pdo);
        self::ensureCategoriesTable($pdo);
        self::ensureProductsTable($pdo);
        self::ensureProductStockTable($pdo);
        self::ensureResellerFavoritesTable($pdo);
        self::ensureStockWatchersTable($pdo);
        self::ensureApiTokens($pdo);
        self::ensureApiRateLimitTable($pdo);
        self::ensureApiRequestLogTable($pdo);
        self::ensureAutoTopupTable($pdo);
        self::ensureUserLocaleColumns($pdo);
        self::ensureBlogCategoriesTable($pdo);
        self::ensureBlogPostsTable($pdo);
        self::ensureInstructionsTable($pdo);

    }

    private static function ensureLanguagesTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS languages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(10) NOT NULL,
            name VARCHAR(100) NOT NULL,
            native_name VARCHAR(100) NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_languages_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'languages', 'native_name', "VARCHAR(100) NOT NULL DEFAULT ''");
        self::ensureColumn($pdo, 'languages', 'is_default', 'TINYINT(1) NOT NULL DEFAULT 0');
        self::ensureColumn($pdo, 'languages', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');

        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM languages')->fetchColumn();
        } catch (PDOException $exception) {
            $count = 0;
        }

        if ($count === 0) {
            $insert = $pdo->prepare('INSERT INTO languages (code, name, native_name, is_default, is_active, created_at) VALUES (:code, :name, :native_name, :is_default, 1, NOW())');
            $insert->execute(array('code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => 1));
            $insert->execute(array('code' => 'tr', 'name' => 'Turkish', 'native_name' => 'Türkçe', 'is_default' => 0));
        } else {
            $pdo->exec("INSERT IGNORE INTO languages (code, name, native_name, is_default, is_active, created_at) VALUES
                ('en', 'English', 'English', 0, 1, NOW()),
                ('tr', 'Turkish', 'Türkçe', 0, 1, NOW())");

            $defaultCount = (int) $pdo->query('SELECT COUNT(*) FROM languages WHERE is_default = 1')->fetchColumn();
            if ($defaultCount === 0) {
                $pdo->exec("UPDATE languages SET is_default = CASE WHEN code = 'en' THEN 1 ELSE 0 END");
            }
        }
    }

    private static function ensureLanguageTranslationsTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS language_translations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            language_code VARCHAR(10) NOT NULL,
            translation_key VARCHAR(255) NOT NULL,
            translation_value TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_language_key (language_code, translation_key),
            INDEX idx_language_code (language_code),
            CONSTRAINT fk_language_code FOREIGN KEY (language_code) REFERENCES languages(code) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'language_translations', 'translation_value', 'TEXT NOT NULL');
    }

    private static function ensureCurrenciesTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS currencies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(3) NOT NULL,
            name VARCHAR(100) NOT NULL,
            symbol VARCHAR(10) NOT NULL,
            rate DECIMAL(18,8) NOT NULL DEFAULT 1.00000000,
            decimals TINYINT(1) NOT NULL DEFAULT 2,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            auto_update TINYINT(1) NOT NULL DEFAULT 0,
            last_rate_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_currencies_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'currencies', 'decimals', 'TINYINT(1) NOT NULL DEFAULT 2');
        self::ensureColumn($pdo, 'currencies', 'auto_update', 'TINYINT(1) NOT NULL DEFAULT 0');
        self::ensureColumn($pdo, 'currencies', 'last_rate_at', 'DATETIME NULL');

        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM currencies')->fetchColumn();
        } catch (PDOException $exception) {
            $count = 0;
        }

        if ($count === 0) {
            $stmt = $pdo->prepare('INSERT INTO currencies (code, name, symbol, rate, decimals, is_default, is_active, auto_update, created_at) VALUES (:code, :name, :symbol, :rate, :decimals, :is_default, 1, :auto_update, NOW())');
            $stmt->execute(array('code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate' => 1.0, 'decimals' => 2, 'is_default' => 1, 'auto_update' => 0));
            $stmt->execute(array('code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate' => 0.90, 'decimals' => 2, 'is_default' => 0, 'auto_update' => 1));
            $stmt->execute(array('code' => 'TRY', 'name' => 'Türk Lirası', 'symbol' => '₺', 'rate' => 27.00, 'decimals' => 2, 'is_default' => 0, 'auto_update' => 1));
        } else {
            $pdo->exec("INSERT IGNORE INTO currencies (code, name, symbol, rate, decimals, is_default, is_active, auto_update, created_at) VALUES
                ('USD', 'US Dollar', '$', 1.0, 2, 0, 1, 0, NOW()),
                ('EUR', 'Euro', '€', 0.90, 2, 0, 1, 1, NOW()),
                ('TRY', 'Türk Lirası', '₺', 27.00, 2, 0, 1, 1, NOW())");

            $defaultCount = (int) $pdo->query('SELECT COUNT(*) FROM currencies WHERE is_default = 1')->fetchColumn();
            if ($defaultCount === 0) {
                $pdo->exec("UPDATE currencies SET is_default = CASE WHEN code = 'USD' THEN 1 ELSE 0 END, rate = CASE WHEN code = 'USD' THEN 1.0 ELSE rate END");
            }
        }
    }

    private static function ensureProvidersTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS providers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            code VARCHAR(100) NOT NULL,
            driver VARCHAR(50) NOT NULL DEFAULT 'generic',
            base_url VARCHAR(255) NOT NULL,
            api_key VARCHAR(191) NOT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
            settings TEXT NULL,
            last_synced_at DATETIME NULL,
            last_sync_status VARCHAR(50) NULL,
            last_sync_error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_provider_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'providers', 'driver', "VARCHAR(50) NOT NULL DEFAULT 'generic'");
        self::ensureColumn($pdo, 'providers', 'settings', 'TEXT NULL');
        self::ensureColumn($pdo, 'providers', 'last_synced_at', 'DATETIME NULL');
        self::ensureColumn($pdo, 'providers', 'last_sync_status', 'VARCHAR(50) NULL');
        self::ensureColumn($pdo, 'providers', 'last_sync_error', 'TEXT NULL');
    }

    private static function ensureProviderProductsTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS provider_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_id INT NOT NULL,
            external_id VARCHAR(191) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description MEDIUMTEXT NULL,
            price DECIMAL(16,4) NULL,
            currency VARCHAR(10) NULL,
            stock INT NULL,
            is_available TINYINT(1) NOT NULL DEFAULT 0,
            payload MEDIUMTEXT NULL,
            last_synced_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_provider_product (provider_id, external_id),
            FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'provider_products', 'currency', 'VARCHAR(10) NULL');
        self::ensureColumn($pdo, 'provider_products', 'stock', 'INT NULL');
        self::ensureColumn($pdo, 'provider_products', 'is_available', 'TINYINT(1) NOT NULL DEFAULT 0');
        self::ensureColumn($pdo, 'provider_products', 'payload', 'MEDIUMTEXT NULL');
        self::ensureColumn($pdo, 'provider_products', 'last_synced_at', 'DATETIME NULL');
    }

    private static function ensureCategoriesTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NULL,
            name VARCHAR(150) NOT NULL,
            description TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'categories', 'parent_id', 'INT NULL');
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
            automatic_delivery TINYINT(1) NOT NULL DEFAULT 1,
            provider_code VARCHAR(100) NULL,
            provider_product_id VARCHAR(100) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'products', 'provider_code', "VARCHAR(100) NULL");
        self::ensureColumn($pdo, 'products', 'provider_product_id', "VARCHAR(100) NULL");
        self::ensureColumn($pdo, 'products', 'automatic_delivery', "TINYINT(1) NOT NULL DEFAULT 1");
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

    private static function ensureBlogCategoriesTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS blog_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            description TEXT NULL,
            meta_title VARCHAR(150) NULL,
            meta_description VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_blog_category_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'blog_categories', 'meta_title', 'VARCHAR(150) NULL');
        self::ensureColumn($pdo, 'blog_categories', 'meta_description', 'VARCHAR(255) NULL');
    }

    private static function ensureBlogPostsTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NULL,
            title VARCHAR(191) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            excerpt TEXT NULL,
            content MEDIUMTEXT NOT NULL,
            image_url VARCHAR(255) NULL,
            author_name VARCHAR(150) NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            meta_title VARCHAR(191) NULL,
            meta_description VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
            UNIQUE KEY uniq_blog_post_slug (slug),
            INDEX idx_blog_status (status),
            INDEX idx_blog_category (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'blog_posts', 'meta_title', 'VARCHAR(191) NULL');
        self::ensureColumn($pdo, 'blog_posts', 'meta_description', 'VARCHAR(255) NULL');
        self::ensureColumn($pdo, 'blog_posts', 'author_name', 'VARCHAR(150) NULL');
        self::ensureColumn($pdo, 'blog_posts', 'image_url', 'VARCHAR(255) NULL');
    }

    private static function ensureInstructionsTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS instructions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(191) NOT NULL,
            summary VARCHAR(255) NULL,
            content MEDIUMTEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'instructions', 'summary', 'VARCHAR(255) NULL');
        self::ensureColumn($pdo, 'instructions', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
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
