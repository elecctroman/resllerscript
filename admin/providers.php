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
$ajaxResponse = null;
$isAjaxRequest = false;
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
        $isAjaxRequest = isset($_POST['ajax']) && $_POST['ajax'] === '1';

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

if ($isAjaxRequest) {
    $ajaxResponse = array(
        'success' => empty($errors),
        'errors' => $errors,
        'successMessage' => $successMessage,
        'test' => $testResult,
        'products' => $productResult,
    );

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($ajaxResponse);
    exit;
}

include __DIR__ . '/../templates/header.php';
?>
<style>
    .api-response {
        max-height: 320px;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .provider-card-scroll {
        max-height: 400px;
        overflow: auto;
    }

    .provider-card-scroll table {
        min-width: 620px;
    }

    .api-meta {
        display: grid;
        gap: 0.5rem;
    }

    .api-meta-row {
        display: grid;
        grid-template-columns: 80px 1fr;
        gap: 0.5rem;
        margin: 0;
    }

    .api-meta-row dt {
        font-weight: 600;
        font-size: 0.875rem;
        margin: 0;
    }

    .api-meta-row dd {
        margin: 0;
        font-size: 0.875rem;
        word-break: break-word;
    }

    .api-response.bg-dark {
        background-color: #1f1f1f !important;
    }
</style>
<div class="row g-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="h3 mb-0">Sağlayıcılar</h1>
            <a class="btn btn-outline-primary" href="<?= Helpers::sanitize($selfUrl) ?>">Yeni Sağlayıcı</a>
        </div>
    </div>

    <div class="col-12">
        <div id="provider-error-alert" class="alert alert-danger<?= $errors ? '' : ' d-none' ?>" role="alert">
            <ul class="mb-0" id="provider-error-list">
                <?php foreach ($errors as $error) : ?>
                    <li><?= Helpers::sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div id="provider-success-alert" class="alert alert-success<?= $successMessage !== '' ? '' : ' d-none' ?>" role="alert">
            <span id="provider-success-message"><?= Helpers::sanitize($successMessage) ?></span>
        </div>
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
                    <form method="post" class="me-2 provider-action-form" data-provider-action="test">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfTokenValue) ?>">
                        <input type="hidden" name="action" value="test_provider">
                        <input type="hidden" name="provider_id" value="<?= (int) $selectedProvider['id'] ?>">
                        <button type="submit" class="btn btn-outline-primary">API Testi Yap</button>
                    </form>
                    <form method="post" class="provider-action-form" data-provider-action="products">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfTokenValue) ?>">
                        <input type="hidden" name="action" value="fetch_products">
                        <input type="hidden" name="provider_id" value="<?= (int) $selectedProvider['id'] ?>">
                        <button type="submit" class="btn btn-outline-secondary">Ürünleri Getir</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $hasTestResult = $testResult !== null;
        $testRequestHeaders = $hasTestResult && isset($testResult['request']['headers']) && is_array($testResult['request']['headers'])
            ? implode("\n", $testResult['request']['headers'])
            : '';
        $testRequestBody = $hasTestResult && isset($testResult['request']['body'])
            ? formatRequestBodyForDisplay($testResult['request']['body'])
            : '';
        $testDecodeError = $hasTestResult && !empty($testResult['decode_error'])
            ? 'JSON ayrıştırma hatası: ' . $testResult['decode_error']
            : '';
        ?>
        <div class="card shadow-sm border-0 mb-4<?= $hasTestResult ? '' : ' d-none' ?>" data-result-card="test">
            <div class="card-header bg-white">
                <h5 class="mb-0">API Test Sonucu</h5>
            </div>
            <div class="card-body" data-test-container>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <strong>Durum:</strong>
                        <span class="badge <?= $hasTestResult && $testResult['ok'] ? 'bg-success' : 'bg-danger' ?>" data-test-badge><?= Helpers::sanitize($hasTestResult && $testResult['ok'] ? 'Başarılı' : 'Başarısız') ?></span>
                        <span class="text-muted" data-test-http>HTTP <?= $hasTestResult ? (int) $testResult['http_code'] : 0 ?></span>
                    </div>
                    <span class="ms-auto small text-muted" data-test-duration><?= $hasTestResult && isset($testResult['duration']) ? (int) $testResult['duration'] . ' ms' : '' ?></span>
                </div>
                <p class="<?= $hasTestResult && $testResult['message'] !== '' ? '' : 'd-none' ?>" data-test-message><?= $hasTestResult && $testResult['message'] !== '' ? Helpers::sanitize($testResult['message']) : '' ?></p>
                <div class="row g-3">
                    <div class="col-12 col-lg-5">
                        <h6 class="fw-semibold">İstek Bilgileri</h6>
                        <dl class="api-meta mb-3">
                            <div class="api-meta-row">
                                <dt>Yöntem</dt>
                                <dd data-test-method><?= $hasTestResult && isset($testResult['request']['method']) ? Helpers::sanitize($testResult['request']['method']) : 'GET' ?></dd>
                            </div>
                            <div class="api-meta-row">
                                <dt>URL</dt>
                                <dd class="text-break" data-test-url><?= $hasTestResult && isset($testResult['request']['url']) ? Helpers::sanitize($testResult['request']['url']) : '' ?></dd>
                            </div>
                        </dl>
                        <pre class="api-response bg-light p-3 rounded small mb-0<?= $testRequestHeaders !== '' ? '' : ' d-none' ?>" data-test-request-headers><?= $testRequestHeaders !== '' ? htmlspecialchars($testRequestHeaders, ENT_QUOTES, 'UTF-8') : '' ?></pre>
                        <pre class="api-response bg-light p-3 rounded small mt-3 mb-0<?= $testRequestBody !== '' ? '' : ' d-none' ?>" data-test-request-body><?= $testRequestBody !== '' ? htmlspecialchars($testRequestBody, ENT_QUOTES, 'UTF-8') : '' ?></pre>
                    </div>
                    <div class="col-12 col-lg-7">
                        <h6 class="fw-semibold">Yanıt İçeriği</h6>
                        <p class="small text-danger mb-2<?= $testDecodeError !== '' ? '' : ' d-none' ?>" data-test-decode-error><?= $testDecodeError !== '' ? Helpers::sanitize($testDecodeError) : '' ?></p>
                        <pre class="api-response bg-light p-3 rounded small mb-3<?= $hasTestResult && $testResult['json'] !== '' ? '' : ' d-none' ?>" data-test-json><?= $hasTestResult && $testResult['json'] !== '' ? htmlspecialchars($testResult['json'], ENT_QUOTES, 'UTF-8') : '' ?></pre>
                        <pre class="api-response bg-dark text-white p-3 rounded small mb-0<?= $hasTestResult && isset($testResult['raw']) && $testResult['raw'] !== '' ? '' : ' d-none' ?>" data-test-raw><?= $hasTestResult && isset($testResult['raw']) && $testResult['raw'] !== '' ? htmlspecialchars($testResult['raw'], ENT_QUOTES, 'UTF-8') : '' ?></pre>
                    </div>
                </div>
            </div>
        </div>

        <?php
        $hasProductResult = $productResult !== null;
        $productRequestHeaders = $hasProductResult && isset($productResult['request']['headers']) && is_array($productResult['request']['headers'])
            ? implode("\n", $productResult['request']['headers'])
            : '';
        $productRequestBody = $hasProductResult && isset($productResult['request']['body'])
            ? formatRequestBodyForDisplay($productResult['request']['body'])
            : '';
        $productDecodeError = $hasProductResult && !empty($productResult['decode_error'])
            ? 'JSON ayrıştırma hatası: ' . $productResult['decode_error']
            : '';
        ?>
        <div class="card shadow-sm border-0 mb-4<?= $hasProductResult ? '' : ' d-none' ?>" data-result-card="products">
            <div class="card-header bg-white">
                <h5 class="mb-0">Sağlayıcı Ürünleri</h5>
            </div>
            <div class="card-body" data-product-container>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <strong>Durum:</strong>
                        <span class="badge <?= $hasProductResult && $productResult['ok'] ? 'bg-success' : 'bg-danger' ?>" data-product-badge><?= Helpers::sanitize($hasProductResult && $productResult['ok'] ? 'Başarılı' : 'Başarısız') ?></span>
                        <span class="text-muted" data-product-http>HTTP <?= $hasProductResult ? (int) $productResult['http_code'] : 0 ?></span>
                    </div>
                    <span class="ms-auto small text-muted" data-product-duration><?= $hasProductResult && isset($productResult['duration']) ? (int) $productResult['duration'] . ' ms' : '' ?></span>
                </div>
                <p class="<?= $hasProductResult && $productResult['message'] !== '' ? '' : 'd-none' ?>" data-product-message><?= $hasProductResult && $productResult['message'] !== '' ? Helpers::sanitize($productResult['message']) : '' ?></p>
                <div class="row g-3">
                    <div class="col-12 col-lg-5">
                        <h6 class="fw-semibold">İstek Bilgileri</h6>
                        <dl class="api-meta mb-3">
                            <div class="api-meta-row">
                                <dt>Yöntem</dt>
                                <dd data-product-method><?= $hasProductResult && isset($productResult['request']['method']) ? Helpers::sanitize($productResult['request']['method']) : 'GET' ?></dd>
                            </div>
                            <div class="api-meta-row">
                                <dt>URL</dt>
                                <dd class="text-break" data-product-url><?= $hasProductResult && isset($productResult['request']['url']) ? Helpers::sanitize($productResult['request']['url']) : '' ?></dd>
                            </div>
                        </dl>
                        <pre class="api-response bg-light p-3 rounded small mb-0<?= $productRequestHeaders !== '' ? '' : ' d-none' ?>" data-product-request-headers><?= $productRequestHeaders !== '' ? htmlspecialchars($productRequestHeaders, ENT_QUOTES, 'UTF-8') : '' ?></pre>
                        <pre class="api-response bg-light p-3 rounded small mt-3 mb-0<?= $productRequestBody !== '' ? '' : ' d-none' ?>" data-product-request-body><?= $productRequestBody !== '' ? htmlspecialchars($productRequestBody, ENT_QUOTES, 'UTF-8') : '' ?></pre>
                    </div>
                    <div class="col-12 col-lg-7">
                        <h6 class="fw-semibold">Yanıt İçeriği</h6>
                        <p class="small text-danger mb-2<?= $productDecodeError !== '' ? '' : ' d-none' ?>" data-product-decode-error><?= $productDecodeError !== '' ? Helpers::sanitize($productDecodeError) : '' ?></p>
                        <div class="provider-card-scroll table-responsive mb-3<?= $hasProductResult && $productResult['items'] ? '' : ' d-none' ?>" data-product-table-wrapper>
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
                        <tbody data-product-rows>
                            <?php if ($hasProductResult && $productResult['items']) : ?>
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
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                        <pre class="api-response bg-light p-3 rounded small mb-3<?= $hasProductResult && $productResult['json'] !== '' ? '' : ' d-none' ?>" data-product-json><?= $hasProductResult && $productResult['json'] !== '' ? htmlspecialchars($productResult['json'], ENT_QUOTES, 'UTF-8') : '' ?></pre>
                        <pre class="api-response bg-dark text-white p-3 rounded small mb-0<?= $hasProductResult && isset($productResult['raw']) && $productResult['raw'] !== '' ? '' : ' d-none' ?>" data-product-raw><?= $hasProductResult && isset($productResult['raw']) && $productResult['raw'] !== '' ? htmlspecialchars($productResult['raw'], ENT_QUOTES, 'UTF-8') : '' ?></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../templates/footer.php';
