<?php
require __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Services\MegaMenuService;

Auth::requireAdmin(array('super_admin', 'admin', 'content'));

$pdo = Database::connection();
$csrfToken = Helpers::csrfToken();
$errors = array();
$messages = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        Helpers::redirectWithFlash('/admin/navigation/mega-menu.php', array(
            'mega.error' => 'Oturum doğrulama hatası. Lütfen tekrar deneyin.',
        ));
    }

    $action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';

    switch ($action) {
        case 'create_group':
            $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
            $active = !empty($_POST['is_active']);
            if ($name === '') {
                Helpers::redirectWithFlash('/admin/navigation/mega-menu.php', array(
                    'mega.error' => 'Grup adı boş olamaz.',
                ));
            }
            MegaMenuService::createGroup($name, $active);
            Helpers::redirectWithFlash('/admin/navigation/mega-menu.php', array(
                'mega.success' => 'Yeni mega menü grubu oluşturuldu.',
            ));
            break;

        case 'update_group':
            $groupId = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;
            $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
            $active = !empty($_POST['is_active']);
            MegaMenuService::updateGroup($groupId, $name, $active);
            Helpers::redirectWithFlash('/admin/navigation/mega-menu.php?group=' . $groupId, array(
                'mega.success' => 'Mega menü grubu güncellendi.',
            ));
            break;

        case 'delete_group':
            $groupId = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;
            MegaMenuService::deleteGroup($groupId);
            Helpers::redirectWithFlash('/admin/navigation/mega-menu.php', array(
                'mega.success' => 'Grup kaldırıldı.',
            ));
            break;

        case 'sort_groups':
            $orders = isset($_POST['orders']) && is_array($_POST['orders']) ? $_POST['orders'] : array();
            $payload = array();
            foreach ($orders as $orderRow) {
                if (!isset($orderRow['id'], $orderRow['sort'])) {
                    continue;
                }
                $payload[] = array(
                    'id' => (int) $orderRow['id'],
                    'sort_order' => (int) $orderRow['sort'],
                );
            }
            MegaMenuService::sortGroups($payload);
            Helpers::redirectWithFlash('/admin/navigation/mega-menu.php', array(
                'mega.success' => 'Grup sıralaması güncellendi.',
            ));
            break;

        case 'create_item':
            $groupId = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;
            $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
            $customLabel = isset($_POST['custom_label']) ? trim((string) $_POST['custom_label']) : '';
            $customUrl = isset($_POST['custom_url']) ? trim((string) $_POST['custom_url']) : '';
            $iconKey = isset($_POST['icon_key']) ? trim((string) $_POST['icon_key']) : '';
            $active = !empty($_POST['is_active']);
            $payload = array(
                'group_id' => $groupId,
                'category_id' => $categoryId,
                'custom_label' => $customLabel,
                'custom_url' => $customUrl,
                'icon_key' => $iconKey,
                'is_active' => $active,
            );
            $imageUpload = MegaMenuService::handleItemImageUpload($_FILES['custom_image'] ?? null);
            if ($imageUpload['status'] === 'error') {
                Helpers::redirectWithFlash('/admin/navigation/mega-menu.php?group=' . $groupId, array(
                    'mega.error' => $imageUpload['message'] ?? 'Görsel yüklenemedi.',
                ));
            } elseif ($imageUpload['status'] === 'success' && !empty($imageUpload['path'])) {
                $payload['custom_image'] = $imageUpload['path'];
            }
            MegaMenuService::createItem($payload);
            Helpers::redirectWithFlash('/admin/navigation/mega-menu.php?group=' . $groupId, array(
                'mega.success' => 'Menü öğesi eklendi.',
            ));
            break;

        case 'update_item':
            $itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
            $groupId = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;
            $payload = array(
                'category_id' => isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null,
                'custom_label' => isset($_POST['custom_label']) ? trim((string) $_POST['custom_label']) : '',
                'custom_url' => isset($_POST['custom_url']) ? trim((string) $_POST['custom_url']) : '',
                'icon_key' => isset($_POST['icon_key']) ? trim((string) $_POST['icon_key']) : '',
                'is_active' => !empty($_POST['is_active']),
            );
            $removeImage = !empty($_POST['remove_image']);
            $imageUpload = MegaMenuService::handleItemImageUpload($_FILES['custom_image'] ?? null);
            if ($imageUpload['status'] === 'error') {
                Helpers::redirectWithFlash('/admin/navigation/mega-menu.php?group=' . $groupId, array(
                    'mega.error' => $imageUpload['message'] ?? 'Görsel yüklenemedi.',
                ));
            } elseif ($imageUpload['status'] === 'success' && !empty($imageUpload['path'])) {
                $payload['custom_image'] = $imageUpload['path'];
            } elseif ($removeImage) {
                $payload['custom_image'] = null;
            }
            MegaMenuService::updateItem($itemId, $payload);
            Helpers::redirectWithFlash('/admin/navigation/mega-menu.php?group=' . $groupId, array(
                'mega.success' => 'Menü öğesi güncellendi.',
            ));
            break;

        case 'delete_item':
            $itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
            $groupId = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;
            MegaMenuService::deleteItem($itemId);
            Helpers::redirectWithFlash('/admin/navigation/mega-menu.php?group=' . $groupId, array(
                'mega.success' => 'Menü öğesi kaldırıldı.',
            ));
            break;

        case 'sort_items':
            $groupId = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;
            $orders = isset($_POST['orders']) && is_array($_POST['orders']) ? $_POST['orders'] : array();
            $payload = array();
            foreach ($orders as $orderRow) {
                if (!isset($orderRow['id'], $orderRow['sort'])) {
                    continue;
                }
                $payload[] = array(
                    'id' => (int) $orderRow['id'],
                    'sort_order' => (int) $orderRow['sort'],
                );
            }
            MegaMenuService::sortItems($groupId, $payload);
            Helpers::redirectWithFlash('/admin/navigation/mega-menu.php?group=' . $groupId, array(
                'mega.success' => 'Öğe sıralaması güncellendi.',
            ));
            break;

        default:
            Helpers::redirectWithFlash('/admin/navigation/mega-menu.php', array(
                'mega.error' => 'Geçersiz işlem.',
            ));
    }
}

