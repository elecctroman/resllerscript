<?php
require __DIR__ . '/../bootstrap.php';

use App\AuditLog;
use App\Auth;
use App\Database;
use App\Helpers;

$currentUser = Auth::requireAdmin(array('super_admin', 'admin', 'content'));
$pdo = Database::connection();

$errors = array();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Geçersiz istek. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        if ($action === 'create_instruction') {
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $summary = isset($_POST['summary']) ? trim($_POST['summary']) : '';
            $content = isset($_POST['content']) ? trim($_POST['content']) : '';
            $isActive = isset($_POST['is_active']) && (int)$_POST['is_active'] === 1 ? 1 : 0;

            if ($title === '' || $content === '') {
                $errors[] = 'Başlık ve açıklama alanları zorunludur.';
            }

            if (!$errors) {
                $stmt = $pdo->prepare('INSERT INTO instructions (title, summary, content, is_active, created_at) VALUES (:title, :summary, :content, :is_active, NOW())');
                $stmt->execute(array(
                    'title' => $title,
                    'summary' => $summary !== '' ? $summary : null,
                    'content' => $content,
                    'is_active' => $isActive,
                ));

                $instructionId = (int)$pdo->lastInsertId();
                AuditLog::record(
                    $currentUser['id'],
                    'instruction.create',
                    'instruction',
                    $instructionId,
                    sprintf('Talimat oluşturuldu: %s', $title)
                );

                $success = 'Talimat başarıyla eklendi.';
            }
        } elseif ($action === 'update_instruction') {
            $instructionId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $summary = isset($_POST['summary']) ? trim($_POST['summary']) : '';
            $content = isset($_POST['content']) ? trim($_POST['content']) : '';
            $isActive = isset($_POST['is_active']) && (int)$_POST['is_active'] === 1 ? 1 : 0;

            if ($instructionId <= 0 || $title === '' || $content === '') {
                $errors[] = 'Talimat bilgileri eksik veya geçersiz.';
            }

            if (!$errors) {
                $stmt = $pdo->prepare('UPDATE instructions SET title = :title, summary = :summary, content = :content, is_active = :is_active, updated_at = NOW() WHERE id = :id');
                $stmt->execute(array(
                    'id' => $instructionId,
                    'title' => $title,
                    'summary' => $summary !== '' ? $summary : null,
                    'content' => $content,
                    'is_active' => $isActive,
                ));

                AuditLog::record(
                    $currentUser['id'],
                    'instruction.update',
                    'instruction',
                    $instructionId,
                    sprintf('Talimat güncellendi: %s', $title)
                );

                $success = 'Talimat güncellendi.';
            }
        } elseif ($action === 'delete_instruction') {
            $instructionId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($instructionId <= 0) {
                $errors[] = 'Silinecek talimat seçilemedi.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM instructions WHERE id = :id');
                $stmt->execute(array('id' => $instructionId));

                AuditLog::record(
                    $currentUser['id'],
                    'instruction.delete',
                    'instruction',
                    $instructionId,
                    sprintf('Talimat silindi: #%d', $instructionId)
                );

                $success = 'Talimat kaldırıldı.';
            }
        }
    }
}

$instructionStmt = $pdo->query('SELECT * FROM instructions ORDER BY created_at DESC');
$instructions = $instructionStmt ? $instructionStmt->fetchAll() : array();

$pageTitle = 'Talimatlar';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Talimat</h5>
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

                <?php if ($success): ?>
                    <div class="alert alert-success mb-3"><?= Helpers::sanitize($success) ?></div>
                <?php endif; ?>

                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="create_instruction">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <div>
                        <label class="form-label">Başlık</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Kısa Özet <span class="text-muted">(Opsiyonel)</span></label>
                        <input type="text" name="summary" class="form-control" placeholder="Ürünün aktivasyonu için adımlar">
                    </div>
                    <div>
                        <label class="form-label">İçerik</label>
                        <textarea name="content" class="form-control" rows="6" placeholder="Ürünün kurulumu, kullanım ve dikkat edilmesi gerekenler" required></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" id="instructionActive" name="is_active" value="1" checked>
                        <label class="form-check-label" for="instructionActive">Talimat aktif</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Talimatı Kaydet</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Talimat Listesi</h5>
            </div>
            <div class="card-body">
                <?php if (!$instructions): ?>
                    <p class="text-muted mb-0">Henüz kayıtlı talimat bulunmuyor.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Başlık</th>
                                <th>Durum</th>
                                <th>Oluşturulma</th>
                                <th class="text-end">İşlemler</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($instructions as $instruction): ?>
                                <tr>
                                    <td><?= (int)$instruction['id'] ?></td>
                                    <td>
                                        <strong><?= Helpers::sanitize($instruction['title']) ?></strong>
                                        <?php if (!empty($instruction['summary'])): ?>
                                            <div class="text-muted small"><?= Helpers::sanitize($instruction['summary']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$instruction['is_active'] === 1): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Taslak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= Helpers::sanitize(isset($instruction['created_at']) ? date('d.m.Y H:i', strtotime($instruction['created_at'])) : '-') ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editInstruction<?= (int)$instruction['id'] ?>">Düzenle</button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Talimatı silmek istediğinize emin misiniz?');">
                                            <input type="hidden" name="action" value="delete_instruction">
                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= (int)$instruction['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editInstruction<?= (int)$instruction['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="post">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Talimatı Düzenle</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="update_instruction">
                                                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$instruction['id'] ?>">
                                                    <div class="row g-3">
                                                        <div class="col-12">
                                                            <label class="form-label">Başlık</label>
                                                            <input type="text" name="title" class="form-control" value="<?= Helpers::sanitize($instruction['title']) ?>" required>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Kısa Özet</label>
                                                            <input type="text" name="summary" class="form-control" value="<?= Helpers::sanitize(isset($instruction['summary']) ? $instruction['summary'] : '') ?>" placeholder="Opsiyonel">
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">İçerik</label>
                                                            <textarea name="content" class="form-control" rows="6" required><?= Helpers::sanitize($instruction['content']) ?></textarea>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="form-check form-switch">
                                                                <input type="hidden" name="is_active" value="0">
                                                                <input class="form-check-input" type="checkbox" id="instructionActive<?= (int)$instruction['id'] ?>" name="is_active" value="1" <?= (int)$instruction['is_active'] === 1 ? 'checked' : '' ?>>
                                                                <label class="form-check-label" for="instructionActive<?= (int)$instruction['id'] ?>">Talimat aktif</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                                    <button type="submit" class="btn btn-primary">Güncelle</button>
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
