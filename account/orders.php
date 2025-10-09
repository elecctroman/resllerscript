<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if (Auth::isAdminRole($user['role'] ?? 'reseller')) {
    Helpers::redirect('/admin/orders.php');
}

if (!Helpers::featureEnabled('orders')) {
    Helpers::setFlash('warning', 'Sipariş geçmişi şu anda görüntülenemiyor.');
    Helpers::redirect('/account/index.php');
}

$pdo = Database::connection();

$productOrders = array();
$packageOrders = array();
$errors = array();

try {
    $productStmt = $pdo->prepare('SELECT po.*, pr.name AS product_name, pr.sku AS product_sku, cat.name AS category_name FROM product_orders po INNER JOIN products pr ON po.product_id = pr.id INNER JOIN categories cat ON pr.category_id = cat.id WHERE po.user_id = :user_id ORDER BY po.created_at DESC');
    $productStmt->execute(array('user_id' => $user['id']));
    $productOrders = $productStmt->fetchAll();

    $packageStmt = $pdo->prepare('SELECT po.*, p.name AS package_name FROM package_orders po INNER JOIN packages p ON po.package_id = p.id WHERE po.email = :email ORDER BY po.created_at DESC');
    $packageStmt->execute(array('email' => $user['email']));
    $packageOrders = $packageStmt->fetchAll();
} catch (\PDOException $exception) {
    $errors[] = 'Sipariş kayıtları yüklenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
}

$pageTitle = 'Siparişlerim';
$pageDescription = 'Ürün ve paket siparişlerinizin geçmişini görüntüleyin, detayları inceleyin ve tekrar sipariş verin.';
$activeMenu = 'orders';

ob_start();
?>
<div class="account-section" data-csrf-token="<?= Helpers::csrfToken() ?>">
    <div class="account-section__header">
        <h5 class="account-section__title">Ürün Siparişleri</h5>
        <span class="text-muted small">Toplam: <?= count($productOrders) ?></span>
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
    <?php if ($productOrders): ?>
        <div class="table-responsive">
            <table class="table align-middle account-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Ürün</th>
                    <th>Kategori</th>
                    <th>Adet</th>
                    <th>Tutar</th>
                    <th>Kaynak</th>
                    <th>Durum</th>
                    <th>Tarih</th>
                    <th class="text-end">İşlem</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($productOrders as $order): ?>
                    <?php
                    $deliveryContent = '';
                    if (!empty($order['external_metadata'])) {
                        $metadata = json_decode($order['external_metadata'], true);
                        if (is_array($metadata)) {
                            if (!empty($metadata['delivery_content'])) {
                                $deliveryContent = (string)$metadata['delivery_content'];
                            } elseif (!empty($metadata['provider_response']['data']['content'])) {
                                $deliveryContent = (string)$metadata['provider_response']['data']['content'];
                            } elseif (!empty($metadata['provider_error']['message'])) {
                                $deliveryContent = (string)$metadata['provider_error']['message'];
                            }
                        }
                    }
                    $metaLabel = 'SKU: ' . (isset($order['product_sku']) ? $order['product_sku'] : '-');
                    ?>
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
                        <td><?= Helpers::formatCurrencyHtml((float)$order['price']) ?></td>
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
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#orderDetailModal"
                                    data-order-type="product"
                                    data-order-id="#<?= (int)$order['id'] ?>"
                                    data-order-title="<?= Helpers::sanitize($order['product_name']) ?>"
                                    data-order-category="<?= Helpers::sanitize($order['category_name']) ?>"
                                    data-order-quantity="<?= isset($order['quantity']) ? (int)$order['quantity'] : 1 ?>"
                                    data-order-price-html="<?= htmlspecialchars(Helpers::formatCurrencyHtml((float)$order['price']), ENT_QUOTES, 'UTF-8') ?>"
                                    data-order-price-base-amount="<?= Helpers::sanitize(number_format((float)$order['price'], 6, '.', '')) ?>"
                                    data-order-price-base-currency="TRY"
                                    data-order-source="<?= Helpers::sanitize(strtoupper(isset($order['source']) ? $order['source'] : 'panel')) ?>"
                                    data-order-reference="<?= Helpers::sanitize(isset($order['external_reference']) ? $order['external_reference'] : '') ?>"
                                    data-order-status="<?= Helpers::sanitize(strtoupper($order['status'])) ?>"
                                    data-order-status-class="<?= Helpers::sanitize($order['status']) ?>"
                                    data-order-created="<?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>"
                                    data-order-note="<?= Helpers::sanitize(isset($order['note']) ? $order['note'] : '') ?>"
                                    data-order-admin-note="<?= Helpers::sanitize(isset($order['admin_note']) ? $order['admin_note'] : '') ?>"
                                    data-order-delivery="<?= Helpers::sanitize($deliveryContent) ?>"
                                    data-order-meta="<?= Helpers::sanitize($metaLabel) ?>">
                                Görüntüle
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success ms-2 js-repeat-order" data-order-id="<?= (int)$order['id'] ?>" title="Tekrar sipariş ver">
                                <i class="ri-arrow-go-forward-line"></i>
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