?>
<script>
    (function () {
        var forms = document.querySelectorAll('.provider-action-form');
        if (!forms.length) {
            return;
        }

        var errorAlert = document.getElementById('provider-error-alert');
        var errorList = document.getElementById('provider-error-list');
        var successAlert = document.getElementById('provider-success-alert');
        var successMessage = document.getElementById('provider-success-message');
        var testCard = document.querySelector('[data-result-card="test"]');
        var productCard = document.querySelector('[data-result-card="products"]');

        function resetAlerts() {
            if (errorAlert) {
                errorAlert.classList.add('d-none');
                if (errorList) {
                    errorList.innerHTML = '';
                }
            }
            if (successAlert) {
                successAlert.classList.add('d-none');
                if (successMessage) {
                    successMessage.textContent = '';
                }
            }
        }

        function setErrors(errors) {
            if (!errorAlert || !errorList) {
                return;
            }
            if (!errors || !errors.length) {
                errorAlert.classList.add('d-none');
                errorList.innerHTML = '';
                return;
            }
            var items = '';
            for (var i = 0; i < errors.length; i += 1) {
                var value = errors[i];
                items += '<li>' + escapeHtml(String(value || 'Beklenmeyen hata.')) + '</li>';
            }
            errorList.innerHTML = items;
            errorAlert.classList.remove('d-none');
        }

        function setSuccess(message) {
            if (!successAlert || !successMessage) {
                return;
            }
            if (!message) {
                successAlert.classList.add('d-none');
                successMessage.textContent = '';
                return;
            }
            successMessage.textContent = message;
            successAlert.classList.remove('d-none');
        }

        function toggleCard(card, show) {
            if (!card) {
                return;
            }
            if (show) {
                card.classList.remove('d-none');
            } else {
                card.classList.add('d-none');
            }
        }

        function escapeHtml(value) {
            return value
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function setPreElement(element, value) {
            if (!element) {
                return;
            }
            if (value) {
                element.textContent = value;
                element.classList.remove('d-none');
            } else {
                element.textContent = '';
                element.classList.add('d-none');
            }
        }

        function formatHeaders(headers) {
            if (!headers || !headers.length) {
                return '';
            }
            return headers.join('\n');
        }

        function formatRequestBody(body) {
            if (!body) {
                return '';
            }
            var text = String(body);
            try {
                var parsed = JSON.parse(text);
                return JSON.stringify(parsed, null, 2);
            } catch (error) {
                return text;
            }
        }

        function updateTestCard(data) {
            if (!testCard) {
                return;
            }
            var badge = testCard.querySelector('[data-test-badge]');
            var http = testCard.querySelector('[data-test-http]');
            var message = testCard.querySelector('[data-test-message]');
            var jsonPre = testCard.querySelector('[data-test-json]');
            var rawPre = testCard.querySelector('[data-test-raw]');
            var duration = testCard.querySelector('[data-test-duration]');
            var decodeError = testCard.querySelector('[data-test-decode-error]');
            var methodElement = testCard.querySelector('[data-test-method]');
            var urlElement = testCard.querySelector('[data-test-url]');
            var requestHeaders = testCard.querySelector('[data-test-request-headers]');
            var requestBody = testCard.querySelector('[data-test-request-body]');
            if (!data) {
                toggleCard(testCard, false);
                return;
            }

            toggleCard(testCard, true);
            if (badge) {
                badge.textContent = data.ok ? 'Başarılı' : 'Başarısız';
                badge.classList.remove('bg-success', 'bg-danger');
                badge.classList.add(data.ok ? 'bg-success' : 'bg-danger');
            }
            if (http) {
                http.textContent = 'HTTP ' + (data.http_code || 0);
            }
            if (duration) {
                if (data.duration) {
                    duration.textContent = String(data.duration) + ' ms';
                    duration.classList.remove('d-none');
                } else {
                    duration.textContent = '';
                    duration.classList.add('d-none');
                }
            }
            if (message) {
                if (data.message) {
                    message.innerHTML = escapeHtml(data.message);
                    message.classList.remove('d-none');
                } else {
                    message.textContent = '';
                    message.classList.add('d-none');
                }
            }
            if (decodeError) {
                if (data.decode_error) {
                    decodeError.textContent = 'JSON ayrıştırma hatası: ' + data.decode_error;
                    decodeError.classList.remove('d-none');
                } else {
                    decodeError.textContent = '';
                    decodeError.classList.add('d-none');
                }
            }
            var request = data.request || {};
            if (methodElement) {
                methodElement.textContent = request.method || '-';
            }
            if (urlElement) {
                urlElement.textContent = request.url || '-';
            }
            setPreElement(requestHeaders, formatHeaders(request.headers || []));
            setPreElement(requestBody, formatRequestBody(request.body || ''));
            if (jsonPre) {
                setPreElement(jsonPre, data.json || '');
            }
            if (rawPre) {
                setPreElement(rawPre, data.raw || '');
            }
        }

        function updateProductCard(data) {
            if (!productCard) {
                return;
            }
            var badge = productCard.querySelector('[data-product-badge]');
            var http = productCard.querySelector('[data-product-http]');
            var message = productCard.querySelector('[data-product-message]');
            var jsonPre = productCard.querySelector('[data-product-json]');
            var tableWrapper = productCard.querySelector('[data-product-table-wrapper]');
            var rowsContainer = productCard.querySelector('[data-product-rows]');
            var rawPre = productCard.querySelector('[data-product-raw]');
            var duration = productCard.querySelector('[data-product-duration]');
            var decodeError = productCard.querySelector('[data-product-decode-error]');
            var methodElement = productCard.querySelector('[data-product-method]');
            var urlElement = productCard.querySelector('[data-product-url]');
            var requestHeaders = productCard.querySelector('[data-product-request-headers]');
            var requestBody = productCard.querySelector('[data-product-request-body]');
            if (!data) {
                toggleCard(productCard, false);
                return;
            }

            toggleCard(productCard, true);
            if (badge) {
                badge.textContent = data.ok ? 'Başarılı' : 'Başarısız';
                badge.classList.remove('bg-success', 'bg-danger');
                badge.classList.add(data.ok ? 'bg-success' : 'bg-danger');
            }
            if (http) {
                http.textContent = 'HTTP ' + (data.http_code || 0);
            }
            if (duration) {
                if (data.duration) {
                    duration.textContent = String(data.duration) + ' ms';
                    duration.classList.remove('d-none');
                } else {
                    duration.textContent = '';
                    duration.classList.add('d-none');
                }
            }
            if (message) {
                if (data.message) {
                    message.innerHTML = escapeHtml(data.message);
                    message.classList.remove('d-none');
                } else {
                    message.textContent = '';
                    message.classList.add('d-none');
                }
            }
            if (decodeError) {
                if (data.decode_error) {
                    decodeError.textContent = 'JSON ayrıştırma hatası: ' + data.decode_error;
                    decodeError.classList.remove('d-none');
                } else {
                    decodeError.textContent = '';
                    decodeError.classList.add('d-none');
                }
            }
            var request = data.request || {};
            if (methodElement) {
                methodElement.textContent = request.method || '-';
            }
            if (urlElement) {
                urlElement.textContent = request.url || '-';
            }
            setPreElement(requestHeaders, formatHeaders(request.headers || []));
            setPreElement(requestBody, formatRequestBody(request.body || ''));
            if (jsonPre) {
                setPreElement(jsonPre, data.json || '');
            }
            if (rawPre) {
                setPreElement(rawPre, data.raw || '');
            }
            if (rowsContainer) {
                rowsContainer.innerHTML = '';
                if (data.items && data.items.length) {
                    var html = '';
                    for (var i = 0; i < data.items.length; i += 1) {
                        var item = data.items[i] || {};
                        var id = typeof item.id !== 'undefined' ? String(item.id) : '-';
                        var title = typeof item.title !== 'undefined' ? String(item.title) : '-';
                        var content = typeof item.content !== 'undefined' ? String(item.content) : '';
                        var amount = typeof item.amount !== 'undefined' ? String(item.amount) : '-';
                        var stock = typeof item.stock !== 'undefined' ? String(item.stock) : '-';
                        var available = !!item.available;
                        html += '<tr>' +
                            '<td>' + escapeHtml(id) + '</td>' +
                            '<td><strong>' + escapeHtml(title) + '</strong>';
                        if (content) {
                            html += '<div class="small text-muted">' + escapeHtml(providerExcerpt(content)) + '</div>';
                        }
                        html += '</td>' +
                            '<td>' + escapeHtml(amount) + '</td>' +
                            '<td>' + escapeHtml(stock) + '</td>' +
                            '<td>' + (available ? '<span class="badge bg-success">Satılabilir</span>' : '<span class="badge bg-secondary">Pasif</span>') + '</td>' +
                            '</tr>';
                    }
                    rowsContainer.innerHTML = html;
                    if (tableWrapper) {
                        tableWrapper.classList.remove('d-none');
                    }
                } else if (tableWrapper) {
                    tableWrapper.classList.add('d-none');
                }
            }
        }

        function providerExcerpt(value) {
            if (!value) {
                return '';
            }
            var text = String(value).trim();
            if (text.length <= 60) {
                return text;
            }
            return text.substring(0, 60).replace(/\s+$/g, '') + '...';
        }

        function handleSubmit(event) {
            event.preventDefault();
            var form = event.currentTarget;
            var submitButton = form.querySelector('button[type="submit"]');
            var buttonText = submitButton ? submitButton.textContent.trim() : '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.dataset.originalHtml = submitButton.dataset.originalHtml || submitButton.innerHTML;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + escapeHtml(buttonText || 'İşleniyor...');
            }

            resetAlerts();

            var formData = new FormData(form);
            formData.append('ajax', '1');

            fetch(form.getAttribute('action') || window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('İstek başarısız oldu.');
                }
                return response.json();
            }).then(function (payload) {
                if (!payload) {
                    throw new Error('Geçersiz sunucu yanıtı.');
                }

                if (payload.errors && payload.errors.length) {
                    setErrors(payload.errors);
                } else {
                    setErrors([]);
                }

                if (payload.successMessage) {
                    setSuccess(payload.successMessage);
                } else {
                    setSuccess('');
                }

                if (payload.test) {
                    updateTestCard(payload.test);
                } else if (form.getAttribute('data-provider-action') === 'test') {
                    updateTestCard(null);
                }

                if (payload.products) {
                    updateProductCard(payload.products);
                } else if (form.getAttribute('data-provider-action') === 'products') {
                    updateProductCard(null);
                }
            }).catch(function (error) {
                setErrors([error && error.message ? error.message : 'Beklenmeyen bir hata oluştu.']);
            }).finally(function () {
                if (submitButton) {
                    submitButton.disabled = false;
                    if (submitButton.dataset.originalHtml) {
                        submitButton.innerHTML = submitButton.dataset.originalHtml;
                    }
                }
            });
        }

        for (var i = 0; i < forms.length; i += 1) {
            forms[i].addEventListener('submit', handleSubmit);
        }
    })();
