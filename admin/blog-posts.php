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
$categories = $pdo->query('SELECT id, name FROM blog_categories ORDER BY name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Geçersiz güvenlik anahtarı. Lütfen sayfayı yenileyerek tekrar deneyin.';
    } else {
        if ($action === 'create_post') {
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
            $excerpt = isset($_POST['excerpt']) ? trim($_POST['excerpt']) : '';
            $content = isset($_POST['content']) ? trim($_POST['content']) : '';
            $imageUrl = isset($_POST['image_url']) ? trim($_POST['image_url']) : '';
            $status = isset($_POST['status']) && $_POST['status'] === 'published' ? 'published' : 'draft';
            $publishedAtInput = isset($_POST['published_at']) ? trim($_POST['published_at']) : '';
            $metaTitle = isset($_POST['meta_title']) ? trim($_POST['meta_title']) : '';
            $metaDescription = isset($_POST['meta_description']) ? trim($_POST['meta_description']) : '';
            $authorName = isset($_POST['author_name']) && $_POST['author_name'] !== '' ? trim($_POST['author_name']) : $currentUser['name'];

            if ($title === '') {
                $errors[] = 'Başlık alanı zorunludur.';
            }

            if ($content === '') {
                $errors[] = 'İçerik alanı boş bırakılamaz.';
            }

            if ($categoryId) {
                $categoryExists = $pdo->prepare('SELECT id FROM blog_categories WHERE id = :id LIMIT 1');
                $categoryExists->execute(array(':id' => $categoryId));
                if (!$categoryExists->fetchColumn()) {
                    $errors[] = 'Seçilen kategori bulunamadı.';
                }
            }

            if (!$errors) {
                $slug = BlogRepository::uniquePostSlug($title);
                $publishedAt = null;

                if ($status === 'published') {
                    if ($publishedAtInput !== '') {
                        $timestamp = strtotime($publishedAtInput);
                        if ($timestamp) {
                            $publishedAt = date('Y-m-d H:i:s', $timestamp);
                        }
                    }

                    if ($publishedAt === null) {
                        $publishedAt = date('Y-m-d H:i:s');
                    }
                }

                if ($excerpt === '') {
                    $excerpt = Helpers::truncate(strip_tags($content), 180);
                }

                $stmt = $pdo->prepare('INSERT INTO blog_posts (category_id, title, slug, excerpt, content, image_url, author_name, status, published_at, meta_title, meta_description, created_at) VALUES (:category_id, :title, :slug, :excerpt, :content, :image_url, :author_name, :status, :published_at, :meta_title, :meta_description, NOW())');
                $stmt->execute(array(
                    ':category_id' => $categoryId,
                    ':title' => $title,
                    ':slug' => $slug,
                    ':excerpt' => $excerpt !== '' ? $excerpt : null,
                    ':content' => $content,
                    ':image_url' => $imageUrl !== '' ? $imageUrl : null,
                    ':author_name' => $authorName !== '' ? $authorName : null,
                    ':status' => $status,
                    ':published_at' => $publishedAt,
                    ':meta_title' => $metaTitle !== '' ? $metaTitle : null,
                    ':meta_description' => $metaDescription !== '' ? $metaDescription : null,
                ));

                $success = 'Blog yazısı oluşturuldu.';

                AuditLog::record(
                    $currentUser['id'],
                    'blog_post.create',
                    'blog_post',
                    (int)$pdo->lastInsertId(),
                    sprintf('Blog yazısı oluşturuldu: %s', $title)
                );
            }
        } elseif ($action === 'update_post') {
            $postId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
            $excerpt = isset($_POST['excerpt']) ? trim($_POST['excerpt']) : '';
            $content = isset($_POST['content']) ? trim($_POST['content']) : '';
            $imageUrl = isset($_POST['image_url']) ? trim($_POST['image_url']) : '';
            $status = isset($_POST['status']) && $_POST['status'] === 'published' ? 'published' : 'draft';
            $publishedAtInput = isset($_POST['published_at']) ? trim($_POST['published_at']) : '';
            $metaTitle = isset($_POST['meta_title']) ? trim($_POST['meta_title']) : '';
            $metaDescription = isset($_POST['meta_description']) ? trim($_POST['meta_description']) : '';
            $authorName = isset($_POST['author_name']) ? trim($_POST['author_name']) : '';
            $slugInput = isset($_POST['slug']) ? trim($_POST['slug']) : '';

            if ($postId <= 0) {
                $errors[] = 'Geçersiz yazı seçimi.';
            }

            if ($title === '') {
                $errors[] = 'Başlık alanı zorunludur.';
            }

            if ($content === '') {
                $errors[] = 'İçerik alanı boş bırakılamaz.';
            }

            $postStmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = :id LIMIT 1');
            $postStmt->execute(array(':id' => $postId));
            $postRow = $postStmt->fetch();
            if (!$postRow) {
                $errors[] = 'Blog yazısı bulunamadı.';
            }

            if ($categoryId) {
                $categoryExists = $pdo->prepare('SELECT id FROM blog_categories WHERE id = :id LIMIT 1');
                $categoryExists->execute(array(':id' => $categoryId));
                if (!$categoryExists->fetchColumn()) {
                    $errors[] = 'Seçilen kategori bulunamadı.';
                }
            }

            if (!$errors) {
                $slug = $slugInput !== '' ? Helpers::slugify($slugInput) : BlogRepository::uniquePostSlug($title, $postId);
                if ($slug === '') {
                    $slug = BlogRepository::uniquePostSlug($title, $postId);
                }

                if ($slug !== $postRow['slug']) {
                    $slug = BlogRepository::uniquePostSlug($slug, $postId);
                }

                $publishedAt = null;
                if ($status === 'published') {
                    if ($publishedAtInput !== '') {
                        $timestamp = strtotime($publishedAtInput);
                        if ($timestamp) {
                            $publishedAt = date('Y-m-d H:i:s', $timestamp);
                        }
                    }

                    if ($publishedAt === null) {
                        $publishedAt = $postRow['published_at'] ? $postRow['published_at'] : date('Y-m-d H:i:s');
                    }
                }

                if ($excerpt === '') {
                    $excerpt = Helpers::truncate(strip_tags($content), 180);
                }

                $stmt = $pdo->prepare('UPDATE blog_posts SET category_id = :category_id, title = :title, slug = :slug, excerpt = :excerpt, content = :content, image_url = :image_url, author_name = :author_name, status = :status, published_at = :published_at, meta_title = :meta_title, meta_description = :meta_description, updated_at = NOW() WHERE id = :id');
                $stmt->execute(array(
                    ':id' => $postId,
                    ':category_id' => $categoryId,
                    ':title' => $title,
                    ':slug' => $slug,
                    ':excerpt' => $excerpt !== '' ? $excerpt : null,
                    ':content' => $content,
                    ':image_url' => $imageUrl !== '' ? $imageUrl : null,
                    ':author_name' => $authorName !== '' ? $authorName : null,
                    ':status' => $status,
                    ':published_at' => $publishedAt,
                    ':meta_title' => $metaTitle !== '' ? $metaTitle : null,
                    ':meta_description' => $metaDescription !== '' ? $metaDescription : null,
                ));

                $success = 'Blog yazısı güncellendi.';

                AuditLog::record(
                    $currentUser['id'],
                    'blog_post.update',
                    'blog_post',
                    $postId,
                    sprintf('Blog yazısı güncellendi: %s', $title)
                );
            }
        } elseif ($action === 'delete_post') {
            $postId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

            if ($postId <= 0) {
                $errors[] = 'Geçersiz yazı seçimi.';
            }

            if (!$errors) {
                $stmt = $pdo->prepare('DELETE FROM blog_posts WHERE id = :id');
                $stmt->execute(array(':id' => $postId));

                $success = 'Blog yazısı silindi.';

                AuditLog::record(
                    $currentUser['id'],
                    'blog_post.delete',
                    'blog_post',
                    $postId,
                    sprintf('Blog yazısı silindi: #%d', $postId)
                );
            }
        }
    }
}

