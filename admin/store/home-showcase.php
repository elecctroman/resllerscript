<?php
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../store/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Settings;

Auth::requireAdmin(array('super_admin', 'admin', 'content'));

try {
    $pdo = Database::connection();
} catch (Throwable $exception) {
    Helpers::abort(500, 'Veritabanı bağlantısı kurulamadı: ' . $exception->getMessage());
    return;
}

$errors = array();
$success = '';

try {
    $categoryStmt = $pdo->query('SELECT id, name, slug, icon_key FROM categories ORDER BY name ASC');
    $allCategories = $categoryStmt ? ($categoryStmt->fetchAll(PDO::FETCH_ASSOC) ?: array()) : array();
} catch (Throwable $exception) {
    $allCategories = array();
}

$categoryMap = array();
foreach ($allCategories as $categoryRow) {
    $categoryMap[(int) $categoryRow['id']] = $categoryRow;
}

$currentSettings = store_home_showcase_settings();
$selectedIds = array();
foreach ($currentSettings['categories'] as $row) {
    if (isset($row['id'])) {
        $selectedIds[] = (int) $row['id'];
    }
}
$selectedIds = array_values(array_unique(array_filter($selectedIds)));
$limitValue = isset($currentSettings['limit']) ? (int) $currentSettings['limit'] : 5;
$sortOption = isset($currentSettings['sort']) ? (string) $currentSettings['sort'] : 'custom';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $orderInput = isset($_POST['selected_order']) ? trim((string) $_POST['selected_order']) : '';
        $rawIds = array();
        if ($orderInput !== '') {
            foreach (explode(',', $orderInput) as $part) {
                $id = (int) trim($part);
                if ($id > 0) {
                    $rawIds[] = $id;
                }
            }
        }
        $rawIds = array_values(array_unique($rawIds));
        $validSelected = array();
        foreach ($rawIds as $candidateId) {
            if (isset($categoryMap[$candidateId])) {
                $validSelected[] = $candidateId;
            }
        }

        $limitValue = isset($_POST['category_limit']) ? (int) $_POST['category_limit'] : $limitValue;
        if ($limitValue < 1) {
            $limitValue = 1;
        } elseif ($limitValue > 20) {
            $limitValue = 20;
        }

        $sortOption = isset($_POST['sort_option']) ? strtolower((string) $_POST['sort_option']) : $sortOption;
        $allowedSorts = array('custom', 'alphabetical', 'latest');
        if (!in_array($sortOption, $allowedSorts, true)) {
            $sortOption = 'custom';
        }

        if (!$validSelected) {
            $errors[] = 'En az bir kategori seçmelisiniz.';
        }

        if (!$errors) {
            $payload = array(
                'categories' => array(),
                'limit' => $limitValue,
                'sort' => $sortOption,
            );
            $order = 1;
            foreach ($validSelected as $categoryId) {
                $payload['categories'][] = array(
                    'id' => $categoryId,
                    'sort_order' => $order++,
                );
            }

            Settings::set('store_home_showcase', json_encode($payload, JSON_UNESCAPED_UNICODE));
            Settings::clearCache('store_home_showcase');

            $currentSettings = $payload;
            $selectedIds = $validSelected;
            $success = 'Ana sayfa vitrin ayarları kaydedildi.';
        }
    }
}

$csrfToken = Helpers::csrfToken();
$pageTitle = 'Ana Sayfa Vitrin Ayarları';

