<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if (Auth::isAdminRole($user['role'])) {
    Helpers::redirect('/admin/orders.php');
}

$pageTitle = 'Siparişlerim';

if (!Helpers::featureEnabled('orders')) {
    Helpers::setFlash('warning', 'Sipariş geçmişi şu anda görüntülenemiyor.');
    Helpers::redirect('/dashboard.php');
}

$pdo = Database::connection();

$productOrders = [];
$packageOrders = [];
$errors = [];

try {
    $productStmt = $pdo->prepare('SELECT po.*, pr.name AS product_name, pr.sku AS product_sku, cat.name AS category_name FROM product_orders po INNER JOIN products pr ON po.product_id = pr.id INNER JOIN categories cat ON pr.category_id = cat.id WHERE po.user_id = :user_id ORDER BY po.created_at DESC');
    $productStmt->execute(['user_id' => $user['id']]);
    $productOrders = $productStmt->fetchAll();

    $packageStmt = $pdo->prepare('SELECT po.*, p.name AS package_name FROM package_orders po INNER JOIN packages p ON po.package_id = p.id WHERE po.email = :email ORDER BY po.created_at DESC');
    $packageStmt->execute(['email' => $user['email']]);
    $packageOrders = $packageStmt->fetchAll();
} catch (\PDOException $exception) {
    $errors[] = 'Sipariş kayıtları yüklenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
}

