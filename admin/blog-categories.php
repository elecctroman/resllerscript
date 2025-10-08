<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Blog\BlogRepository;
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
        $errors[] = 'Geçersiz güvenlik anahtarı. Lütfen sayfayı yenileyerek tekrar deneyin.';
    } else {
        if ($action === 'create_category') {
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $metaTitle = isset($_POST['meta_title']) ? trim($_POST['meta_title']) : '';
            $metaDescription = isset($_POST['meta_description']) ? trim($_POST['meta_description']) : '';

            if ($name === '') {
                $errors[] = 'Kategori adı zorunludur.';
            }

            if (!$errors) {
                $slug = BlogRepository::uniqueCategorySlug($name);

                $stmt = $pdo->prepare('INSERT INTO blog_categories (name, slug, description, meta_title, meta_description, created_at) VALUES (:name, :slug, :description, :meta_title, :meta_description, NOW())');
                $stmt->execute(array(
                    ':name' => $name,
                    ':slug' => $slug,
                    ':description' => $description !== '' ? $description : null,
                    ':meta_title' => $metaTitle !== '' ? $metaTitle : null,
                    ':meta_description' => $metaDescription !== '' ? $metaDescription : null,
                ));

                $success = 'Kategori oluşturuldu.';

                AuditLog::record(
                    $currentUser['id'],
                    'blog_category.create',
                    'blog_category',
                    (int)$pdo->lastInsertId(),
                    sprintf('Blog kategorisi oluşturuldu: %s', $name)
                );
            }
        } elseif ($action === 'update_category') {
            $categoryId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $slugInput = isset($_POST['slug']) ? trim($_POST['slug']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $metaTitle = isset($_POST['meta_title']) ? trim($_POST['meta_title']) : '';
            $metaDescription = isset($_POST['meta_description']) ? trim($_POST['meta_description']) : '';

            if ($categoryId <= 0) {
                $errors[] = 'Geçersiz kategori seçimi.';
            }

            if ($name === '') {
                $errors[] = 'Kategori adı zorunludur.';
            }

            $category = $categoryId > 0 ? BlogRepository::findCategoryById($categoryId) : null;
            if (!$category) {
                $errors[] = 'Kategori bulunamadı.';
            }

            if (!$errors) {
                $slug = $slugInput !== '' ? Helpers::slugify($slugInput) : BlogRepository::uniqueCategorySlug($name, $categoryId);
                if ($slug === '') {
                    $slug = BlogRepository::uniqueCategorySlug($name, $categoryId);
                }

                if ($slug !== $category['slug']) {
                    $slug = BlogRepository::uniqueCategorySlug($slug, $categoryId);
                }

                $stmt = $pdo->prepare('UPDATE blog_categories SET name = :name, slug = :slug, description = :description, meta_title = :meta_title, meta_description = :meta_description, updated_at = NOW() WHERE id = :id');
                $stmt->execute(array(
                    ':id' => $categoryId,
                    ':name' => $name,
                    ':slug' => $slug,
                    ':description' => $description !== '' ? $description : null,
                    ':meta_title' => $metaTitle !== '' ? $metaTitle : null,
                    ':meta_description' => $metaDescription !== '' ? $metaDescription : null,
                ));

                $success = 'Kategori güncellendi.';

                AuditLog::record(
                    $currentUser['id'],
                    'blog_category.update',
                    'blog_category',
                    $categoryId,
                    sprintf('Blog kategorisi güncellendi: %s', $name)
                );
            }
        } elseif ($action === 'delete_category') {
            $categoryId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

            if ($categoryId <= 0) {
                $errors[] = 'Geçersiz kategori seçimi.';
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM blog_posts WHERE category_id = :id');
                $stmt->execute(array(':id' => $categoryId));
                $postCount = (int)$stmt->fetchColumn();

                if ($postCount > 0) {
                    $errors[] = 'Bu kategoriye bağlı yazılar bulunduğu için silinemez.';
                }
            }

            if (!$errors) {
                $stmt = $pdo->prepare('DELETE FROM blog_categories WHERE id = :id');
                $stmt->execute(array(':id' => $categoryId));

                $success = 'Kategori silindi.';

                AuditLog::record(
                    $currentUser['id'],
                    'blog_category.delete',
                    'blog_category',
                    $categoryId,
                    sprintf('Blog kategorisi silindi: #%d', $categoryId)
                );
            }
        }
    }
}

$categories = $pdo->query('SELECT c.*, COUNT(p.id) AS post_count FROM blog_categories AS c LEFT JOIN blog_posts AS p ON p.category_id = c.id GROUP BY c.id ORDER BY c.created_at DESC')->fetchAll();

$pageTitle = 'Blog Kategorileri';
include __DIR__ . '/../templates/header.php';
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-lg-8">
            <h1 class="h3 mb-0">Blog Kategorileri</h1>
            <p class="text-muted">Blog yazılarını düzenlemek için kategorileri yönetin.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCategory"><i class="bi bi-plus-lg me-2"></i>Yeni Kategori</button>
        </div>
    </div>

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
        <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Başlık</th>
                        <th>Slug</th>
                        <th>Yazı Sayısı</th>
                        <th>Oluşturma</th>
                        <th class="text-end">İşlemler</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$categories): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Henüz kategori oluşturulmamış.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>#<?= (int)$category['id'] ?></td>
                                <td><?= Helpers::sanitize($category['name']) ?></td>
                                <td><span class="badge bg-light text-dark"><?= Helpers::sanitize($category['slug'] ?? '') ?></span></td>
                                <td><?= (int)$category['post_count'] ?></td>
                                <td><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($category['created_at']))) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCategory<?= (int)$category['id'] ?>">Düzenle</button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Kategoriyi silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editCategory<?= (int)$category['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Kategoriyi Düzenle</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="update_category">
                                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Kategori Adı</label>
                                                    <input type="text" name="name" class="form-control" value="<?= Helpers::sanitize($category['name']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">URL Slug</label>
                                                    <input type="text" name="slug" class="form-control" value="<?= Helpers::sanitize($category['slug'] ?? '') ?>">
                                                    <div class="form-text">Boş bırakırsanız otomatik oluşturulur.</div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Açıklama</label>
                                                    <textarea name="description" class="form-control" rows="3"><?= Helpers::sanitize($category['description'] ?? '') ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Meta Başlık</label>
                                                    <input type="text" name="meta_title" class="form-control" value="<?= Helpers::sanitize($category['meta_title'] ?? '') ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Meta Açıklama</label>
                                                    <textarea name="meta_description" class="form-control" rows="2"><?= Helpers::sanitize($category['meta_description'] ?? '') ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                                <button type="submit" class="btn btn-primary">Kaydet</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createCategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Blog Kategorisi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_category">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <div class="mb-3">
                        <label class="form-label">Kategori Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meta Başlık</label>
                        <input type="text" name="meta_title" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meta Açıklama</label>
                        <textarea name="meta_description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Oluştur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php';