</script>
<?php

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

function formatRequestBodyForDisplay($body)
{
    if ($body === null) {
        return '';
    }

    $body = (string) $body;
    if ($body === '') {
        return '';
    }

    $decoded = json_decode($body, true);
    if ($decoded !== null && json_last_error() === JSON_ERROR_NONE) {
        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($pretty !== false && $pretty !== null) {
            return $pretty;
        }
    }

    return $body;
}

function performApiTest($baseUrl, $apiKey)
{
    $response = lotusApiRequest($baseUrl, $apiKey, 'GET', '/api/user');
    $messageParts = array();

    if ($response['error']) {
        $messageParts[] = $response['error'];
    }

    if ($response['decoded'] && isset($response['decoded']['message'])) {
        $messageParts[] = (string) $response['decoded']['message'];
    }

    if ($response['decode_error']) {
        $messageParts[] = 'JSON ayrıştırma hatası: ' . $response['decode_error'];
    }

    if (!$messageParts) {
        if ($response['ok']) {
            $decoded = $response['decoded'];
            if (isset($decoded['success']) && $decoded['success'] && isset($decoded['data']['credit'])) {
                $messageParts[] = 'API bağlantısı başarılı. Bakiye: ' . $decoded['data']['credit'];
            } else {
                $messageParts[] = 'API isteği tamamlandı.';
            }
        } elseif ($response['http_code'] === 0) {
            $messageParts[] = 'API yanıt vermedi.';
        } else {
            $messageParts[] = 'API isteği başarısız oldu.';
        }
    }

    return array(
        'ok' => $response['ok'] && !$response['error'],
        'http_code' => $response['http_code'],
        'duration' => $response['duration'],
        'message' => trim(implode(' ', $messageParts)),
        'json' => $response['decode_error'] === '' ? formatJsonOutput($response['decoded'], $response['body']) : '',
        'raw' => $response['body'],
        'decode_error' => $response['decode_error'],
        'request' => $response['request'],
    );
}

