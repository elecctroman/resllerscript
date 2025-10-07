<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

Auth::requireRoles(array('super_admin', 'admin'));

$pageTitle = 'Sağlayıcılar';

$errors = array();
$successMessage = '';
$testResult = null;
$productResult = null;
$providers = array();
$selectedProvider = null;
$selectedProviderId = isset($_GET['provider_id']) ? (int) $_GET['provider_id'] : 0;
$csrfTokenValue = Helpers::csrfToken();
$selfUrl = isset($_SERVER['PHP_SELF']) ? (string) $_SERVER['PHP_SELF'] : '/admin/providers.php';

try {
    $pdo = Database::connection();
} catch (Exception $exception) {
    $pdo = null;
    $errors[] = 'Veritabanı bağlantısı kurulamadı. Lütfen ayarlarınızı kontrol edin.';
    error_log('[providers] Database connection failed: ' . $exception->getMessage());
}

if ($pdo instanceof PDO) {
    ensureProviderTables($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
        $csrfToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

        if (!Helpers::verifyCsrf($csrfToken)) {
            $errors[] = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
        } else {
            if ($action === 'save_provider') {
                $providerId = isset($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;
                $name = trim((string) (isset($_POST['name']) ? $_POST['name'] : ''));
                $baseUrl = normalizeProviderBaseUrl((string) (isset($_POST['base_url']) ? $_POST['base_url'] : ''));
                $apiKey = trim((string) (isset($_POST['api_key']) ? $_POST['api_key'] : ''));
                $isActiveValue = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;
                $isActive = $isActiveValue === 1 ? 1 : 0;

                if ($name === '' || $baseUrl === '' || $apiKey === '') {
                    $errors[] = 'Sağlayıcı adı, API adresi ve anahtar alanları zorunludur.';
                } else {
                    try {
                        if ($providerId > 0) {
                            updateProvider($pdo, $providerId, $name, $baseUrl, $apiKey, $isActive);
                            $successMessage = 'Sağlayıcı bilgileri güncellendi.';
                            $selectedProviderId = $providerId;
                        } else {
                            $selectedProviderId = insertProvider($pdo, $name, $baseUrl, $apiKey, $isActive);
                            $successMessage = 'Yeni sağlayıcı kaydedildi.';
                        }
                    } catch (PDOException $storageException) {
                        $errors[] = 'Sağlayıcı kaydedilirken bir hata oluştu. Detaylar için hata kayıtlarını kontrol edin.';
                        error_log('[providers] Provider save failed: ' . $storageException->getMessage());
                    }
                }
            } elseif ($action === 'delete_provider') {
                $providerId = isset($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;
                if ($providerId > 0) {
                    try {
                        deleteProvider($pdo, $providerId);
                        $successMessage = 'Sağlayıcı silindi.';
                        if ($selectedProviderId === $providerId) {
                            $selectedProviderId = 0;
                        }
                    } catch (PDOException $storageException) {
                        $errors[] = 'Sağlayıcı silinemedi. Lütfen hata kayıtlarını kontrol edin.';
                        error_log('[providers] Provider delete failed: ' . $storageException->getMessage());
                    }
                }
            } elseif ($action === 'test_provider' || $action === 'fetch_products') {
                $providerId = isset($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;
                if ($providerId <= 0) {
                    $errors[] = 'Lütfen önce bir sağlayıcı seçin.';
                } else {
                    $selectedProviderId = $providerId;
                    $provider = getProvider($pdo, $providerId);
                    if (!$provider) {
                        $errors[] = 'Seçili sağlayıcı bulunamadı.';
                    } else {
                        if ($action === 'test_provider') {
                            $testResult = performApiTest($provider['base_url'], $provider['api_key']);
                        } else {
                            $productResult = fetchProviderProducts($provider['base_url'], $provider['api_key']);
                        }
                    }
                }
            }
        }
    }

    $providers = listProviders($pdo);

    if ($selectedProviderId === 0 && $providers) {
        $selectedProviderId = (int) $providers[0]['id'];
    }

    if ($selectedProviderId > 0) {
        $selectedProvider = getProvider($pdo, $selectedProviderId);
    }
}

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="h3 mb-0">Sağlayıcılar</h1>
            <a class="btn btn-outline-primary" href="<?= Helpers::sanitize($selfUrl) ?>">Yeni Sağlayıcı</a>
        </div>
    </div>

    <div class="col-12">
        <?php if ($errors) : ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $error) : ?>
                        <li><?= Helpers::sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($successMessage !== '') : ?>
            <div class="alert alert-success" role="alert">
                <?= Helpers::sanitize($successMessage) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Kayıtlı Sağlayıcılar</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!$providers) : ?>
                    <p class="text-muted px-3 py-4 mb-0">Henüz bir sağlayıcı eklenmedi.</p>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <tbody>
                                <?php foreach ($providers as $provider) : ?>
                                    <tr class="<?= $selectedProviderId === (int) $provider['id'] ? 'table-active' : '' ?>">
                                        <td>
                                            <strong><?= Helpers::sanitize($provider['name']) ?></strong>
                                            <div class="small text-muted"><?= Helpers::sanitize($provider['base_url']) ?></div>
                                        </td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= Helpers::sanitize($selfUrl) ?>?provider_id=<?= (int) $provider['id'] ?>">Seç</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Sağlayıcı Bilgileri</h5>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfTokenValue) ?>">
                    <input type="hidden" name="action" value="save_provider">
                    <input type="hidden" name="provider_id" value="<?= $selectedProvider ? (int) $selectedProvider['id'] : 0 ?>">

                    <div class="col-12 col-md-6">
                        <label for="provider-name" class="form-label">Sağlayıcı Adı</label>
                        <input type="text" id="provider-name" name="name" class="form-control" value="<?= Helpers::sanitize(isset($selectedProvider['name']) ? $selectedProvider['name'] : '') ?>" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="provider-active" class="form-label">Durum</label>
                        <select id="provider-active" name="is_active" class="form-select">
                            <option value="1" <?= !empty($selectedProvider['is_active']) ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= isset($selectedProvider['is_active']) && !(int) $selectedProvider['is_active'] ? 'selected' : '' ?>>Pasif</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="provider-base-url" class="form-label">API Adresi</label>
                        <input type="url" id="provider-base-url" name="base_url" class="form-control" placeholder="https://partner.lotuslisans.com.tr" value="<?= Helpers::sanitize(isset($selectedProvider['base_url']) ? $selectedProvider['base_url'] : '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="provider-api-key" class="form-label">API Anahtarı</label>
                        <input type="text" id="provider-api-key" name="api_key" class="form-control" value="<?= Helpers::sanitize(isset($selectedProvider['api_key']) ? $selectedProvider['api_key'] : '') ?>" required>
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
                <?php if ($selectedProvider) : ?>
                    <form method="post" class="mt-3" onsubmit="return confirm('Bu sağlayıcıyı silmek istediğinize emin misiniz?');">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfTokenValue) ?>">
                        <input type="hidden" name="action" value="delete_provider">
                        <input type="hidden" name="provider_id" value="<?= (int) $selectedProvider['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger">Sağlayıcıyı Sil</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selectedProvider) : ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">API İşlemleri</h5>
                </div>
                <div class="card-body d-flex flex-wrap gap-3">
                    <form method="post" class="me-2">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfTokenValue) ?>">
                        <input type="hidden" name="action" value="test_provider">
                        <input type="hidden" name="provider_id" value="<?= (int) $selectedProvider['id'] ?>">
                        <button type="submit" class="btn btn-outline-primary">API Testi Yap</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfTokenValue) ?>">
                        <input type="hidden" name="action" value="fetch_products">
                        <input type="hidden" name="provider_id" value="<?= (int) $selectedProvider['id'] ?>">
                        <button type="submit" class="btn btn-outline-secondary">Ürünleri Getir</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($testResult !== null) : ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">API Test Sonucu</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong>Durum:</strong>
                        <span class="badge <?= $testResult['ok'] ? 'bg-success' : 'bg-danger' ?>">
                            <?= Helpers::sanitize($testResult['ok'] ? 'Başarılı' : 'Başarısız') ?>
                        </span>
                        <span class="ms-2 text-muted">HTTP <?= (int) $testResult['http_code'] ?></span>
                    </p>
                    <?php if ($testResult['message'] !== '') : ?>
                        <p><?= Helpers::sanitize($testResult['message']) ?></p>
                    <?php endif; ?>
                    <?php if ($testResult['json'] !== '') : ?>
                        <pre class="bg-light p-3 rounded small mb-0"><?= htmlspecialchars($testResult['json'], ENT_QUOTES, 'UTF-8') ?></pre>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($productResult !== null) : ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Sağlayıcı Ürünleri</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong>Durum:</strong>
                        <span class="badge <?= $productResult['ok'] ? 'bg-success' : 'bg-danger' ?>">
                            <?= Helpers::sanitize($productResult['ok'] ? 'Başarılı' : 'Başarısız') ?>
                        </span>
                        <span class="ms-2 text-muted">HTTP <?= (int) $productResult['http_code'] ?></span>
                    </p>
                    <?php if ($productResult['message'] !== '') : ?>
                        <p><?= Helpers::sanitize($productResult['message']) ?></p>
                    <?php endif; ?>
                    <?php if ($productResult['items']) : ?>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Ürün</th>
                                        <th>Tutar</th>
                                        <th>Stok</th>
                                        <th>Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productResult['items'] as $product) : ?>
                                        <tr>
                                            <td><?= Helpers::sanitize(isset($product['id']) ? $product['id'] : '-') ?></td>
                                            <td>
                                                <strong><?= Helpers::sanitize(isset($product['title']) ? $product['title'] : '-') ?></strong>
                                                <?php if (!empty($product['content'])) : ?>
                                                    <div class="small text-muted"><?= Helpers::sanitize(providerExcerpt((string) $product['content'])) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= Helpers::sanitize(isset($product['amount']) ? $product['amount'] : '-') ?></td>
                                            <td><?= Helpers::sanitize(isset($product['stock']) ? $product['stock'] : '-') ?></td>
                                            <td><?= !empty($product['available']) ? '<span class="badge bg-success">Satılabilir</span>' : '<span class="badge bg-secondary">Pasif</span>' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if ($productResult['json'] !== '') : ?>
                        <pre class="bg-light p-3 rounded small mb-0"><?= htmlspecialchars($productResult['json'], ENT_QUOTES, 'UTF-8') ?></pre>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../templates/footer.php';

