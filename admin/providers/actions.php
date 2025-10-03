<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Logger;
use App\LotusClient;
use App\LotusClientCurl;
use App\Migrations\Schema;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'error' => 'Geçersiz istek yöntemi.'));
    exit;
}

Auth::requireRoles(array('super_admin', 'admin'));

if (!Helpers::verifyCsrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'Oturum doğrulaması başarısız. Lütfen sayfayı yenileyin.'));
    exit;
}

Schema::ensure();

$pdo = Database::connection();
$logger = new Logger(__DIR__ . '/../../storage/lotus.log');

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';

try {
    switch ($action) {
        case 'save_settings':
            echo json_encode(saveSettings($pdo));
            break;
        case 'test_connection':
            echo json_encode(testConnection());
            break;
        case 'import_single':
            echo json_encode(importSingle($pdo));
            break;
        case 'import_bulk':
            echo json_encode(importBulk($pdo));
            break;
        default:
            http_response_code(400);
            echo json_encode(array('ok' => false, 'error' => 'Bilinmeyen işlem.'));
    }
} catch (\Throwable $exception) {
    $logger->error('Sağlayıcı işlem hatası: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'Beklenmeyen bir hata oluştu.'));
}

/**
 * @return array<string,mixed>
 */
function saveSettings(\PDO $pdo): array
{
    $apiUrl = isset($_POST['api_url']) ? trim((string) $_POST['api_url']) : '';
    $apiKey = isset($_POST['api_key']) ? trim((string) $_POST['api_key']) : '';
    $timeout = isset($_POST['timeout_ms']) ? (int) $_POST['timeout_ms'] : 20000;

    if ($apiUrl === '' || !filter_var($apiUrl, FILTER_VALIDATE_URL)) {
        return array('ok' => false, 'error' => 'Geçerli bir API URL girin.');
    }

    $apiUrl = rtrim($apiUrl, '/');
    if (preg_match('#/api$#i', $apiUrl)) {
        $apiUrl = rtrim(substr($apiUrl, 0, -4), '/');
    }

    if ($apiKey === '') {
        return array('ok' => false, 'error' => 'API anahtarı boş olamaz.');
    }

    $timeout = max(5000, min($timeout, 60000));

    $stmt = $pdo->prepare('INSERT INTO providers (name, api_url, api_key, timeout_ms, status) VALUES (:name, :url, :key, :timeout, 1)
        ON DUPLICATE KEY UPDATE api_url = VALUES(api_url), api_key = VALUES(api_key), timeout_ms = VALUES(timeout_ms), status = VALUES(status), updated_at = CURRENT_TIMESTAMP');
    $stmt->execute(array(
        ':name' => 'Lotus',
        ':url' => $apiUrl,
        ':key' => $apiKey,
        ':timeout' => $timeout,
    ));

    return array('ok' => true, 'message' => 'Ayarlar başarıyla kaydedildi.');
}

/**
 * @return array<string,mixed>
 */
function testConnection(): array
{
    global $logger;

    $apiUrl = isset($_POST['api_url']) ? trim((string) $_POST['api_url']) : '';
    $apiKey = isset($_POST['api_key']) ? trim((string) $_POST['api_key']) : '';
    $timeout = isset($_POST['timeout_ms']) ? (int) $_POST['timeout_ms'] : 20000;

    if ($apiUrl === '' || !filter_var($apiUrl, FILTER_VALIDATE_URL)) {
        return array('ok' => false, 'error' => 'Geçerli bir API URL girin.');
    }

    $apiUrl = rtrim($apiUrl, '/');
    if (preg_match('#/api$#i', $apiUrl)) {
        $apiUrl = rtrim(substr($apiUrl, 0, -4), '/');
    }

    if ($apiKey === '') {
        return array('ok' => false, 'error' => 'API anahtarı boş olamaz.');
    }

    $timeout = max(5000, min($timeout, 60000));
    $connectTimeout = min($timeout, 10000);

    try {
        if (class_exists('GuzzleHttp\\Client')) {
            $client = new LotusClient($apiUrl, $apiKey, $timeout, $connectTimeout, $logger);
        } else {
            $client = new LotusClientCurl($apiUrl, $apiKey, $timeout, $connectTimeout, $logger);
        }

        $response = $client->getUser();
        if (!isset($response['success']) || !$response['success']) {
            $message = isset($response['message']) ? (string) $response['message'] : 'Sağlayıcıdan yanıt alınamadı.';
            return array('ok' => false, 'error' => $message);
        }

        $credit = isset($response['data']['credit']) ? (string) $response['data']['credit'] : null;

        return array(
            'ok' => true,
            'message' => 'Bağlantı başarılı.',
            'credit' => $credit,
        );
    } catch (\Throwable $exception) {
        $logger->error('Lotus test bağlantısı başarısız: ' . $exception->getMessage());
        return array('ok' => false, 'error' => $exception->getMessage());
    }
}

/**
 * @return array<string,mixed>
 */
function importSingle(\PDO $pdo): array
{
    global $logger;

    $lotusId = isset($_POST['lotus_product_id']) ? (int) $_POST['lotus_product_id'] : 0;
    $title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
    $price = isset($_POST['price']) ? (float) $_POST['price'] : 0.0;
    $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
    $description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
    $snapshot = isset($_POST['snapshot']) ? (string) $_POST['snapshot'] : '';

    if ($lotusId <= 0 || $title === '' || $categoryId <= 0) {
        return array('ok' => false, 'error' => 'Eksik veya geçersiz veri gönderildi.');
    }

    if ($price < 0) {
        return array('ok' => false, 'error' => 'Fiyat negatif olamaz.');
    }

    $pdo->beginTransaction();

    try {
        $checkStmt = $pdo->prepare('SELECT id FROM products WHERE lotus_product_id = :lotus LIMIT 1');
        $checkStmt->execute(array(':lotus' => $lotusId));
        if ($checkStmt->fetch()) {
            $pdo->rollBack();
            return array('ok' => false, 'error' => 'Bu Lotus ürünü zaten içeri aktarılmış.');
        }

        $insertProduct = $pdo->prepare('INSERT INTO products (category_id, name, description, price, status, provider_product_id, lotus_product_id, created_at)
            VALUES (:category, :name, :description, :price, "active", :provider_id, :lotus_id, NOW())');
        $insertProduct->execute(array(
            ':category' => $categoryId,
            ':name' => $title,
            ':description' => $description,
            ':price' => $price,
            ':provider_id' => (string) $lotusId,
            ':lotus_id' => $lotusId,
        ));

        $localId = (int) $pdo->lastInsertId();

        $snapshotData = null;
        if ($snapshot !== '') {
            $decoded = json_decode($snapshot, true);
            if (is_array($decoded)) {
                $snapshotData = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        $mapStmt = $pdo->prepare('INSERT INTO lotus_products_map (lotus_product_id, local_product_id, title, snapshot)
            VALUES (:lotus, :local, :title, :snapshot)
            ON DUPLICATE KEY UPDATE local_product_id = VALUES(local_product_id), title = VALUES(title), snapshot = VALUES(snapshot), updated_at = CURRENT_TIMESTAMP');
        $mapStmt->execute(array(
            ':lotus' => $lotusId,
            ':local' => $localId,
            ':title' => $title,
            ':snapshot' => $snapshotData,
        ));

        $pdo->commit();

        $logger->info('Lotus ürünü içe aktarıldı: ' . $lotusId . ' => ' . $localId);

        return array('ok' => true, 'message' => 'Ürün başarıyla eklendi.');
    } catch (\Throwable $exception) {
        $pdo->rollBack();
        $logger->error('Lotus ürünü eklenemedi: ' . $exception->getMessage());
        return array('ok' => false, 'error' => 'Ürün ekleme sırasında hata oluştu.');
    }
}

/**
 * @return array<string,mixed>
 */
function importBulk(\PDO $pdo): array
{
    global $logger;

    $itemsRaw = isset($_POST['items']) ? (string) $_POST['items'] : '[]';
    $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
    $mode = isset($_POST['pricing_mode']) ? (string) $_POST['pricing_mode'] : 'copy';
    $percentage = isset($_POST['percentage']) ? (float) $_POST['percentage'] : 0.0;
    $fixed = isset($_POST['fixed']) ? (float) $_POST['fixed'] : 0.0;
    $skipExisting = !empty($_POST['skip_existing']);

    if ($categoryId <= 0) {
        return array('ok' => false, 'error' => 'Lütfen bir kategori seçin.');
    }

    $items = json_decode($itemsRaw, true);
    if (!is_array($items) || !$items) {
        return array('ok' => false, 'error' => 'Aktarılacak ürün seçilmedi.');
    }

    $created = 0;
    $skipped = 0;

    $pdo->beginTransaction();

    try {
        $checkStmt = $pdo->prepare('SELECT id FROM products WHERE lotus_product_id = :lotus LIMIT 1');
        $insertProduct = $pdo->prepare('INSERT INTO products (category_id, name, description, price, status, provider_product_id, lotus_product_id, created_at)
            VALUES (:category, :name, :description, :price, "active", :provider_id, :lotus_id, NOW())');
        $mapStmt = $pdo->prepare('INSERT INTO lotus_products_map (lotus_product_id, local_product_id, title, snapshot)
            VALUES (:lotus, :local, :title, :snapshot)
            ON DUPLICATE KEY UPDATE local_product_id = VALUES(local_product_id), title = VALUES(title), snapshot = VALUES(snapshot), updated_at = CURRENT_TIMESTAMP');

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $lotusId = isset($item['id']) ? (int) $item['id'] : 0;
            if ($lotusId <= 0) {
                continue;
            }

            $checkStmt->execute(array(':lotus' => $lotusId));
            if ($checkStmt->fetch()) {
                if ($skipExisting) {
                    $skipped++;
                    continue;
                }
                $pdo->rollBack();
                return array('ok' => false, 'error' => 'Bazı ürünler zaten eklenmiş. "Zaten eklenmiş ürünleri atla" seçeneğini kullanabilirsiniz.');
            }

            $title = isset($item['title']) ? trim((string) $item['title']) : ('Lotus #' . $lotusId);
            $amount = isset($item['amount']) ? (float) $item['amount'] : 0.0;
            $description = isset($item['content']) ? (string) $item['content'] : '';

            $price = $amount;
            if ($mode === 'percentage') {
                $price = $amount + ($amount * ($percentage / 100));
            } elseif ($mode === 'fixed') {
                $price = $amount + $fixed;
            }
            $price = max(0.0, $price);

            $insertProduct->execute(array(
                ':category' => $categoryId,
                ':name' => $title,
                ':description' => $description,
                ':price' => $price,
                ':provider_id' => (string) $lotusId,
                ':lotus_id' => $lotusId,
            ));

            $localId = (int) $pdo->lastInsertId();
            $snapshot = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $mapStmt->execute(array(
                ':lotus' => $lotusId,
                ':local' => $localId,
                ':title' => $title,
                ':snapshot' => $snapshot,
            ));

            $created++;
        }

        $pdo->commit();
        $logger->info('Lotus toplu aktarım tamamlandı. Eklenen: ' . $created . ', atlanan: ' . $skipped);

        return array('ok' => true, 'message' => 'Toplu aktarım tamamlandı.', 'created' => $created, 'skipped' => $skipped);
    } catch (\Throwable $exception) {
        $pdo->rollBack();
        $logger->error('Lotus toplu aktarım hatası: ' . $exception->getMessage());
        return array('ok' => false, 'error' => 'Toplu aktarım sırasında hata oluştu.');
    }
}
