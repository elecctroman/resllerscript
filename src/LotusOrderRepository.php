<?php declare(strict_types=1);

namespace App;

use PDO;

final class LotusOrderRepository
{
    private PDO $pdo;

    public function __construct(string $dbPath, Logger $logger)
    {
        $dir = \dirname($dbPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $dsn = 'sqlite:' . $dbPath;
        $this->pdo = new PDO($dsn);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $logger->info('SQLite bağlandı: ' . $dbPath);
        $this->migrate();
    }

    private function migrate(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS lotus_orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                local_order_id INTEGER UNIQUE NOT NULL,
                lotus_order_id INTEGER,
                status TEXT NOT NULL,
                content TEXT NULL,
                last_checked_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )"
        );
    }

    public function findByLocalOrderId(int $localOrderId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lotus_orders WHERE local_order_id = :id LIMIT 1');
        $stmt->execute([':id' => $localOrderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insert(int $localOrderId, int $lotusOrderId, string $status, ?string $content): void
    {
        $now = date('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO lotus_orders (local_order_id, lotus_order_id, status, content, created_at, updated_at)
             VALUES (:local_id, :lotus_id, :status, :content, :created, :updated)'
        );
        $stmt->execute([
            ':local_id' => $localOrderId,
            ':lotus_id' => $lotusOrderId,
            ':status' => $status,
            ':content' => $content,
            ':created' => $now,
            ':updated' => $now,
        ]);
    }

    public function updateStatusContent(int $localOrderId, string $status, ?string $content): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE lotus_orders SET status = :status, content = :content, updated_at = :updated WHERE local_order_id = :id'
        );
        $stmt->execute([
            ':status' => $status,
            ':content' => $content,
            ':updated' => date('c'),
            ':id' => $localOrderId,
        ]);
    }

    public function touchCheckedAt(int $localOrderId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE lotus_orders SET last_checked_at = :checked, updated_at = :updated WHERE local_order_id = :id'
        );
        $stmt->execute([
            ':checked' => date('c'),
            ':updated' => date('c'),
            ':id' => $localOrderId,
        ]);
    }

    /**
     * Pending kayıtları getir (en fazla $limit adet)
     */
    public function findPending(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM lotus_orders WHERE status = "pending" ORDER BY updated_at ASC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
