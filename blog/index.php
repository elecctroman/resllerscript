<?php
require __DIR__ . '/../bootstrap.php';

use App\Blog\BlogRepository;
use App\Helpers;
use App\Lang;

Lang::boot();

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$slug = trim($slug, '/');

$categorySlug = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 9;
$offset = ($page - 1) * $perPage;

if ($slug !== '') {
    $post = BlogRepository::findPostBySlug($slug, true);

    if (!$post) {
        http_response_code(404);
        $pageTitle = 'Yazı bulunamadı';
        Helpers::includeTemplate('public-header.php', array('pageTitle' => $pageTitle));
        ?>
        <div class="public-hero text-center">
            <h1 class="display-5 mb-3">Yazı bulunamadı</h1>
            <p class="lead">Aradığınız içerik yayından kaldırılmış veya taşınmış olabilir.</p>
            <a class="btn btn-primary mt-3" href="/blog/">Blog ana sayfasına dön</a>
        </div>
        <?php
        Helpers::includeTemplate('public-footer.php');
        exit;
    }

    $metaDescription = $post['meta_description'] ?: ($post['excerpt'] ?: Helpers::seoDescription());
    $pageTitle = $post['meta_title'] ?: $post['title'];
    $categories = BlogRepository::categories();
    $related = BlogRepository::relatedPosts((int)$post['id'], isset($post['category_id']) ? (int)$post['category_id'] : null, 4);

    Helpers::includeTemplate('public-header.php', array(
        'pageTitle' => $pageTitle,
        'metaDescription' => $metaDescription,
    ));
    ?>
    <article class="public-article">
        <div class="row g-5">
            <div class="col-lg-8">
                <div class="public-hero">
                    <?php if (!empty($post['category_name'])): ?>
                        <span class="public-badge-category mb-3 d-inline-flex align-items-center"><i class="bi bi-folder2 me-2"></i><?= Helpers::sanitize($post['category_name']) ?></span>
                    <?php endif; ?>
                    <h1 class="display-5 mb-3"><?= Helpers::sanitize($post['title']) ?></h1>
                    <div class="public-card-meta d-flex gap-3 flex-wrap">
                        <?php if (!empty($post['author_name'])): ?>
                            <span><i class="bi bi-person me-2"></i><?= Helpers::sanitize($post['author_name']) ?></span>
                        <?php endif; ?>
                        <span><i class="bi bi-calendar-event me-2"></i><?= Helpers::sanitize(date('d F Y', strtotime($post['published_at'] ?: $post['created_at']))) ?></span>
                    </div>
                </div>

                <?php if (!empty($post['image_url'])): ?>
                    <img src="<?= Helpers::sanitize($post['image_url'] ?? '') ?>" class="img-fluid mb-4" alt="<?= Helpers::sanitize($post['title']) ?>">
                <?php endif; ?>

                <div class="public-card p-4 public-article-content">
                    <?= $post['content'] ?>
                </div>
            </div>
            <div class="col-lg-4">
                <aside class="public-sidebar mb-4">
                    <h5 class="mb-3">Kategoriler</h5>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($categories as $category): ?>
                            <li class="mb-2">
                                <a href="/blog/?category=<?= rawurlencode($category['slug']) ?>">
                                    <?= Helpers::sanitize($category['name']) ?>
                                    <span class="badge bg-secondary ms-2"><?= (int)$category['post_count'] ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </aside>

                <?php if ($related): ?>
                    <div class="public-sidebar">
                        <h5 class="mb-3">Benzer Yazılar</h5>
                        <div class="d-grid gap-3">
                            <?php foreach ($related as $item): ?>
                                <a href="/blog/<?= rawurlencode($item['slug']) ?>" class="public-related-item text-decoration-none d-block">
                                    <div class="fw-semibold mb-1 text-white"><?= Helpers::sanitize($item['title']) ?></div>
                                    <div class="small text-muted">
                                        <?= Helpers::sanitize(date('d.m.Y', strtotime($item['published_at'] ?: $item['created_at']))) ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
    Helpers::includeTemplate('public-footer.php');
    exit;
}