$flashSuccess = Helpers::getFlash('mega.success');
$flashError = Helpers::getFlash('mega.error');

$groups = MegaMenuService::adminTree();
$selectedGroupId = isset($_GET['group']) ? (int) $_GET['group'] : 0;

if ($selectedGroupId <= 0 && $groups) {
    $selectedGroupId = (int) $groups[0]['id'];
}

$selectedGroup = null;
foreach ($groups as $group) {
    if ((int) $group['id'] === $selectedGroupId) {
        $selectedGroup = $group;
        break;
    }
}

try {
    $categoryStmt = $pdo->query('SELECT id, name, slug, icon_key FROM categories ORDER BY name ASC');
    $allCategories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
} catch (Throwable $categoryException) {
    $allCategories = array();
}

$pageTitle = 'Mega Menü';

include __DIR__ . '/../../templates/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">Mega Menü</h1>
            <p class="text-muted mb-0">Mega menü gruplarını ve menü öğelerini yönetin. Sürükle-bırak sıralama desteklenene kadar sıralama alanlarını kullanabilirsiniz.</p>
        </div>
        <div>
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#createGroupForm" aria-expanded="false" aria-controls="createGroupForm">
                <i class="bi bi-plus-lg me-1"></i> Yeni Grup
            </button>
        </div>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><?= Helpers::sanitize($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger"><?= Helpers::sanitize($flashError) ?></div>
    <?php endif; ?>

    <div class="collapse mb-4" id="createGroupForm">
        <div class="card">
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_group">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Grup Adı</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="createGroupActive" name="is_active" checked>
                                <label class="form-check-label" for="createGroupActive">Aktif</label>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end justify-content-end">
                            <button class="btn btn-success" type="submit"><i class="bi bi-check-lg me-1"></i> Kaydet</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h2 class="h5 mb-0">Gruplar</h2>
                    <?php if ($groups): ?>
                        <form method="post" class="d-inline-flex align-items-center gap-2">
                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                            <input type="hidden" name="action" value="sort_groups">
                            <?php foreach ($groups as $group): ?>
                                <input type="hidden" name="orders[<?= (int) $group['id'] ?>][id]" value="<?= (int) $group['id'] ?>">
                                <input type="hidden" name="orders[<?= (int) $group['id'] ?>][sort]" value="<?= (int) $group['sort_order'] ?>" data-sort-input>
                            <?php endforeach; ?>
                            <button class="btn btn-outline-secondary btn-sm" type="submit">Sıralamayı Kaydet</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (!$groups): ?>
                        <div class="list-group-item text-muted">Henüz grup oluşturulmadı.</div>
                    <?php endif; ?>
                    <?php foreach ($groups as $group): ?>
                        <?php $isActive = !empty($group['is_active']); ?>
                        <div class="list-group-item<?= (int) $group['id'] === $selectedGroupId ? ' active bg-primary text-white' : '' ?>">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div>
                                    <a href="/admin/navigation/mega-menu.php?group=<?= (int) $group['id'] ?>" class="fw-semibold<?= (int) $group['id'] === $selectedGroupId ? ' text-white' : '' ?>">
                                        <?= Helpers::sanitize($group['name']) ?>
                                    </a>
                                    <div class="small text-muted">Sıra: <span data-sort-display><?= (int) $group['sort_order'] ?></span> · <?= $isActive ? 'Aktif' : 'Pasif' ?></div>
                                </div>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editGroupModal-<?= (int) $group['id'] ?>"><i class="bi bi-pencil"></i></button>
                                    <form method="post" onsubmit="return confirm('Bu grubu silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete_group">
                                        <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <div class="modal fade" id="editGroupModal-<?= (int) $group['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                                            <input type="hidden" name="action" value="update_group">
                                            <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Grubu Düzenle</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Grup Adı</label>
                                                    <input type="text" name="name" class="form-control" value="<?= Helpers::sanitize($group['name']) ?>" required>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="groupActive<?= (int) $group['id'] ?>"<?= $isActive ? ' checked' : '' ?>>
                                                    <label class="form-check-label" for="groupActive<?= (int) $group['id'] ?>">Aktif</label>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">İptal</button>
                                                <button type="submit" class="btn btn-primary">Kaydet</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h2 class="h5 mb-0">Öğeler<?= $selectedGroup ? ' · ' . Helpers::sanitize($selectedGroup['name']) : '' ?></h2>
                    <?php if ($selectedGroup): ?>
                        <div>
                            <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#createItemForm" aria-expanded="false" aria-controls="createItemForm">
                                <i class="bi bi-plus-lg me-1"></i> Yeni Öğe
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$selectedGroup): ?>
                        <p class="text-muted">Önce bir grup seçin.</p>
                    <?php else: ?>
                        <div class="collapse mb-4" id="createItemForm">
                            <form method="post" enctype="multipart/form-data" class="border rounded p-3 bg-light">
                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                                <input type="hidden" name="action" value="create_item">
                                <input type="hidden" name="group_id" value="<?= (int) $selectedGroup['id'] ?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Kategori Bağlantısı</label>
                                        <select name="category_id" class="form-select">
                                            <option value="">Özel bağlantı kullan</option>
                                            <?php foreach ($allCategories as $category): ?>
                                                <option value="<?= (int) $category['id'] ?>"><?= Helpers::sanitize($category['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Özel Başlık</label>
                                        <input type="text" name="custom_label" class="form-control" placeholder="Özel bağlantılar için başlık">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Özel URL</label>
                                        <input type="text" name="custom_url" class="form-control" placeholder="https:// veya /yol">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">İkon Anahtarı</label>
                                        <input type="text" name="icon_key" class="form-control" placeholder="windows, pubg">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Görsel</label>
                                        <input type="file" name="custom_image" class="form-control" accept=".png,.jpg,.jpeg,.webp">
                                        <div class="form-text">PNG, JPG veya WebP formatında, 4 MB sınırı.</div>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="1" id="itemActive" name="is_active" checked>
                                            <label class="form-check-label" for="itemActive">Aktif</label>
                                        </div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-end">
                                        <button class="btn btn-success" type="submit">Ekle</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                            <input type="hidden" name="action" value="sort_items">
                            <input type="hidden" name="group_id" value="<?= (int) $selectedGroup['id'] ?>">
                            <div class="table-responsive">
                                <table class="table table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th style="width:60px">Sıra</th>
                                            <th>Başlık</th>
                                            <th>Tip</th>
                                            <th>Bağlantı</th>
                                            <th>İkon</th>
                                            <th>Görsel</th>
                                            <th>Durum</th>
                                            <th style="width:120px" class="text-end">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($selectedGroup['items'])): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Bu gruba henüz öğe eklenmemiş.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($selectedGroup['items'] as $item): ?>
                                            <tr>
                                                <td>
                                                    <input type="hidden" name="orders[<?= (int) $item['id'] ?>][id]" value="<?= (int) $item['id'] ?>">
                                                    <input type="number" name="orders[<?= (int) $item['id'] ?>][sort]" value="<?= (int) $item['sort_order'] ?>" class="form-control form-control-sm" min="0">
                                                </td>
                                                <td><?= Helpers::sanitize($item['category_name'] ?? ($item['custom_label'] ?? 'Öğe')) ?></td>
                                                <td><?= $item['category_id'] ? 'Kategori' : 'Özel' ?></td>
                                                <td class="text-truncate" style="max-width:220px;" title="<?= Helpers::sanitize($item['category_slug'] ?? ($item['custom_url'] ?? '')) ?>">
                                                    <?= Helpers::sanitize($item['category_slug'] ?? ($item['custom_url'] ?? '')) ?>
                                                </td>
                                                <td><?= Helpers::sanitize($item['icon_key'] ?? $item['category_icon'] ?? '') ?></td>
                                                <td>
                                                    <?php if (!empty($item['custom_image'])): ?>
                                                        <img src="<?= Helpers::sanitize($item['custom_image']) ?>" alt="Önizleme" class="img-thumbnail" style="max-height:48px;">
                                                    <?php else: ?>
                                                        <span class="text-muted small">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= !empty($item['is_active']) ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>' ?></td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#editItemModal-<?= (int) $item['id'] ?>"><i class="bi bi-pencil"></i></button>
                                                        <form method="post" onsubmit="return confirm('Bu öğeyi silmek istediğinize emin misiniz?');">
                                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                                                            <input type="hidden" name="action" value="delete_item">
                                                            <input type="hidden" name="group_id" value="<?= (int) $selectedGroup['id'] ?>">
                                                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                        </form>
                                                    </div>

                                                    <div class="modal fade" id="editItemModal-<?= (int) $item['id'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg">
                                                            <div class="modal-content">
                                                                <form method="post" enctype="multipart/form-data">
                                                                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                                                                    <input type="hidden" name="action" value="update_item">
                                                                    <input type="hidden" name="group_id" value="<?= (int) $selectedGroup['id'] ?>">
                                                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Öğeyi Düzenle</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="row g-3">
                                                                            <div class="col-md-6">
                                                                                <label class="form-label">Kategori</label>
                                                                                <select name="category_id" class="form-select">
                                                                                    <option value="">Özel bağlantı</option>
                                                                                    <?php foreach ($allCategories as $category): ?>
                                                                                        <option value="<?= (int) $category['id'] ?>"<?= !empty($item['category_id']) && (int) $item['category_id'] === (int) $category['id'] ? ' selected' : '' ?>><?= Helpers::sanitize($category['name']) ?></option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-md-6">
                                                                                <label class="form-label">Özel Başlık</label>
                                                                                <input type="text" name="custom_label" class="form-control" value="<?= Helpers::sanitize($item['custom_label'] ?? '') ?>">
                                                                            </div>
                                                                            <div class="col-md-6">
                                                                                <label class="form-label">Özel URL</label>
                                                                                <input type="text" name="custom_url" class="form-control" value="<?= Helpers::sanitize($item['custom_url'] ?? '') ?>">
                                                                            </div>
                                                                            <div class="col-md-3">
                                                                                <label class="form-label">İkon Anahtarı</label>
                                                                                <input type="text" name="icon_key" class="form-control" value="<?= Helpers::sanitize($item['icon_key'] ?? '') ?>">
                                                                            </div>
                                                                            <div class="col-md-6">
                                                                                <label class="form-label">Görsel</label>
                                                                                <?php if (!empty($item['custom_image'])): ?>
                                                                                    <div class="mb-2">
                                                                                        <img src="<?= Helpers::sanitize($item['custom_image']) ?>" alt="Önizleme" class="img-thumbnail" style="max-height: 80px;">
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                                <input type="file" name="custom_image" class="form-control" accept=".png,.jpg,.jpeg,.webp">
                                                                                <div class="form-text">PNG, JPG veya WebP formatı, 4 MB sınırı.</div>
                                                                                <?php if (!empty($item['custom_image'])): ?>
                                                                                    <div class="form-check mt-2">
                                                                                        <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImage<?= (int) $item['id'] ?>">
                                                                                        <label class="form-check-label" for="removeImage<?= (int) $item['id'] ?>">Mevcut görseli kaldır</label>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                            <div class="col-md-3 d-flex align-items-end">
                                                                                <div class="form-check">
                                                                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="itemActive<?= (int) $item['id'] ?>"<?= !empty($item['is_active']) ? ' checked' : '' ?>>
                                                                                    <label class="form-check-label" for="itemActive<?= (int) $item['id'] ?>">Aktif</label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">İptal</button>
                                                                        <button type="submit" class="btn btn-primary">Kaydet</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (!empty($selectedGroup['items'])): ?>
                                <div class="d-flex justify-content-end">
                                    <button class="btn btn-outline-secondary" type="submit">Sıralamayı Kaydet</button>
                                </div>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('[data-sort-input]').forEach(function (input) {
        input.addEventListener('change', function () {
            var display = this.closest('.list-group-item').querySelector('[data-sort-display]');
            if (display) {
                display.textContent = this.value;
            }
            this.setAttribute('value', this.value);
        });
    });
</script>

<?php include __DIR__ . '/../../templates/footer.php';
