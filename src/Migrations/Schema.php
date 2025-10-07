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
        self::ensureCategoriesTable($pdo);
        self::ensureProductsTable($pdo);
        self::ensureProductStockTable($pdo);
        self::ensureProductOrdersTable($pdo);
        self::ensureExternalProvidersTable($pdo);
        self::ensureExternalProviderProductsTable($pdo);
        self::ensureResellerFavoritesTable($pdo);
        self::ensureStockWatchersTable($pdo);
        self::ensureAutoTopupTable($pdo);
        self::ensureUserLocaleColumns($pdo);
        self::ensureBlogCategoriesTable($pdo);
        self::ensureBlogPostsTable($pdo);
        self::ensureInstructionsTable($pdo);
        self::ensureAnnouncementsTable($pdo);
        self::ensureUserApiKeyColumn($pdo);

    }

    private static function ensureUserApiKeyColumn(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'users')) {
            return;
        }

        self::ensureColumn($pdo, 'users', 'api_key', 'VARCHAR(128) NULL');
        self::addIndex($pdo, 'users', 'uniq_users_api_key', 'ADD UNIQUE INDEX uniq_users_api_key (api_key)');
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
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

    private static function ensureProductOrdersTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            user_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            note TEXT NULL,
            admin_note TEXT NULL,
            price DECIMAL(12,2) NOT NULL,
            total_amount DECIMAL(12,2) NOT NULL,
            source VARCHAR(50) NULL,
            external_reference VARCHAR(191) NULL,
            external_metadata TEXT NULL,
            status ENUM('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            INDEX idx_product_orders_user_created (user_id, created_at),
            INDEX idx_product_orders_status (status),
            INDEX idx_product_orders_external (external_reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'product_orders', 'total_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER price');
        self::ensureColumn($pdo, 'product_orders', 'updated_at', 'DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP');

        try {
            $pdo->exec('UPDATE product_orders SET total_amount = price WHERE total_amount = 0 OR total_amount IS NULL');
        } catch (PDOException $exception) {
            error_log('[Schema] product_orders total_amount backfill failed: ' . $exception->getMessage());
        }

        self::addIndex($pdo, 'product_orders', 'idx_product_orders_user_created', 'ADD INDEX idx_product_orders_user_created (user_id, created_at)');
        self::addIndex($pdo, 'product_orders', 'idx_product_orders_status', 'ADD INDEX idx_product_orders_status (status)');
        self::addIndex($pdo, 'product_orders', 'idx_product_orders_external', 'ADD INDEX idx_product_orders_external (external_reference)');
    }

    private static function ensureExternalProvidersTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS external_providers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            base_url VARCHAR(255) NOT NULL,
            api_key VARCHAR(191) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_tested_at DATETIME NULL,
            last_test_response TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'external_providers', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
        self::ensureColumn($pdo, 'external_providers', 'last_tested_at', 'DATETIME NULL');
        self::ensureColumn($pdo, 'external_providers', 'last_test_response', 'TEXT NULL');
    }

    private static function ensureExternalProviderProductsTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS external_provider_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_id INT NOT NULL,
            provider_product_id VARCHAR(100) NOT NULL,
            product_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_external_provider_product (provider_id, provider_product_id),
            UNIQUE KEY uniq_external_provider_local (product_id),
            CONSTRAINT fk_external_provider_product_provider FOREIGN KEY (provider_id) REFERENCES external_providers(id) ON DELETE CASCADE,
            CONSTRAINT fk_external_provider_product_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::addIndex($pdo, 'external_provider_products', 'uniq_external_provider_product', 'ADD UNIQUE INDEX uniq_external_provider_product (provider_id, provider_product_id)');
        self::addIndex($pdo, 'external_provider_products', 'uniq_external_provider_local', 'ADD UNIQUE INDEX uniq_external_provider_local (product_id)');
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
        self::dropColumnIfExists($pdo, 'users', 'currency');
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

    private static function ensureAnnouncementsTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(191) NOT NULL,
            body MEDIUMTEXT NOT NULL,
            audience ENUM('reseller','admin','all') NOT NULL DEFAULT 'reseller',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            pinned TINYINT(1) NOT NULL DEFAULT 0,
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_announcements_active (is_active, audience, starts_at, ends_at),
            KEY idx_announcements_pinned (pinned),
            CONSTRAINT fk_announcements_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureColumn($pdo, 'announcements', 'audience', "ENUM('reseller','admin','all') NOT NULL DEFAULT 'reseller'");
        self::ensureColumn($pdo, 'announcements', 'pinned', 'TINYINT(1) NOT NULL DEFAULT 0');
        self::ensureColumn($pdo, 'announcements', 'starts_at', 'DATETIME NULL');
        self::ensureColumn($pdo, 'announcements', 'ends_at', 'DATETIME NULL');
        self::ensureColumn($pdo, 'announcements', 'created_by', 'INT NULL');
    }


    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (self::columnExists($pdo, $table, $column)) {
            return;
        }

        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    private static function dropColumnIfExists(PDO $pdo, string $table, string $column): void
    {
        if (!self::columnExists($pdo, $table, $column)) {
            return;
        }

        $pdo->exec('ALTER TABLE ' . $table . ' DROP COLUMN ' . $column);
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

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute(array(':table_name' => $table));

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