include __DIR__ . '/templates/header.php';
?>
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
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Ürün Siparişleri</h5>
                <span class="text-muted small">Toplam: <?= count($productOrders) ?></span>
            </div>
            <div class="card-body">
                <?php if ($productOrders): ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-striped">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Ürün</th>
                                <th>Kategori</th>
                                <th>Adet</th>
                                <th>Ödenen Tutar</th>
                                <th>Kaynak</th>
                                <th>Durum</th>
                                <th>Tarih</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($productOrders as $order): ?>
                                <tr>
                                    <td><?= (int)$order['id'] ?></td>
                                    <td>
                                        <strong><?= Helpers::sanitize($order['product_name']) ?></strong>
                                        <div class="text-muted small">SKU: <?= Helpers::sanitize(isset($order['product_sku']) ? $order['product_sku'] : '-') ?></div>
                                        <?php if (!empty($order['note'])): ?>
                                            <div class="text-muted small mt-1">Bayi Notu: <?= Helpers::sanitize($order['note']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($order['admin_note'])): ?>
                                            <div class="text-muted small">Yönetici Notu: <?= Helpers::sanitize($order['admin_note']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= Helpers::sanitize($order['category_name']) ?></td>
                                    <td><?= isset($order['quantity']) ? (int)$order['quantity'] : 1 ?></td>
                                    <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$order['price'])) ?></td>
                                    <td>
                                        <?php
                                        $source = isset($order['source']) ? $order['source'] : 'panel';
                                        echo '<span class="badge bg-light text-dark">' . Helpers::sanitize(strtoupper($source)) . '</span>';
                                        if (!empty($order['external_reference'])) {
                                            echo '<div class="small text-muted mt-1">Ref: ' . Helpers::sanitize($order['external_reference']) . '</div>';
                                        }
                                        ?>
                                    </td>
                                    <td><span class="badge-status <?= Helpers::sanitize($order['status']) ?>"><?= strtoupper(Helpers::sanitize($order['status'])) ?></span></td>
                                    <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                    <td class="text-end">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#orderDetailModal"
                                                data-order-type="product"
                                                data-order-id="#<?= (int)$order['id'] ?>"
                                                data-order-title="<?= Helpers::sanitize($order['product_name']) ?>"
                                                data-order-category="<?= Helpers::sanitize($order['category_name']) ?>"
                                                data-order-quantity="<?= isset($order['quantity']) ? (int)$order['quantity'] : 1 ?>"
                                                data-order-price="<?= Helpers::sanitize(Helpers::formatCurrency((float)$order['price'])) ?>"
                                                data-order-source="<?= Helpers::sanitize(strtoupper(isset($order['source']) ? $order['source'] : 'panel')) ?>"
                                                data-order-reference="<?= Helpers::sanitize(isset($order['external_reference']) ? $order['external_reference'] : '') ?>"
                                                data-order-status="<?= Helpers::sanitize(strtoupper($order['status'])) ?>"
                                                data-order-status-class="<?= Helpers::sanitize($order['status']) ?>"
                                                data-order-created="<?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>"
                                                data-order-note="<?= Helpers::sanitize(isset($order['note']) ? $order['note'] : '') ?>"
                                                data-order-admin-note="<?= Helpers::sanitize(isset($order['admin_note']) ? $order['admin_note'] : '') ?>"
                                                data-order-meta="SKU: <?= Helpers::sanitize(isset($order['product_sku']) ? $order['product_sku'] : '-') ?>">
                                            Görüntüle
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Henüz ürün siparişi oluşturmadınız.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Paket Siparişleri</h5>
                <a href="/register.php" class="btn btn-sm btn-outline-primary">Yeni Paket Talebi</a>
            </div>
            <div class="card-body">
                <?php if ($packageOrders): ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-striped">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Paket</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th>Tarih</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($packageOrders as $order): ?>
                                <tr>
                                    <td><?= (int)$order['id'] ?></td>
                                    <td>
                                        <strong><?= Helpers::sanitize($order['package_name']) ?></strong>
                                        <?php if (!empty($order['notes'])): ?>
                                            <div class="text-muted small mt-1">Not: <?= Helpers::sanitize($order['notes']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($order['admin_note'])): ?>
                                            <div class="text-muted small">Yönetici Notu: <?= Helpers::sanitize($order['admin_note']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$order['total_amount'])) ?></td>
                                    <td><span class="badge-status <?= Helpers::sanitize($order['status']) ?>"><?= strtoupper(Helpers::sanitize($order['status'])) ?></span></td>
                                    <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                    <td class="text-end">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#orderDetailModal"
                                                data-order-type="package"
                                                data-order-id="#<?= (int)$order['id'] ?>"
                                                data-order-title="<?= Helpers::sanitize($order['package_name']) ?>"
                                                data-order-price="<?= Helpers::sanitize(Helpers::formatCurrency((float)$order['total_amount'])) ?>"
                                                data-order-status="<?= Helpers::sanitize(strtoupper($order['status'])) ?>"
                                                data-order-status-class="<?= Helpers::sanitize($order['status']) ?>"
                                                data-order-created="<?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>"
                                                data-order-note="<?= Helpers::sanitize(isset($order['notes']) ? $order['notes'] : '') ?>"
                                                data-order-admin-note="<?= Helpers::sanitize(isset($order['admin_note']) ? $order['admin_note'] : '') ?>"
                                                data-order-meta="Başvuru E-postası: <?= Helpers::sanitize($order['email']) ?>"
                                                data-order-email="<?= Helpers::sanitize($order['email']) ?>">
                                            Görüntüle
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Henüz paket siparişi oluşturmadınız.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title order-detail-title">Sipariş Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                    <div>
                        <div class="small text-muted">Sipariş No</div>
                        <div class="fw-semibold" data-detail-field="id"></div>
                    </div>
                    <div class="text-md-end">
                        <div class="small text-muted">Oluşturulma</div>
                        <div class="fw-semibold" data-detail-field="created"></div>
                    </div>
                </div>

                <div class="mt-3">
                    <h6 class="mb-1" data-detail-field="title"></h6>
                    <div class="text-muted small" data-detail-field="meta"></div>
                </div>

                <div class="mt-3">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Durum</dt>
                        <dd class="col-sm-8"><span class="badge badge-status" data-detail-field="status"></span></dd>
                        <dt class="col-sm-4">Tutar</dt>
                        <dd class="col-sm-8" data-detail-field="price"></dd>
                    </dl>
                </div>

                <div class="mt-3 d-none" data-detail-section="summary">
                    <h6 class="small text-uppercase text-muted mb-2">Özet</h6>
                    <ul class="list-unstyled mb-0" data-detail-field="summary"></ul>
                </div>

                <div class="mt-3 d-none" data-detail-section="note">
                    <h6 class="mb-1">Bayi Notu</h6>
                    <p class="mb-0" data-detail-field="note"></p>
                </div>

                <div class="mt-3 d-none" data-detail-section="admin-note">
                    <div class="alert alert-warning mb-0">
                        <h6 class="alert-heading mb-1">Yönetici Notu</h6>
                        <p class="mb-0" data-detail-field="admin-note"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>
