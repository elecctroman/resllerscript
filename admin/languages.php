<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Lang;
use App\Services\LanguageService;

Auth::requireRoles(array('super_admin', 'admin'));

$csrfToken = Helpers::csrfToken();
$errors = array();
$successFlash = Helpers::getFlash('languages.success', '');
$successFlash = is_string($successFlash) ? $successFlash : '';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');

    $body = file_get_contents('php://input');
    $payload = json_decode($body, true);
    if (!is_array($payload)) {
        echo json_encode(array('success' => false, 'message' => 'Geçersiz istek verisi.'));
        exit;
    }

    $action = isset($payload['action']) ? (string) $payload['action'] : '';
    $token = isset($payload['csrf_token']) ? (string) $payload['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        echo json_encode(array('success' => false, 'message' => 'Oturum doğrulaması başarısız.'));
        exit;
    }

    if ($action === 'fetch_translations') {
        $locale = isset($payload['locale']) ? strtolower((string) $payload['locale']) : Lang::defaultLocale();
        $defaultCatalog = LanguageService::catalog(LanguageService::defaultCode());
        $targetCatalog = LanguageService::catalog($locale);

        $keys = array_unique(array_merge(array_keys($defaultCatalog), array_keys($targetCatalog)));
        sort($keys, SORT_STRING);

        $items = array();
        foreach ($keys as $key) {
            $items[] = array(
                'key' => $key,
                'default' => isset($defaultCatalog[$key]) ? $defaultCatalog[$key] : $key,
                'value' => isset($targetCatalog[$key]) ? $targetCatalog[$key] : '',
            );
        }

        echo json_encode(array(
            'success' => true,
            'items' => $items,
            'locale' => $locale,
        ));
        exit;
    }

    if ($action === 'save_translation') {
        $locale = isset($payload['locale']) ? strtolower((string) $payload['locale']) : '';
        $key = isset($payload['key']) ? (string) $payload['key'] : '';
        $value = isset($payload['value']) ? (string) $payload['value'] : '';

        if ($locale === '' || $key === '') {
            echo json_encode(array('success' => false, 'message' => 'Eksik alanlar.'));
            exit;
        }

        $updated = LanguageService::saveTranslation($locale, $key, $value);

        echo json_encode(array(
            'success' => $updated,
            'locale' => $locale,
            'key' => $key,
            'value' => $value,
        ));
        exit;
    }

    if ($action === 'delete_translation') {
        $locale = isset($payload['locale']) ? strtolower((string) $payload['locale']) : '';
        $key = isset($payload['key']) ? (string) $payload['key'] : '';

        if ($locale === '' || $key === '') {
            echo json_encode(array('success' => false, 'message' => 'Eksik alanlar.'));
            exit;
        }

        $deleted = LanguageService::deleteTranslation($locale, $key);

        echo json_encode(array('success' => $deleted));
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
        if ($action === 'create_language') {
            $code = isset($_POST['code']) ? strtolower(trim((string) $_POST['code'])) : '';
            $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
            $native = isset($_POST['native_name']) ? trim((string) $_POST['native_name']) : '';
            $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1';

            if ($code === '' || !preg_match('/^[a-z]{2,5}$/', $code)) {
                $errors[] = 'Dil kodu 2-5 karakter arasında olmalı ve yalnızca harf içermelidir.';
            } else {
                $created = LanguageService::create($code, $name, $native, $isActive, false);
                if ($created) {
                    Helpers::setFlash('languages.success', 'Yeni dil başarıyla eklendi.');
                    Helpers::redirect('/admin/languages.php');
                } else {
                    $errors[] = 'Dil eklenirken bir sorun oluştu.';
                }
            }
        } elseif ($action === 'set_default') {
            $code = isset($_POST['code']) ? strtolower(trim((string) $_POST['code'])) : '';
            if ($code === '') {
                $errors[] = 'Geçersiz dil kodu.';
            } else {
                if (LanguageService::setDefault($code)) {
                    Helpers::setFlash('languages.success', 'Varsayılan dil güncellendi.');
                    Helpers::redirect('/admin/languages.php');
                } else {
                    $errors[] = 'Varsayılan dil güncellenemedi.';
                }
            }
        } elseif ($action === 'toggle_language') {
            $code = isset($_POST['code']) ? strtolower(trim((string) $_POST['code'])) : '';
            $state = isset($_POST['state']) && $_POST['state'] === 'active';
            if ($code === '') {
                $errors[] = 'Geçersiz dil kodu.';
            } else {
                if (LanguageService::setActive($code, $state)) {
                    Helpers::setFlash('languages.success', 'Dil durumu güncellendi.');
                    Helpers::redirect('/admin/languages.php');
                } else {
                    $errors[] = 'Dil durumu güncellenemedi.';
                }
            }
        }
    }
}

