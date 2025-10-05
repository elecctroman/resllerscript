<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Services\CurrencyService;

Auth::requireRoles(array('super_admin', 'admin'));

$csrfToken = Helpers::csrfToken();
$errors = array();
$successFlash = Helpers::getFlash('currencies.success', '');
$successFlash = is_string($successFlash) ? $successFlash : '';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        echo json_encode(array('success' => false, 'message' => 'Geçersiz istek.'));
        exit;
    }

    $action = isset($payload['action']) ? (string) $payload['action'] : '';
    $token = isset($payload['csrf_token']) ? (string) $payload['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        echo json_encode(array('success' => false, 'message' => 'Oturum doğrulaması başarısız.'));
        exit;
    }

    if ($action === 'update_currency') {
        $code = isset($payload['code']) ? strtoupper((string) $payload['code']) : '';
        if ($code === '') {
            echo json_encode(array('success' => false, 'message' => 'Geçersiz para birimi.'));
            exit;
        }

        $attributes = array();
        if (isset($payload['rate'])) {
            $attributes['rate'] = (float) $payload['rate'];
        }
        if (isset($payload['symbol'])) {
            $attributes['symbol'] = (string) $payload['symbol'];
        }
        if (isset($payload['decimals'])) {
            $attributes['decimals'] = (int) $payload['decimals'];
        }
        if (isset($payload['auto_update'])) {
            $attributes['auto_update'] = (int) $payload['auto_update'] === 1;
        }

        $updated = CurrencyService::update($code, $attributes);
        echo json_encode(array('success' => $updated));
        exit;
    }

    if ($action === 'refresh_rate') {
        $code = isset($payload['code']) ? strtoupper((string) $payload['code']) : '';
        if ($code === '') {
            echo json_encode(array('success' => false, 'message' => 'Geçersiz para birimi.'));
            exit;
        }

        $rate = CurrencyService::refreshRate($code);
        if ($rate === null) {
            echo json_encode(array('success' => false, 'message' => 'Kur bilgisi alınamadı.'));
            exit;
        }

        echo json_encode(array('success' => true, 'rate' => $rate));
        exit;
    }

    echo json_encode(array('success' => false, 'message' => 'Bilinmeyen işlem.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum doğrulaması başarısız oldu. Lütfen sayfayı yenileyin.';
    } else {
        if ($action === 'create_currency') {
            $code = isset($_POST['code']) ? strtoupper(trim((string) $_POST['code'])) : '';
            $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
            $symbol = isset($_POST['symbol']) ? trim((string) $_POST['symbol']) : '';
            $rate = isset($_POST['rate']) ? (float) $_POST['rate'] : 1.0;
            $decimals = isset($_POST['decimals']) ? (int) $_POST['decimals'] : 2;
            $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1';
            $autoUpdate = isset($_POST['auto_update']) && $_POST['auto_update'] === '1';

            if ($code === '' || !preg_match('/^[A-Z]{3}$/', $code)) {
                $errors[] = 'Para birimi kodu üç harften oluşmalıdır.';
            } else {
                $created = CurrencyService::create($code, $name, $symbol, $rate, $decimals, $isActive, false, $autoUpdate);
                if ($created) {
                    Helpers::setFlash('currencies.success', 'Yeni para birimi başarıyla eklendi.');
                    Helpers::redirect('/admin/currencies.php');
                } else {
                    $errors[] = 'Para birimi eklenemedi. Kod benzersiz olmalıdır.';
                }
            }
        } elseif ($action === 'set_default_currency') {
            $code = isset($_POST['code']) ? strtoupper(trim((string) $_POST['code'])) : '';
            if ($code === '') {
                $errors[] = 'Geçersiz para birimi kodu.';
            } else {
                if (CurrencyService::setDefault($code)) {
                    Helpers::setFlash('currencies.success', 'Varsayılan para birimi güncellendi.');
                    Helpers::redirect('/admin/currencies.php');
                } else {
                    $errors[] = 'Varsayılan para birimi güncellenemedi.';
                }
            }
        } elseif ($action === 'toggle_currency') {
            $code = isset($_POST['code']) ? strtoupper(trim((string) $_POST['code'])) : '';
            $state = isset($_POST['state']) && $_POST['state'] === 'active';
            if ($code === '') {
                $errors[] = 'Geçersiz para birimi kodu.';
            } else {
                if (CurrencyService::setActive($code, $state)) {
                    Helpers::setFlash('currencies.success', 'Para birimi durumu güncellendi.');
                    Helpers::redirect('/admin/currencies.php');
                } else {
                    $errors[] = 'Para birimi güncellenemedi.';
                }
            }
        }
    }
}

$currencies = CurrencyService::currencies(true);
$defaultCurrency = CurrencyService::defaultCurrency();