<div class="account-section mt-5">
    <div class="account-section__header">
        <h5 class="account-section__title">Paket Siparişleri</h5>
        <span class="text-muted small">Toplam: <?= count($packageOrders) ?></span>
    </div>
    <?php if ($packageOrders): ?>
        <div class="table-responsive">
            <table class="table align-middle account-table">
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
                        </td>
                        <td><?= Helpers::formatCurrencyHtml((float)$order['total_amount']) ?></td>
                        <td><span class="badge-status <?= Helpers::sanitize($order['status']) ?>"><?= strtoupper(Helpers::sanitize($order['status'])) ?></span></td>
                        <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#orderDetailModal"
                                    data-order-type="package"
                                    data-order-id="#<?= (int)$order['id'] ?>"
                                    data-order-title="<?= Helpers::sanitize($order['package_name']) ?>"
                                    data-order-price-html="<?= htmlspecialchars(Helpers::formatCurrencyHtml((float)$order['total_amount']), ENT_QUOTES, 'UTF-8') ?>"
                                    data-order-price-base-amount="<?= Helpers::sanitize(number_format((float)$order['total_amount'], 6, '.', '')) ?>"
                                    data-order-price-base-currency="TRY"
                                    data-order-status="<?= Helpers::sanitize(strtoupper($order['status'])) ?>"
                                    data-order-status-class="<?= Helpers::sanitize($order['status']) ?>"
                                    data-order-created="<?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>"
                                    data-order-email="<?= Helpers::sanitize($order['email']) ?>"
                                    data-order-summary="<?= Helpers::sanitize($order['name']) ?>">
                                Detay
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted mb-0">Henüz paket başvurunuz bulunmuyor.</p>
    <?php endif; ?>
</div>

<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sipariş Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0" id="orderDetailTitle"></h6>
                        <span class="badge" id="orderDetailStatus"></span>
                    </div>
                    <small class="text-muted" id="orderDetailId"></small>
                </div>
                <ul class="list-unstyled d-none" id="orderDetailSummary"></ul>
                <div class="mb-3 d-none" id="orderDetailDeliverySection">
                    <h6 class="fw-semibold">Teslimat Bilgisi</h6>
                    <pre class="bg-light rounded border p-3" id="orderDetailDelivery"></pre>
                </div>
                <div class="mb-3 d-none" id="orderDetailNoteSection">
                    <h6 class="fw-semibold">Notunuz</h6>
                    <p class="mb-0" id="orderDetailNote"></p>
                </div>
                <div class="mb-3 d-none" id="orderDetailAdminNoteSection">
                    <h6 class="fw-semibold">Yönetici Notu</h6>
                    <p class="mb-0" id="orderDetailAdminNote"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

