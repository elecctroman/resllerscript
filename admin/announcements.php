<?php
require __DIR__ . '/../bootstrap.php';

use App\AuditLog;
use App\Auth;
use App\Database;
use App\Helpers;

$currentUser = Auth::requireAdmin(array('super_admin', 'admin', 'content'));
$pdo = Database::connection();

$errors = array();
$success = Helpers::getFlash('announcements.success', '');
$success = is_string($success) ? $success : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        if ($action === 'create_announcement') {
            $title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
            $body = isset($_POST['body']) ? trim((string) $_POST['body']) : '';
            $audience = isset($_POST['audience']) ? strtolower((string) $_POST['audience']) : 'reseller';
            $startsAtInput = isset($_POST['starts_at']) ? trim((string) $_POST['starts_at']) : '';
            $endsAtInput = isset($_POST['ends_at']) ? trim((string) $_POST['ends_at']) : '';
            $startsAt = null;
            $endsAt = null;
            $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;
            $pinned = isset($_POST['pinned']) && $_POST['pinned'] === '1' ? 1 : 0;

            if ($title === '' || $body === '') {
                $errors[] = 'Başlık ve duyuru metni zorunludur.';
            }

            if (!in_array($audience, array('reseller', 'admin', 'all'), true)) {
                $audience = 'reseller';
            }

            if ($startsAtInput !== '') {
                $startsTimestamp = strtotime($startsAtInput);
                if ($startsTimestamp === false) {
                    $errors[] = 'Başlangıç tarihi geçersiz.';
                } else {
                    $startsAt = date('Y-m-d H:i:s', $startsTimestamp);
                }
            }

            if ($endsAtInput !== '') {
                $endsTimestamp = strtotime($endsAtInput);
                if ($endsTimestamp === false) {
                    $errors[] = 'Bitiş tarihi geçersiz.';
                } else {
                    $endsAt = date('Y-m-d H:i:s', $endsTimestamp);
                }
            }

            if ($startsAt && $endsAt && $endsAt < $startsAt) {
                $errors[] = 'Bitiş tarihi başlangıç tarihinden önce olamaz.';
            }

            if (!$errors) {
                $stmt = $pdo->prepare('INSERT INTO announcements (title, body, audience, is_active, pinned, starts_at, ends_at, created_by, created_at) VALUES (:title, :body, :audience, :is_active, :pinned, :starts_at, :ends_at, :created_by, NOW())');
                $stmt->execute(array(
                    'title' => $title,
                    'body' => $body,
                    'audience' => $audience,
                    'is_active' => $isActive,
                    'pinned' => $pinned,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'created_by' => $currentUser['id'],
                ));

                $announcementId = (int) $pdo->lastInsertId();

                AuditLog::record(
                    $currentUser['id'],
                    'announcement.create',
                    'announcement',
                    $announcementId,
                    sprintf('Duyuru oluşturuldu: %s', $title)
                );

                Helpers::redirectWithFlash('/admin/announcements.php', array('announcements.success' => 'Duyuru başarıyla oluşturuldu.'));
                exit;
            }
        } elseif ($action === 'update_announcement') {
            $announcementId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
            $body = isset($_POST['body']) ? trim((string) $_POST['body']) : '';
            $audience = isset($_POST['audience']) ? strtolower((string) $_POST['audience']) : 'reseller';
            $startsAtInput = isset($_POST['starts_at']) ? trim((string) $_POST['starts_at']) : '';
            $endsAtInput = isset($_POST['ends_at']) ? trim((string) $_POST['ends_at']) : '';
            $startsAt = null;
            $endsAt = null;
            $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;
            $pinned = isset($_POST['pinned']) && $_POST['pinned'] === '1' ? 1 : 0;

            if ($announcementId <= 0 || $title === '' || $body === '') {
                $errors[] = 'Duyuru bilgileri eksik veya geçersiz.';
            }

            if (!in_array($audience, array('reseller', 'admin', 'all'), true)) {
                $audience = 'reseller';
            }

            if ($startsAtInput !== '') {
                $startsTimestamp = strtotime($startsAtInput);
                if ($startsTimestamp === false) {
                    $errors[] = 'Başlangıç tarihi geçersiz.';
                } else {
                    $startsAt = date('Y-m-d H:i:s', $startsTimestamp);
                }
            }

            if ($endsAtInput !== '') {
                $endsTimestamp = strtotime($endsAtInput);
                if ($endsTimestamp === false) {
                    $errors[] = 'Bitiş tarihi geçersiz.';
                } else {
                    $endsAt = date('Y-m-d H:i:s', $endsTimestamp);
                }
            }

            if ($startsAt && $endsAt && $endsAt < $startsAt) {
                $errors[] = 'Bitiş tarihi başlangıç tarihinden önce olamaz.';
            }

            if (!$errors) {
                $stmt = $pdo->prepare('UPDATE announcements SET title = :title, body = :body, audience = :audience, is_active = :is_active, pinned = :pinned, starts_at = :starts_at, ends_at = :ends_at, updated_at = NOW() WHERE id = :id');
                $stmt->execute(array(
                    'id' => $announcementId,
                    'title' => $title,
                    'body' => $body,
                    'audience' => $audience,
                    'is_active' => $isActive,
                    'pinned' => $pinned,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ));

                AuditLog::record(
                    $currentUser['id'],
                    'announcement.update',
                    'announcement',
                    $announcementId,
                    sprintf('Duyuru güncellendi: %s', $title)
                );

                Helpers::redirectWithFlash('/admin/announcements.php', array('announcements.success' => 'Duyuru güncellendi.'));
                exit;
            }
        } elseif ($action === 'delete_announcement') {
            $announcementId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            if ($announcementId <= 0) {
                $errors[] = 'Silinecek duyuru bulunamadı.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM announcements WHERE id = :id');
                $stmt->execute(array('id' => $announcementId));

                AuditLog::record(
                    $currentUser['id'],
                    'announcement.delete',
                    'announcement',
                    $announcementId,
                    sprintf('Duyuru silindi: #%d', $announcementId)
                );

                Helpers::redirectWithFlash('/admin/announcements.php', array('announcements.success' => 'Duyuru kaldırıldı.'));
                exit;
            }
        }
    }
}