$postsStmt = $pdo->query('SELECT p.*, c.name AS category_name FROM blog_posts AS p LEFT JOIN blog_categories AS c ON c.id = p.category_id ORDER BY p.created_at DESC');
$posts = $postsStmt ? $postsStmt->fetchAll() : array();

$pageTitle = 'Blog Yazıları';
include __DIR__ . '/../templates/header.php';
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-lg-8">
            <h1 class="h3 mb-0">Blog Yazıları</h1>
            <p class="text-muted">Sitenizde yayınlanan blog yazılarını buradan oluşturup yönetebilirsiniz.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPost"><i class="bi bi-plus-lg me-2"></i>Yeni Yazı</button>
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
                        <th>Kategori</th>
                        <th>Durum</th>
                        <th>Yayın</th>
                        <th class="text-end">İşlemler</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$posts): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Henüz blog yazısı oluşturulmamış.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                            <tr>
                                <td>#<?= (int)$post['id'] ?></td>
                                <td>
                                    <div class="fw-semibold"><?= Helpers::sanitize($post['title']) ?></div>
                                    <div class="text-muted small">/blog/<?= Helpers::sanitize($post['slug'] ?? '') ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($post['category_name'])): ?>
                                        <?= Helpers::sanitize($post['category_name']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">(Genel)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($post['status'] === 'published'): ?>
                                        <span class="badge bg-success">Yayında</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Taslak</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $post['published_at'] ? Helpers::sanitize(date('d.m.Y H:i', strtotime($post['published_at']))) : '—' ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPost<?= (int)$post['id'] ?>">Düzenle</button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Blog yazısını silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editPost<?= (int)$post['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-xl">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Blog Yazısını Düzenle</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="update_post">
                                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                                                <div class="row g-3">
                                                    <div class="col-lg-8">
                                                        <div class="mb-3">
                                                            <label class="form-label">Başlık</label>
                                                            <input type="text" name="title" class="form-control" value="<?= Helpers::sanitize($post['title']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Kısa Açıklama</label>
                                                            <textarea name="excerpt" class="form-control" rows="2"><?= Helpers::sanitize($post['excerpt'] ?? '') ?></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">İçerik</label>
                                                            <textarea name="content" class="form-control" rows="10" required><?= Helpers::sanitize($post['content'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-4">
                                                        <div class="mb-3">
                                                            <label class="form-label">Kategori</label>
                                                            <select name="category_id" class="form-select">
                                                                <option value="">(Genel)</option>
                                                                <?php foreach ($categories as $category): ?>
                                                                    <option value="<?= (int)$category['id'] ?>" <?= (int)$post['category_id'] === (int)$category['id'] ? 'selected' : '' ?>><?= Helpers::sanitize($category['name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Durum</label>
                                                            <select name="status" class="form-select">
                                                                <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>Taslak</option>
                                                                <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>Yayında</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Yayın Tarihi</label>
                                                            <input type="datetime-local" name="published_at" class="form-control" value="<?= $post['published_at'] ? Helpers::sanitize(date('Y-m-d\TH:i', strtotime($post['published_at']))) : '' ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Kapak Görseli URL</label>
                                                            <input type="url" name="image_url" class="form-control" value="<?= Helpers::sanitize($post['image_url'] ?? '') ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Yazar Adı</label>
                                                            <input type="text" name="author_name" class="form-control" value="<?= Helpers::sanitize($post['author_name'] ?? '') ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Slug</label>
                                                            <input type="text" name="slug" class="form-control" value="<?= Helpers::sanitize($post['slug'] ?? '') ?>">
                                                            <div class="form-text">Boş bırakırsanız otomatik oluşturulur.</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Meta Başlık</label>
                                                            <input type="text" name="meta_title" class="form-control" value="<?= Helpers::sanitize($post['meta_title'] ?? '') ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Meta Açıklama</label>
                                                            <textarea name="meta_description" class="form-control" rows="2"><?= Helpers::sanitize($post['meta_description'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
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

<div class="modal fade" id="createPost" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Blog Yazısı</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_post">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="mb-3">
                                <label class="form-label">Başlık</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Kısa Açıklama</label>
                                <textarea name="excerpt" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">İçerik</label>
                                <textarea name="content" class="form-control" rows="10" required></textarea>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="mb-3">
                                <label class="form-label">Kategori</label>
                                <select name="category_id" class="form-select">
                                    <option value="">(Genel)</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int)$category['id'] ?>"><?= Helpers::sanitize($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Durum</label>
                                <select name="status" class="form-select">
                                    <option value="draft">Taslak</option>
                                    <option value="published">Yayında</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Yayın Tarihi</label>
                                <input type="datetime-local" name="published_at" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Kapak Görseli URL</label>
                                <input type="url" name="image_url" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Yazar Adı</label>
                                <input type="text" name="author_name" class="form-control" value="<?= Helpers::sanitize($currentUser['name']) ?>">
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