$GLOBALS['pageInlineScripts'][] = <<<'JS'
(function () {
    var modal = document.getElementById('orderDetailModal');
    if (!modal) { return; }
    modal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (!trigger) { return; }

        var title = trigger.getAttribute('data-order-title') || '';
        var category = trigger.getAttribute('data-order-category') || '';
        var quantity = trigger.getAttribute('data-order-quantity') || '';
        var priceHtml = trigger.getAttribute('data-order-price-html') || '';
        var status = trigger.getAttribute('data-order-status') || '';
        var statusClass = trigger.getAttribute('data-order-status-class') || '';
        var orderId = trigger.getAttribute('data-order-id') || '';
        var summary = trigger.getAttribute('data-order-summary') || '';
        var source = trigger.getAttribute('data-order-source') || '';
        var reference = trigger.getAttribute('data-order-reference') || '';
        var created = trigger.getAttribute('data-order-created') || '';
        var delivery = trigger.getAttribute('data-order-delivery') || '';
        var note = trigger.getAttribute('data-order-note') || '';
        var adminNote = trigger.getAttribute('data-order-admin-note') || '';
        var email = trigger.getAttribute('data-order-email') || '';
        var meta = trigger.getAttribute('data-order-meta') || '';

        var elements = {
            title: modal.querySelector('#orderDetailTitle'),
            status: modal.querySelector('#orderDetailStatus'),
            id: modal.querySelector('#orderDetailId'),
            summary: modal.querySelector('#orderDetailSummary'),
            summarySection: modal.querySelector('#orderDetailSummary'),
            delivery: modal.querySelector('#orderDetailDelivery'),
            deliverySection: modal.querySelector('#orderDetailDeliverySection'),
            note: modal.querySelector('#orderDetailNote'),
            noteSection: modal.querySelector('#orderDetailNoteSection'),
            adminNote: modal.querySelector('#orderDetailAdminNote'),
            adminNoteSection: modal.querySelector('#orderDetailAdminNoteSection')
        };

        elements.title.textContent = title;
        elements.status.textContent = status;
        elements.status.className = 'badge badge-status ' + statusClass.toLowerCase();
        elements.id.textContent = orderId;
        elements.summary.innerHTML = '';
        elements.summarySection.classList.add('d-none');

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
        if (summary) {
            summaryItems.push({ label: 'Başvuru Sahibi', value: summary });
        }
        if (email) {
            summaryItems.push({ label: 'E-posta', value: email });
        }
        if (priceHtml) {
            summaryItems.push({ label: 'Tutar', value: priceHtml, isHtml: true });
        }
        if (created) {
            summaryItems.push({ label: 'Oluşturma', value: created });
        }
        if (meta) {
            summaryItems.push({ label: 'Bilgi', value: meta });
        }

        if (summaryItems.length > 0) {
            summaryItems.forEach(function (item) {
                var li = document.createElement('li');
                li.className = 'd-flex justify-content-between align-items-center gap-2 py-2 border-bottom';

                var labelSpan = document.createElement('span');
                labelSpan.className = 'text-muted';
                labelSpan.textContent = item.label;

                var valueSpan = document.createElement('span');
                valueSpan.className = 'fw-semibold text-end';
                if (item.isCode) {
                    var code = document.createElement('code');
                    code.textContent = item.value;
                    valueSpan.appendChild(code);
                } else if (item.isHtml) {
                    valueSpan.innerHTML = item.value;
                } else {
                    valueSpan.textContent = item.value;
                }

                li.appendChild(labelSpan);
                li.appendChild(valueSpan);
                elements.summary.appendChild(li);
            });

            elements.summarySection.classList.remove('d-none');
        }

        if (delivery) {
            elements.delivery.textContent = delivery;
            elements.deliverySection.classList.remove('d-none');
        } else {
            elements.delivery.textContent = '';
            elements.deliverySection.classList.add('d-none');
        }

        if (note) {
            elements.note.textContent = note;
            elements.noteSection.classList.remove('d-none');
        } else {
            elements.note.textContent = '';
            elements.noteSection.classList.add('d-none');
        }

        if (adminNote) {
            elements.adminNote.textContent = adminNote;
            elements.adminNoteSection.classList.remove('d-none');
        } else {
            elements.adminNote.textContent = '';
            elements.adminNoteSection.classList.add('d-none');
        }
    });
})();
JS;

$GLOBALS['pageInlineScripts'][] = <<<'JS'
(function () {
    var container = document.querySelector('.account-section[data-csrf-token]');
    if (!container) { return; }
    var csrfToken = container.getAttribute('data-csrf-token') || '';

    document.querySelectorAll('.js-repeat-order').forEach(function (button) {
        button.addEventListener('click', function () {
            var orderId = button.getAttribute('data-order-id');
            if (!orderId) { return; }

            button.disabled = true;
            var formData = new URLSearchParams();
            formData.append('action', 'repeat_order');
            formData.append('csrf_token', csrfToken);
            formData.append('order_id', orderId);

            fetch('/reseller-actions.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('İstek tamamlanamadı.');
                }
                return response.json();
            }).then(function (data) {
                var message = data && data.message ? data.message : 'Siparişiniz başarıyla tekrarlandı.';
                alert(message);
            }).catch(function (error) {
                alert(error && error.message ? error.message : 'İşlem tamamlanamadı.');
            }).finally(function () {
                button.disabled = false;
            });
        });
    });
})();
JS;

require __DIR__ . '/../themes/store/default/account/layout.php';