$pageTitle = 'Para Birimi Yönetimi';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Para Birimleri</h5>
                    <small class="text-muted">Varsayılan para birimi: <?= Helpers::sanitize(strtoupper($defaultCurrency)) ?></small>
                </div>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($successFlash !== ''): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($successFlash) ?></div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="currencyTable" data-csrf="<?= Helpers::sanitize($csrfToken) ?>">
                        <thead>
                        <tr>
                            <th>Kod</th>
                            <th>Adı</th>
                            <th>Sembol</th>
                            <th>Kur (<?= Helpers::sanitize(strtoupper($defaultCurrency)) ?>)</th>
                            <th>Durum</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($currencies as $currency): ?>
                            <?php
                            $code = isset($currency['code']) ? strtoupper((string) $currency['code']) : '';
                            $isDefault = isset($currency['is_default']) && (int) $currency['is_default'] === 1;
                            $isActive = isset($currency['is_active']) && (int) $currency['is_active'] === 1;
                            $autoUpdate = isset($currency['auto_update']) && (int) $currency['auto_update'] === 1;
                            $rate = isset($currency['rate']) ? (float) $currency['rate'] : 1.0;
                            $symbol = isset($currency['symbol']) ? $currency['symbol'] : '';
                            $decimals = isset($currency['decimals']) ? (int) $currency['decimals'] : 2;
                            ?>
                            <tr data-currency-row data-code="<?= Helpers::sanitize($code) ?>">
                                <td class="fw-semibold text-uppercase"><?= Helpers::sanitize($code) ?></td>
                                <td><?= Helpers::sanitize(isset($currency['name']) ? $currency['name'] : $code) ?></td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" value="<?= Helpers::sanitize($symbol) ?>" maxlength="5" data-currency-symbol>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.000001" class="form-control" value="<?= Helpers::sanitize(number_format($rate, 6, '.', '')) ?>" data-currency-rate>
                                        <button type="button" class="btn btn-outline-secondary" data-refresh-rate title="Kuru güncelle">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </div>
                                    <div class="mt-2 d-flex align-items-center gap-2">
                                        <label class="form-label mb-0 small text-muted">Ondalık:</label>
                                        <input type="number" class="form-control form-control-sm" value="<?= (int) $decimals ?>" min="0" max="6" style="width:80px" data-currency-decimals>
                                        <div class="form-check form-switch ms-auto">
                                            <input class="form-check-input" type="checkbox" data-currency-auto <?= $autoUpdate ? 'checked' : '' ?>>
                                            <label class="form-check-label small">Otomatik</label>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($isDefault): ?>
                                        <span class="badge bg-primary">Varsayılan</span>
                                    <?php elseif ($isActive): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <form method="post" class="me-2">
                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                                            <input type="hidden" name="action" value="toggle_currency">
                                            <input type="hidden" name="code" value="<?= Helpers::sanitize($code) ?>">
                                            <input type="hidden" name="state" value="<?= $isActive ? 'inactive' : 'active' ?>">
                                            <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                                <?= $isActive ? 'Pasifleştir' : 'Aktifleştir' ?>
                                            </button>
                                        </form>
                                        <?php if (!$isDefault): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                                                <input type="hidden" name="action" value="set_default_currency">
                                                <input type="hidden" name="code" value="<?= Helpers::sanitize($code) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Varsayılan Yap</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Para Birimi</h5>
            </div>
            <div class="card-body">
                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_currency">
                    <div>
                        <label class="form-label">Para Birimi Kodu</label>
                        <input type="text" name="code" class="form-control" maxlength="3" placeholder="USD" required>
                    </div>
                    <div>
                        <label class="form-label">Adı</label>
                        <input type="text" name="name" class="form-control" placeholder="US Dollar">
                    </div>
                    <div>
                        <label class="form-label">Sembol</label>
                        <input type="text" name="symbol" class="form-control" maxlength="5" placeholder="$">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Başlangıç Kuru</label>
                            <input type="number" name="rate" class="form-control" step="0.000001" value="1.000000">
                            <small class="text-muted">Varsayılan para birimine göre oran.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ondalık</label>
                            <input type="number" name="decimals" class="form-control" min="0" max="6" value="2">
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="currencyActiveSwitch" value="1" checked>
                        <label class="form-check-label" for="currencyActiveSwitch">Para birimi aktif olsun</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="auto_update" id="currencyAutoSwitch" value="1">
                        <label class="form-check-label" for="currencyAutoSwitch">Kur otomatik güncellensin</label>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Para Birimi Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var table = document.getElementById('currencyTable');
        if (!table) { return; }

        var csrf = table.getAttribute('data-csrf');

        function updateCurrency(code, data) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({
                    action: 'update_currency',
                    code: code,
                    csrf_token: csrf
                }, data))
            });
        }

        table.addEventListener('change', function (event) {
            var row = event.target.closest('[data-currency-row]');
            if (!row) { return; }
            var code = row.getAttribute('data-code');
            if (!code) { return; }

            if (event.target.matches('[data-currency-symbol]')) {
                updateCurrency(code, { symbol: event.target.value });
            }

            if (event.target.matches('[data-currency-rate]')) {
                updateCurrency(code, { rate: event.target.value });
            }

            if (event.target.matches('[data-currency-decimals]')) {
                updateCurrency(code, { decimals: parseInt(event.target.value, 10) || 0 });
            }

            if (event.target.matches('[data-currency-auto]')) {
                updateCurrency(code, { auto_update: event.target.checked ? 1 : 0 });
            }
        });

        table.addEventListener('click', function (event) {
            var button = event.target.closest('[data-refresh-rate]');
            if (!button) { return; }

            var row = button.closest('[data-currency-row]');
            if (!row) { return; }
            var code = row.getAttribute('data-code');
            if (!code) { return; }

            button.disabled = true;
            button.classList.add('disabled');

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'refresh_rate', code: code, csrf_token: csrf })
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (data && data.success && typeof data.rate !== 'undefined') {
                    var rateInput = row.querySelector('[data-currency-rate]');
                    if (rateInput) {
                        rateInput.value = Number(data.rate).toFixed(6);
                    }
                }
            }).finally(function () {
                button.disabled = false;
                button.classList.remove('disabled');
            });
        });
    })();
</script>

<?php include __DIR__ . '/../templates/footer.php';