$languages = LanguageService::languages(true);
$defaultLocale = LanguageService::defaultCode();
$availableLocales = array_map(static function ($language) {
    return isset($language['code']) ? $language['code'] : '';
}, $languages);

$pageTitle = 'Dil Yönetimi';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Diller</h5>
                <span class="badge bg-light text-dark">Aktif Dil: <?= strtoupper(Helpers::sanitize(Lang::locale())) ?></span>
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

                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Kod</th>
                            <th>Adı</th>
                            <th>Durum</th>
                            <th>Varsayılan</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($languages as $language): ?>
                            <?php
                            $code = isset($language['code']) ? strtolower((string) $language['code']) : '';
                            $isDefault = isset($language['is_default']) && (int) $language['is_default'] === 1;
                            $isActive = isset($language['is_active']) && (int) $language['is_active'] === 1;
                            ?>
                            <tr data-language-row data-language-code="<?= Helpers::sanitize($code) ?>">
                                <td class="fw-semibold text-uppercase"><?= Helpers::sanitize($code) ?></td>
                                <td>
                                    <div><?= Helpers::sanitize(isset($language['name']) ? $language['name'] : strtoupper($code)) ?></div>
                                    <div class="text-muted small"><?= Helpers::sanitize(isset($language['native_name']) ? $language['native_name'] : '') ?></div>
                                </td>
                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isDefault): ?>
                                        <span class="badge bg-primary">Varsayılan</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <form method="post" class="me-2">
                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                                            <input type="hidden" name="action" value="toggle_language">
                                            <input type="hidden" name="code" value="<?= Helpers::sanitize($code) ?>">
                                            <input type="hidden" name="state" value="<?= $isActive ? 'inactive' : 'active' ?>">
                                            <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                                <?= $isActive ? 'Pasifleştir' : 'Aktifleştir' ?>
                                            </button>
                                        </form>
                                        <?php if (!$isDefault): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                                                <input type="hidden" name="action" value="set_default">
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

                <hr>

                <h6 class="mb-3">Yeni Dil Ekle</h6>
                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_language">
                    <div>
                        <label class="form-label">Dil Kodu</label>
                        <input type="text" name="code" class="form-control" maxlength="5" placeholder="ör. en" required>
                        <small class="text-muted">Dil kodunu ISO 639-1 formatında girin.</small>
                    </div>
                    <div>
                        <label class="form-label">Dil Adı</label>
                        <input type="text" name="name" class="form-control" placeholder="English">
                    </div>
                    <div>
                        <label class="form-label">Yerel Ad</label>
                        <input type="text" name="native_name" class="form-control" placeholder="English">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="languageActiveSwitch" value="1" checked>
                        <label class="form-check-label" for="languageActiveSwitch">Dil aktif olsun</label>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Dili Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0">Çeviri Düzenleyici</h5>
                    <small class="text-muted">Bir dili seçerek çevirileri anında güncelleyebilirsiniz.</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <select class="form-select" id="translationLocaleSelector">
                        <?php foreach ($languages as $language): ?>
                            <?php $code = isset($language['code']) ? strtolower((string) $language['code']) : ''; ?>
                            <option value="<?= Helpers::sanitize($code) ?>" <?= $code === $defaultLocale ? 'selected' : '' ?>><?= strtoupper(Helpers::sanitize($code)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="position-relative">
                        <input type="search" class="form-control" id="translationSearch" placeholder="Çeviri ara...">
                        <span class="position-absolute top-50 end-0 translate-middle-y me-3 text-muted"><i class="bi bi-search"></i></span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div id="translationTable" data-csrf="<?= Helpers::sanitize($csrfToken) ?>" class="translation-table" data-default-locale="<?= Helpers::sanitize($defaultLocale) ?>"></div>
                <div id="translationEmptyState" class="alert alert-info d-none">Çeviri verisi yükleniyor...</div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var tableContainer = document.getElementById('translationTable');
        if (!tableContainer) { return; }

        var selector = document.getElementById('translationLocaleSelector');
        var searchInput = document.getElementById('translationSearch');
        var emptyState = document.getElementById('translationEmptyState');
        var csrf = tableContainer.getAttribute('data-csrf');

        function renderTranslations(items) {
            if (!items || items.length === 0) {
                tableContainer.innerHTML = '';
                if (emptyState) {
                    emptyState.classList.remove('d-none');
                    emptyState.textContent = 'Gösterilecek çeviri bulunamadı.';
                }
                return;
            }

            if (emptyState) {
                emptyState.classList.add('d-none');
            }

            var rows = items.map(function (item) {
                return '<tr>' +
                    '<td class="col-key"><code>' + escapeHtml(item.key) + '</code><div class="text-muted small">' + escapeHtml(item.default) + '</div></td>' +
                    '<td><textarea class="form-control form-control-sm" data-translation-key="' + encodeURIComponent(item.key) + '">' + escapeHtml(item.value) + '</textarea></td>' +
                    '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-translation-delete data-translation-key="' + encodeURIComponent(item.key) + '"><i class="bi bi-trash"></i></button></td>' +
                    '</tr>';
            }).join('');

            tableContainer.innerHTML = '<div class="table-responsive">' +
                '<table class="table table-sm align-middle">' +
                '<thead><tr><th>Anahtar</th><th>Çeviri</th><th class="text-end">Sil</th></tr></thead>' +
                '<tbody>' + rows + '</tbody>' +
                '</table></div>';
        }

        function escapeHtml(value) {
            return (value || '').replace(/[&<>"']/g, function (char) {
                var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
                return map[char] || char;
            });
        }

        function loadTranslations(locale) {
            if (!locale) { return; }
            if (emptyState) {
                emptyState.classList.remove('d-none');
                emptyState.textContent = 'Çeviriler yükleniyor...';
            }
            tableContainer.innerHTML = '';

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'fetch_translations', locale: locale, csrf_token: csrf })
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (!data.success) {
                    if (emptyState) {
                        emptyState.classList.remove('d-none');
                        emptyState.textContent = data.message || 'Çeviri verileri yüklenemedi.';
                    }
                    return;
                }

                tableContainer.setAttribute('data-active-locale', data.locale);
                renderTranslations(data.items || []);
            }).catch(function () {
                if (emptyState) {
                    emptyState.classList.remove('d-none');
                    emptyState.textContent = 'Çeviri verileri yüklenemedi.';
                }
            });
        }

        tableContainer.addEventListener('input', function (event) {
            var target = event.target;
            if (!target || target.tagName !== 'TEXTAREA') {
                return;
            }

            var locale = tableContainer.getAttribute('data-active-locale');
            var key = target.getAttribute('data-translation-key');
            if (!locale || !key) { return; }

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_translation',
                    locale: locale,
                    key: decodeURIComponent(key),
                    value: target.value,
                    csrf_token: csrf
                })
            });
        });

        tableContainer.addEventListener('click', function (event) {
            var target = event.target;
            if (!target) { return; }
            if (target.matches('[data-translation-delete]') || target.closest('[data-translation-delete]')) {
                var button = target.closest('[data-translation-delete]');
                var locale = tableContainer.getAttribute('data-active-locale');
                var key = button ? button.getAttribute('data-translation-key') : null;
                if (!locale || !key) { return; }

                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_translation',
                        locale: locale,
                        key: decodeURIComponent(key),
                        csrf_token: csrf
                    })
                }).then(function () {
                    loadTranslations(locale);
                });
            }
        });

        if (selector) {
            selector.addEventListener('change', function () {
                loadTranslations(this.value);
            });
            loadTranslations(selector.value);
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var term = this.value.toLowerCase();
                var rows = tableContainer.querySelectorAll('tbody tr');
                rows.forEach(function (row) {
                    var keyText = row.querySelector('.col-key') ? row.querySelector('.col-key').innerText.toLowerCase() : '';
                    if (!term || keyText.indexOf(term) !== -1) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                    }
                });
            });
        }
    })();
</script>

<?php include __DIR__ . '/../templates/footer.php';