/**
 * Sağlayıcı tablolarını oluşturur.
 */
function ensureProviderTables(PDO $pdo)
{
    $providerSql = "CREATE TABLE IF NOT EXISTS external_providers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        base_url VARCHAR(255) NOT NULL,
        api_key VARCHAR(191) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_tested_at DATETIME NULL,
        last_test_response TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $mappingSql = "CREATE TABLE IF NOT EXISTS external_provider_products (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    try {
        $pdo->exec($providerSql);
    } catch (PDOException $exception) {
        error_log('[providers] external_providers table ensure failed: ' . $exception->getMessage());
    }

    try {
        $pdo->exec($mappingSql);
    } catch (PDOException $exception) {
        error_log('[providers] external_provider_products table ensure failed: ' . $exception->getMessage());
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function listProviders(PDO $pdo)
{
    $stmt = $pdo->query('SELECT * FROM external_providers ORDER BY name ASC');
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
}

/**
 * @return array<string, mixed>|null
 */
function getProvider(PDO $pdo, $id)
{
    $stmt = $pdo->prepare('SELECT * FROM external_providers WHERE id = :id LIMIT 1');
    $stmt->execute(array('id' => $id));
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    return $provider ?: null;
}

function insertProvider(PDO $pdo, $name, $baseUrl, $apiKey, $isActive)
{
    $stmt = $pdo->prepare('INSERT INTO external_providers (name, base_url, api_key, is_active, created_at) VALUES (:name, :base_url, :api_key, :is_active, NOW())');
    $stmt->execute(array(
        'name' => $name,
        'base_url' => $baseUrl,
        'api_key' => $apiKey,
        'is_active' => $isActive,
    ));

    return (int) $pdo->lastInsertId();
}

function updateProvider(PDO $pdo, $id, $name, $baseUrl, $apiKey, $isActive)
{
    $stmt = $pdo->prepare('UPDATE external_providers SET name = :name, base_url = :base_url, api_key = :api_key, is_active = :is_active, updated_at = NOW() WHERE id = :id');
    $stmt->execute(array(
        'id' => $id,
        'name' => $name,
        'base_url' => $baseUrl,
        'api_key' => $apiKey,
        'is_active' => $isActive,
    ));
}

function deleteProvider(PDO $pdo, $id)
{
    $stmt = $pdo->prepare('DELETE FROM external_providers WHERE id = :id');
    $stmt->execute(array('id' => $id));
}

function normalizeProviderBaseUrl($value)
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    return rtrim($value, '/');
}