$selectedCategory = null;
if ($categorySlug !== '') {
    $selectedCategory = BlogRepository::findCategoryBySlug($categorySlug);
}

$categories = BlogRepository::categories();
$categoryId = $selectedCategory ? (int)$selectedCategory['id'] : null;
$posts = BlogRepository::listPosts($perPage, $offset, $categoryId, true);
$totalPosts = BlogRepository::countPosts($categoryId, true);
$totalPages = $totalPosts > 0 ? (int)ceil($totalPosts / $perPage) : 1;

$heroTitle = $selectedCategory ? $selectedCategory['name'] : 'Blog Yazıları';
$heroDescription = $selectedCategory && !empty($selectedCategory['description'])
    ? $selectedCategory['description']
    : 'E-Pin, oyun hesapları ve dijital ürünler dünyasından ipuçları, kampanyalar ve güncel duyurular.';

Helpers::includeTemplate('public-header.php', array(
    'pageTitle' => $selectedCategory ? ($selectedCategory['meta_title'] ?: $selectedCategory['name']) : 'Blog',
    'metaDescription' => $selectedCategory ? ($selectedCategory['meta_description'] ?: Helpers::seoDescription()) : Helpers::seoDescription(),
));
?>
<section class="public-hero">
    <h1 class="display-5 mb-3"><?= Helpers::sanitize($heroTitle) ?></h1>
    <p class="lead mb-0"><?= Helpers::sanitize($heroDescription) ?></p>
</section>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="row g-4">
            <?php if (!$posts): ?>
                <div class="col-12">
                    <div class="public-card p-5 text-center text-muted">
                        Henüz yayınlanmış blog içeriği bulunmuyor.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?php $summary = $post['excerpt'] ?: Helpers::truncate(strip_tags($post['content'] ?? ''), 140); ?>
                    <div class="col-md-6">
                        <article class="public-card h-100">
                            <?php if (!empty($post['image_url'])): ?>
                                <img src="<?= Helpers::sanitize($post['image_url'] ?? '') ?>" class="w-100" alt="<?= Helpers::sanitize($post['title']) ?>" height="200">
                            <?php endif; ?>
                            <div class="p-4 d-flex flex-column h-100">
                                <?php if (!empty($post['category_name'])): ?>
                                    <span class="public-badge align-self-start mb-2 text-uppercase small"><?= Helpers::sanitize($post['category_name']) ?></span>
                                <?php endif; ?>
                                <h2 class="h5 public-card-title mb-3"><?= Helpers::sanitize($post['title']) ?></h2>
                                <p class="public-card-meta mb-4 flex-grow-1"><?= Helpers::sanitize($summary) ?></p>
                                <div class="d-flex justify-content-between align-items-center text-muted small">
                                    <span><i class="bi bi-calendar-event me-2"></i><?= Helpers::sanitize(date('d.m.Y', strtotime($post['published_at'] ?: $post['created_at']))) ?></span>
                                    <a class="btn btn-sm btn-outline-light" href="/blog/<?= rawurlencode($post['slug']) ?>">Devamını oku</a>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="public-pagination mt-4">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php
                        $params = array('page' => $i);
                        if ($selectedCategory) {
                            $params['category'] = $selectedCategory['slug'];
                        }
                        $query = http_build_query($params);
                        ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="/blog/?<?= Helpers::sanitize($query) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <aside class="public-sidebar">
            <h5 class="mb-3">Kategoriler</h5>
            <ul class="list-unstyled mb-0">
                <?php foreach ($categories as $category): ?>
                    <li class="mb-2">
                        <a href="/blog/?category=<?= rawurlencode($category['slug']) ?>" class="d-flex justify-content-between align-items-center">
                            <span><?= Helpers::sanitize($category['name']) ?></span>
                            <span class="badge bg-secondary"><?= (int)$category['post_count'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>
    </div>
</div>
<?php
Helpers::includeTemplate('public-footer.php');