function fetchProviderProducts($baseUrl, $apiKey)
{
    $response = lotusApiRequest($baseUrl, $apiKey, 'GET', '/api/products');
    $items = array();
    $messageParts = array();

    if ($response['error']) {
        $messageParts[] = $response['error'];
    }

    if ($response['decoded'] && isset($response['decoded']['message'])) {
        $messageParts[] = (string) $response['decoded']['message'];
    }

    if ($response['decode_error']) {
        $messageParts[] = 'JSON ayrıştırma hatası: ' . $response['decode_error'];
    }

    $decoded = $response['decoded'];
    if (is_array($decoded) && isset($decoded['success']) && $decoded['success'] && isset($decoded['data']) && is_array($decoded['data'])) {
        $items = array_slice($decoded['data'], 0, 20);
        $messageParts[] = 'Toplam ürün: ' . count($decoded['data']);
    }

    if (!$messageParts) {
        if ($response['ok']) {
            $messageParts[] = 'API isteği tamamlandı.';
        } elseif ($response['http_code'] === 0) {
            $messageParts[] = 'API yanıt vermedi.';
        } else {
            $messageParts[] = 'API isteği başarısız oldu.';
        }
    }

    return array(
        'ok' => $response['ok'] && !$response['error'],
        'http_code' => $response['http_code'],
        'duration' => $response['duration'],
        'message' => trim(implode(' ', $messageParts)),
        'items' => $items,
        'json' => $response['decode_error'] === '' ? formatJsonOutput($response['decoded'], $response['body']) : '',
        'raw' => $response['body'],
        'decode_error' => $response['decode_error'],
        'request' => $response['request'],
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
            'decode_error' => '',
            'error' => 'Sunucuda cURL eklentisi etkin değil. API isteği gönderilemedi.',
            'duration' => 0,
            'request' => array(
                'url' => '',
                'method' => strtoupper($method),
                'headers' => array(),
                'body' => '',
            ),
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

    $requestBody = '';

    $curlOptions = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    );

    if ($payload !== null) {
        $requestBody = json_encode($payload);
        $headers[] = 'Content-Type: application/json';
        $curlOptions[CURLOPT_HTTPHEADER] = $headers;
        $curlOptions[CURLOPT_POSTFIELDS] = $requestBody;
    }

    curl_setopt_array($ch, $curlOptions);

    $startTime = microtime(true);
    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $durationMs = (int) round((microtime(true) - $startTime) * 1000);
    curl_close($ch);

    if ($body === false || $body === null) {
        return array(
            'ok' => false,
            'http_code' => $httpCode,
            'body' => '',
            'decoded' => null,
            'decode_error' => '',
            'error' => $curlError !== '' ? $curlError : 'API isteği başarısız oldu.',
            'duration' => $durationMs,
            'request' => array(
                'url' => $url,
                'method' => $method,
                'headers' => $headers,
                'body' => $requestBody,
            ),
        );
    }

    $decoded = json_decode($body, true);
    $decodeError = '';
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        $decodeError = json_last_error_msg();
    }

    return array(
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'body' => $body,
        'decoded' => $decoded === null ? null : $decoded,
        'decode_error' => $decodeError,
        'error' => $curlError !== '' ? $curlError : '',
        'duration' => $durationMs,
        'request' => array(
            'url' => $url,
            'method' => $method,
            'headers' => $headers,
            'body' => $requestBody,
        ),
    );
}