function providerExcerpt($value, $limit = 60)
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strlen')) {
        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit, 'UTF-8')) . '...';
    }

    if (strlen($value) <= $limit) {
        return $value;
    }

    return rtrim(substr($value, 0, $limit)) . '...';
}

function formatJsonOutput($decoded, $raw)
{
    if (is_array($decoded) || is_object($decoded)) {
        $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($encoded !== false && $encoded !== null) {
            return $encoded;
        }
    }

    return $raw;
}

function performApiTest($baseUrl, $apiKey)
{
    $response = lotusApiRequest($baseUrl, $apiKey, 'GET', '/api/user');
    $message = '';

    if ($response['error']) {
        $message = $response['error'];
    } elseif ($response['ok']) {
        $decoded = $response['decoded'];
        if (isset($decoded['success']) && $decoded['success']) {
            $credit = isset($decoded['data']['credit']) ? $decoded['data']['credit'] : 'bilinmiyor';
            $message = 'API bağlantısı başarılı. Bakiye: ' . $credit;
        } elseif (isset($decoded['message'])) {
            $message = (string) $decoded['message'];
        }
    } else {
        $decoded = $response['decoded'];
        if (isset($decoded['message'])) {
            $message = (string) $decoded['message'];
        } elseif ($response['http_code'] === 0) {
            $message = 'API yanıt vermedi.';
        } else {
            $message = 'API isteği başarısız oldu.';
        }
    }

    return array(
        'ok' => $response['ok'] && !$response['error'],
        'http_code' => $response['http_code'],
        'message' => $message,
        'json' => formatJsonOutput($response['decoded'], $response['body']),
    );
}