try {
    $announcementsStmt = $pdo->query('SELECT a.*, u.name AS author_name FROM announcements a LEFT JOIN users u ON u.id = a.created_by ORDER BY pinned DESC, COALESCE(a.starts_at, a.created_at) DESC');
    $announcements = $announcementsStmt ? $announcementsStmt->fetchAll() : array();
} catch (\PDOException $exception) {
    $announcements = array();
    if (!$errors) {
        $errors[] = 'Duyurular yüklenirken bir hata oluştu: ' . $exception->getMessage();
    }
}

$pageTitle = 'Duyurular';

include __DIR__ . '/../templates/header.php';
?>
<?php if ($success): ?>
    <div class="alert alert-success mb-4"><?= Helpers::sanitize($success) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger mb-4">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= Helpers::sanitize($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Duyuru</h5>
            </div>
            <div class="card-body">
                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="create_announcement">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <div>
                        <label class="form-label">Başlık</label>
                        <input type="text" name="title" class="form-control" placeholder="Örn. Yeni kampanya" required>
                    </div>
                    <div>
                        <label class="form-label">Duyuru Metni</label>
                        <textarea name="body" class="form-control" rows="6" placeholder="Bayiler için görünmesini istediğiniz mesaj" required></textarea>
                        <small class="text-muted">Satır sonları duyuru kartında otomatik korunur.</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Hedef Kitle</label>
                            <select name="audience" class="form-select">
                                <option value="reseller">Bayiler</option>
                                <option value="admin">Yöneticiler</option>
                                <option value="all">Tümü</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Öncelik</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="announcementPinned" name="pinned" value="1">
                                <label class="form-check-label" for="announcementPinned">En üstte göster</label>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Başlangıç</label>
                            <input type="datetime-local" name="starts_at" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bitiş</label>
                            <input type="datetime-local" name="ends_at" class="form-control">
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="announcementActive" name="is_active" value="1" checked>
                        <label class="form-check-label" for="announcementActive">Hemen yayınla</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Duyuruyu Kaydet</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Duyuru Listesi</h5>
                <span class="badge bg-light text-dark">Toplam <?= (int)count($announcements) ?> kayıt</span>
            </div>
            <div class="card-body">
                <?php if (!$announcements): ?>
                    <p class="text-muted mb-0">Henüz duyuru oluşturulmamış.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Başlık</th>
                                <th>Hedef</th>
                                <th>Durum</th>
                                <th>Planlama</th>
                                <th class="text-end">İşlemler</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($announcements as $announcement): ?>
                                <?php
                                $id = (int) $announcement['id'];
                                $modalId = 'editAnnouncement' . $id;
                                $startsAtValue = isset($announcement['starts_at']) && $announcement['starts_at'] ? date('Y-m-d\TH:i', strtotime($announcement['starts_at'])) : '';
                                $endsAtValue = isset($announcement['ends_at']) && $announcement['ends_at'] ? date('Y-m-d\TH:i', strtotime($announcement['ends_at'])) : '';
                                ?>
                                <tr>
                                    <td><?= $id ?></td>
                                    <td>
                                        <strong><?= Helpers::sanitize($announcement['title']) ?></strong>
                                        <?php if (!empty($announcement['pinned'])): ?>
                                            <span class="badge bg-danger ms-1">Sabit</span>
                                        <?php endif; ?>
                                        <?php if (!empty($announcement['author_name'])): ?>
                                            <div class="text-muted small"><?= Helpers::sanitize($announcement['author_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= Helpers::sanitize(strtoupper($announcement['audience'])) ?></td>
                                    <td>
                                        <?php if ((int)$announcement['is_active'] === 1): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small text-muted">
                                            <?php if (!empty($announcement['starts_at'])): ?>
                                                <div><i class="bi bi-play-fill me-1"></i><?= date('d.m.Y H:i', strtotime($announcement['starts_at'])) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($announcement['ends_at'])): ?>
                                                <div><i class="bi bi-flag me-1"></i><?= date('d.m.Y H:i', strtotime($announcement['ends_at'])) ?></div>
                                            <?php endif; ?>
                                            <?php if (empty($announcement['starts_at']) && empty($announcement['ends_at'])): ?>
                                                <span>Sürekli yayın</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= Helpers::sanitize($modalId) ?>">Düzenle</button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Bu duyuruyu silmek istediğinize emin misiniz?');">
                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_announcement">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="<?= Helpers::sanitize($modalId) ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="post" class="modal-form">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Duyuruyu Düzenle</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                                                </div>
                                                <div class="modal-body vstack gap-3">
                                                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="update_announcement">
                                                    <input type="hidden" name="id" value="<?= $id ?>">
                                                    <div>
                                                        <label class="form-label">Başlık</label>
                                                        <input type="text" name="title" class="form-control" value="<?= Helpers::sanitize($announcement['title']) ?>" required>
                                                    </div>
                                                    <div>
                                                        <label class="form-label">Duyuru Metni</label>
                                                        <textarea name="body" class="form-control" rows="6" required><?= Helpers::sanitize($announcement['body']) ?></textarea>
                                                    </div>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Hedef Kitle</label>
                                                            <select name="audience" class="form-select">
                                                                <option value="reseller" <?= $announcement['audience'] === 'reseller' ? 'selected' : '' ?>>Bayiler</option>
                                                                <option value="admin" <?= $announcement['audience'] === 'admin' ? 'selected' : '' ?>>Yöneticiler</option>
                                                                <option value="all" <?= $announcement['audience'] === 'all' ? 'selected' : '' ?>>Tümü</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Öncelik</label>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" name="pinned" value="1" <?= !empty($announcement['pinned']) ? 'checked' : '' ?>>
                                                                <label class="form-check-label">En üstte göster</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Başlangıç</label>
                                                            <input type="datetime-local" name="starts_at" class="form-control" value="<?= Helpers::sanitize($startsAtValue) ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Bitiş</label>
                                                            <input type="datetime-local" name="ends_at" class="form-control" value="<?= Helpers::sanitize($endsAtValue) ?>">
                                                        </div>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= (int)$announcement['is_active'] === 1 ? 'checked' : '' ?>>
                                                        <label class="form-check-label">Aktif</label>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                                    <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
