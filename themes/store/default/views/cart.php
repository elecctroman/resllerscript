<?php
use App\Helpers;

$items = isset($cart['items']) && is_array($cart['items']) ? $cart['items'] : array();
$total = isset($cart['total']) ? (float) $cart['total'] : 0.0;
$csrfToken = Helpers::csrfToken();
$flashData = isset($flash) && is_array($flash) ? $flash : null;
?>
<section class="cart-view">
    <h1 class="h3 mb-4">Sepetiniz</h1>

    <?php if ($flashData): ?>
        <div class="alert alert-<?= htmlspecialchars($flashData['type'] === 'error' ? 'danger' : 'success', ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashData['message'], ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    <?php endif; ?>

    <?php if ($items): ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="table-responsive shadow-sm border rounded-4 overflow-hidden">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th scope="col">Ürün</th>
                            <th scope="col" class="text-center">Adet</th>
                            <th scope="col" class="text-end">Birim Fiyat</th>
                            <th scope="col" class="text-end">Ara Toplam</th>
                            <th scope="col" class="text-end">İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $quantity = isset($item['quantity']) ? max(1, (int) $item['quantity']) : 1;
                            $unitPrice = isset($item['price']) ? (float) $item['price'] : 0.0;
                            $lineTotal = isset($item['line_total']) ? (float) $item['line_total'] : ($unitPrice * $quantity);
                            $imagePath = isset($item['image']) && $item['image'] !== ''
                                ? (preg_match('/^https?:/i', (string) $item['image']) ? (string) $item['image'] : store_url(ltrim((string) $item['image'], '/')))
                                : theme_asset('img/placeholder-16x9.svg');
                            ?>
                            <tr data-cart-row="<?= (int) ($item['product_id'] ?? 0) ?>">
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="rounded-3 object-fit-cover" width="80" height="56">
                                        <div>
                                            <a href="<?= htmlspecialchars($item['url'] ?? store_url('product/' . (int) ($item['product_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" class="fw-semibold text-decoration-none text-dark">
                                                <?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <?php if (!empty($item['category_name'])): ?>
                                                <div class="text-muted small">Kategori: <?= htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($item['sku'])): ?>
                                                <div class="text-muted small">SKU: <?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <form method="post" action="<?= htmlspecialchars(store_url('cart/update'), ENT_QUOTES, 'UTF-8') ?>" data-cart-update>
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="product_id" value="<?= (int) ($item['product_id'] ?? 0) ?>">
                                        <div class="input-group input-group-sm cart-qty-group">
                                            <button class="btn btn-outline-secondary" type="button" data-cart-decrement aria-label="Adet azalt">−</button>
                                            <input type="number" class="form-control text-center" name="quantity" value="<?= $quantity ?>" min="1">
                                            <button class="btn btn-outline-secondary" type="button" data-cart-increment aria-label="Adet artır">+</button>
                                        </div>
                                    </form>
                                </td>
                                <td class="text-end text-nowrap"><?= htmlspecialchars(money_format_try($unitPrice), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end text-nowrap fw-semibold"><?= htmlspecialchars(money_format_try($lineTotal), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button class="btn btn-outline-primary btn-sm" type="submit" formnovalidate data-cart-update-submit>Güncelle</button>
                                        <form method="post" action="<?= htmlspecialchars(store_url('cart/remove'), ENT_QUOTES, 'UTF-8') ?>" data-cart-remove>
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="product_id" value="<?= (int) ($item['product_id'] ?? 0) ?>">
                                            <button type="submit" class="btn btn-link text-danger p-0">Sil</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body">
                        <h2 class="h5 fw-semibold mb-3">Sepet Özeti</h2>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Ara Toplam</span>
                            <span class="fw-semibold"><?= htmlspecialchars(money_format_try($total), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <form class="cart-coupon-form mt-3" action="#" method="post" data-cart-coupon>
                            <label class="form-label small text-muted">Kupon Kodu</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" placeholder="Kupon kodu">
                                <button class="btn btn-outline-secondary" type="button" disabled>Uygula</button>
                            </div>
                            <div class="form-text">Kupon desteği yakında eklenecek.</div>
                        </form>
                        <hr class="my-4">
                        <a href="<?= htmlspecialchars(store_url('checkout'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary w-100 mb-2">Ödemeye Devam Et</a>
                        <a href="<?= htmlspecialchars(store_url(''), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary w-100">Alışverişe Devam Et</a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <p class="text-muted mb-4">Sepetinizde ürün bulunmuyor.</p>
                <a href="<?= htmlspecialchars(store_url(''), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Ürünleri incele</a>
            </div>
        </div>
    <?php endif; ?>
</section>