function fetchProviderProducts($baseUrl, $apiKey)
{
    $response = lotusApiRequest($baseUrl, $apiKey, 'GET', '/api/products');
    $items = array();
    $message = '';

    if ($response['error']) {
        $message = $response['error'];
    } else {
        $decoded = $response['decoded'];
        if (isset($decoded['success']) && $decoded['success'] && isset($decoded['data']) && is_array($decoded['data'])) {
            $items = array_slice($decoded['data'], 0, 20);
            $message = 'Toplam ürün: ' . count($decoded['data']);
        } elseif (isset($decoded['message'])) {
            $message = (string) $decoded['message'];
        }
    }

    return array(
        'ok' => $response['ok'] && !$response['error'],
        'http_code' => $response['http_code'],
        'message' => $message,
        'items' => $items,
        'json' => formatJsonOutput($response['decoded'], $response['body']),
    );
}

function lotusApiRequest($baseUrl, $apiKey, $method, $path, $payload = null)
{
    if (!function_exists('curl_init')) {
        return array(
            'ok' => false,
            'http_code' => 0,
            'body' => '',
            'decoded' => null,
            'error' => 'Sunucuda cURL eklentisi etkin değil. API isteği gönderilemedi.',
        );
    }

    $method = strtoupper($method);
    $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    $queryGlue = strpos($url, '?') === false ? '?' : '&';
    $url .= $queryGlue . http_build_query(array('apikey' => $apiKey));

    $ch = curl_init($url);
    $headers = array(
        'Accept: application/json',
        'User-Agent: ResellerPanel/1.0 (+https://github.com/reseller)',
    );

    if ($apiKey !== '') {
        $headers[] = 'X-API-Key: ' . $apiKey;
    }

    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ));

    if ($payload !== null) {
        $jsonPayload = json_encode($payload);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $body === null) {
        return array(
            'ok' => false,
            'http_code' => $httpCode,
            'body' => '',
            'decoded' => null,
            'error' => $curlError !== '' ? $curlError : 'API isteği başarısız oldu.',
        );
    }

    $decoded = json_decode($body, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        return array(
            'ok' => false,
            'http_code' => $httpCode,
            'body' => $body,
            'decoded' => null,
            'error' => 'API yanıtı çözümlenemedi: ' . json_last_error_msg(),
        );
    }

    return array(
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'body' => $body,
        'decoded' => $decoded,
        'error' => '',
    );
}