<?php
$GLOBALS['pageInlineScripts'][] = <<<'JS'
(function () {
    var modalEl = document.getElementById('orderDetailModal');
    if (!modalEl) {
        return;
    }

    var fields = {
        id: modalEl.querySelector('[data-detail-field="id"]'),
        created: modalEl.querySelector('[data-detail-field="created"]'),
        title: modalEl.querySelector('[data-detail-field="title"]'),
        meta: modalEl.querySelector('[data-detail-field="meta"]'),
        status: modalEl.querySelector('[data-detail-field="status"]'),
        price: modalEl.querySelector('[data-detail-field="price"]'),
        summary: modalEl.querySelector('[data-detail-field="summary"]'),
        note: modalEl.querySelector('[data-detail-field="note"]'),
        adminNote: modalEl.querySelector('[data-detail-field="admin-note"]')
    };

    var sections = {
        summary: modalEl.querySelector('[data-detail-section="summary"]'),
        note: modalEl.querySelector('[data-detail-section="note"]'),
        adminNote: modalEl.querySelector('[data-detail-section="admin-note"]')
    };

    modalEl.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (!trigger) {
            return;
        }

        var type = trigger.getAttribute('data-order-type') || 'order';
        var orderId = trigger.getAttribute('data-order-id') || '';
        var created = trigger.getAttribute('data-order-created') || '';
        var title = trigger.getAttribute('data-order-title') || 'Sipariş Detayı';
        var meta = trigger.getAttribute('data-order-meta') || '';
        var status = trigger.getAttribute('data-order-status') || '';
        var statusClass = trigger.getAttribute('data-order-status-class') || '';
        var price = trigger.getAttribute('data-order-price') || '';
        var quantity = trigger.getAttribute('data-order-quantity') || '';
        var category = trigger.getAttribute('data-order-category') || '';
        var source = trigger.getAttribute('data-order-source') || '';
        var reference = trigger.getAttribute('data-order-reference') || '';
        var note = trigger.getAttribute('data-order-note') || '';
        var adminNote = trigger.getAttribute('data-order-admin-note') || '';

        fields.id.textContent = orderId;
        fields.created.textContent = created;
        fields.title.textContent = title;
        fields.meta.textContent = meta;
        fields.price.textContent = price !== '' ? price : '-';

        fields.status.textContent = status !== '' ? status : '-';
        fields.status.className = 'badge-status ' + statusClass;

        sections.summary.classList.add('d-none');
        fields.summary.innerHTML = '';

        var summaryItems = [];
        if (quantity) {
            summaryItems.push({ label: 'Adet', value: quantity });
        }
        if (category) {
            summaryItems.push({ label: 'Kategori', value: category });
        }
        if (source) {
            summaryItems.push({ label: 'Kaynak', value: source.toUpperCase() });
        }
        if (reference) {
            summaryItems.push({ label: 'Referans', value: reference, isCode: true });
        }
        if (type === 'package') {
            var applicantEmail = trigger.getAttribute('data-order-email') || '';
            if (applicantEmail) {
                summaryItems.push({ label: 'Başvuru E-postası', value: applicantEmail });
            }
        }

        if (summaryItems.length > 0) {
            summaryItems.forEach(function (item) {
                var li = document.createElement('li');
                li.className = 'd-flex justify-content-between align-items-center gap-2';

                var labelSpan = document.createElement('span');
                labelSpan.className = 'text-muted';
                labelSpan.textContent = item.label;

                var valueSpan = document.createElement('span');
                valueSpan.className = 'fw-semibold text-end';
                if (item.isCode) {
                    var code = document.createElement('code');
                    code.textContent = item.value;
                    valueSpan.appendChild(code);
                } else {
                    valueSpan.textContent = item.value;
                }

                li.appendChild(labelSpan);
                li.appendChild(valueSpan);
                fields.summary.appendChild(li);
            });

            sections.summary.classList.remove('d-none');
        }

        if (note) {
            fields.note.textContent = note;
            sections.note.classList.remove('d-none');
        } else {
            fields.note.textContent = '';
            sections.note.classList.add('d-none');
        }

        if (adminNote) {
            fields.adminNote.textContent = adminNote;
            sections.adminNote.classList.remove('d-none');
        } else {
            fields.adminNote.textContent = '';
            sections.adminNote.classList.add('d-none');
        }
    });
})();
JS;

include __DIR__ . '/templates/footer.php';