include __DIR__ . '/../../templates/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Ana Sayfa Vitrin Ayarları</h1>
            <p class="text-muted mb-0">Ana vitrinde hangi kategorilerin ve kaç ürünün öne çıkarılacağını yönetin.</p>
        </div>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= Helpers::sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Kategoriler</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Sol listeden kategorileri seçerek sağ tarafa ekleyin. Sürükleyip bırakarak sıralamayı değiştirebilirsiniz.</p>
                    <div class="showcase-lists">
                        <div class="showcase-column">
                            <h3 class="h6">Tüm Kategoriler</h3>
                            <ul class="showcase-list" data-available-list>
                                <?php foreach ($allCategories as $category): ?>
                                    <?php $categoryId = (int) $category['id']; ?>
                                    <li class="showcase-item" data-category-id="<?= $categoryId ?>" data-category-name="<?= Helpers::sanitize($category['name']) ?>"<?= in_array($categoryId, $selectedIds, true) ? ' aria-disabled="true" data-selected="true"' : '' ?>>
                                        <span class="showcase-item__label"><i class="bi bi-folder me-2"></i><?= Helpers::sanitize($category['name']) ?></span>
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-add-category>Ekle</button>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (!$allCategories): ?>
                                    <li class="text-muted small">Kayıtlı kategori bulunamadı.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="showcase-column">
                            <h3 class="h6">Seçilen Kategoriler</h3>
                            <ul class="showcase-list showcase-list--selected" data-selected-list>
                                <?php foreach ($selectedIds as $selectedId): ?>
                                    <?php if (!isset($categoryMap[$selectedId])) { continue; } ?>
                                    <?php $category = $categoryMap[$selectedId]; ?>
                                    <li class="showcase-item" data-category-id="<?= (int) $category['id'] ?>" draggable="true">
                                        <span class="showcase-item__label"><i class="bi bi-grip-vertical me-2"></i><?= Helpers::sanitize($category['name']) ?></span>
                                        <button class="btn btn-outline-danger btn-sm" type="button" data-remove-category>&times;</button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Vitrin Ayarları</h2>
                </div>
                <div class="card-body">
                    <form method="post" class="vstack gap-3">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                        <input type="hidden" name="selected_order" value="<?= Helpers::sanitize(implode(',', $selectedIds)) ?>" data-selected-order>
                        <div>
                            <label class="form-label" for="categoryLimit">Kategori başına ürün limiti</label>
                            <input type="number" class="form-control" id="categoryLimit" name="category_limit" value="<?= (int) $limitValue ?>" min="1" max="20">
                            <div class="form-text">Her kategori için listelenecek ürün sayısı.</div>
                        </div>
                        <div>
                            <label class="form-label" for="sortOption">Sıralama</label>
                            <select class="form-select" id="sortOption" name="sort_option">
                                <option value="custom"<?= $sortOption === 'custom' ? ' selected' : '' ?>>Özel sıra</option>
                                <option value="alphabetical"<?= $sortOption === 'alphabetical' ? ' selected' : '' ?>>A-Z</option>
                                <option value="latest"<?= $sortOption === 'latest' ? ' selected' : '' ?>>Son eklenen</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary align-self-start">Kaydet</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var availableList = document.querySelector('[data-available-list]');
    var selectedList = document.querySelector('[data-selected-list]');
    var orderInput = document.querySelector('[data-selected-order]');

    if (!availableList || !selectedList || !orderInput) {
        return;
    }

    function updateOrderInput() {
        var ids = [];
        selectedList.querySelectorAll('[data-category-id]').forEach(function (item) {
            ids.push(item.getAttribute('data-category-id'));
        });
        orderInput.value = ids.join(',');
    }

    function setAvailableState(categoryId, disabled) {
        var availableItem = availableList.querySelector('[data-category-id="' + categoryId + '"]');
        if (!availableItem) {
            return;
        }
        if (disabled) {
            availableItem.setAttribute('aria-disabled', 'true');
            availableItem.setAttribute('data-selected', 'true');
        } else {
            availableItem.removeAttribute('aria-disabled');
            availableItem.removeAttribute('data-selected');
        }
    }

    availableList.addEventListener('click', function (event) {
        var button = event.target.closest('[data-add-category]');
        if (!button) {
            return;
        }
        var item = button.closest('[data-category-id]');
        if (!item || item.getAttribute('aria-disabled') === 'true') {
            return;
        }
        var categoryId = item.getAttribute('data-category-id');
        var categoryName = item.getAttribute('data-category-name') || 'Kategori';

        var listItem = document.createElement('li');
        listItem.className = 'showcase-item';
        listItem.setAttribute('data-category-id', categoryId);
        listItem.setAttribute('draggable', 'true');
        listItem.innerHTML = '<span class="showcase-item__label"><i class="bi bi-grip-vertical me-2"></i>' + categoryName + '</span>' +
            '<button class="btn btn-outline-danger btn-sm" type="button" data-remove-category>&times;</button>';
        selectedList.appendChild(listItem);
        setAvailableState(categoryId, true);
        updateOrderInput();
    });

    selectedList.addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove-category]');
        if (!button) {
            return;
        }
        var item = button.closest('[data-category-id]');
        if (!item) {
            return;
        }
        var categoryId = item.getAttribute('data-category-id');
        item.remove();
        setAvailableState(categoryId, false);
        updateOrderInput();
    });

    var dragSource = null;
    selectedList.addEventListener('dragstart', function (event) {
        var item = event.target.closest('[data-category-id]');
        if (!item) {
            return;
        }
        dragSource = item;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', item.getAttribute('data-category-id'));
        item.classList.add('is-dragging');
    });

    selectedList.addEventListener('dragend', function (event) {
        var item = event.target.closest('[data-category-id]');
        if (item) {
            item.classList.remove('is-dragging');
        }
        dragSource = null;
        updateOrderInput();
    });

    selectedList.addEventListener('dragover', function (event) {
        if (!dragSource) {
            return;
        }
        event.preventDefault();
        var target = event.target.closest('[data-category-id]');
        if (!target || target === dragSource) {
            return;
        }
        var rect = target.getBoundingClientRect();
        var isAfter = (event.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
        selectedList.insertBefore(dragSource, isAfter ? target.nextSibling : target);
    });

    updateOrderInput();
})();
</script>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
