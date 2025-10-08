<?php
$category = isset($category) && is_array($category) ? $category : null;
$products = isset($products) && is_array($products) ? $products : array();
$categories = isset($categories) && is_array($categories) ? $categories : array();
?>
<section class="category-heading mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <div>
            <h1 class="h3 mb-0"><?php echo htmlspecialchars($category['name'] ?? 'Tüm Ürünler', ENT_QUOTES, 'UTF-8'); ?></h1>
            <?php if (!empty($category['description'])): ?>
                <p class="text-muted mb-0 small"><?php echo htmlspecialchars($category['description'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
        <form method="get" class="d-flex gap-2">
            <input type="text" name="q" value="<?php echo isset($query) ? htmlspecialchars($query, ENT_QUOTES, 'UTF-8') : ''; ?>" class="form-control" placeholder="Ürün ara">
            <button type="submit" class="btn btn-primary">Ara</button>
        </form>
    </div>
</section>

<div class="row g-4">
    <aside class="col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Kategoriler</div>
            <div class="list-group list-group-flush">
                <a href="<?php echo htmlspecialchars(store_url('category.php'), ENT_QUOTES, 'UTF-8'); ?>" class="list-group-item list-group-item-action<?php echo $category ? '' : ' active'; ?>">Tüm Ürünler</a>
                <?php foreach ($categories as $cat): ?>
                    <?php
                    $href = store_url('category.php?id=' . (int) $cat['id']);
                    $active = $category && isset($category['id']) && (int) $category['id'] === (int) $cat['id'];
                    ?>
                    <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="list-group-item list-group-item-action<?php echo $active ? ' active' : ''; ?>">
                        <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>
    <section class="col-lg-9">
        <div class="row g-4 catalog-grid">
            <?php if ($products): ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-sm-6 col-md-4 d-flex">
                        <?php store_include(theme_partial('product-card'), array('product' => $product)); ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-warning">Bu kategoriye ait ürün bulunamadı.</div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
