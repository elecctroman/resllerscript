<?php
$orders = isset($orders) && is_array($orders) ? $orders : array();
?>
<section class="py-5">
    <div class="container-xxl">
        <div class="card card-panel border-0">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 fw-semibold mb-1">Siparişlerim</h1>
                        <p class="text-muted mb-0">Mağazadan satın aldığınız ürünlerin ve lisansların geçmişini görüntüleyin.</p>
                    </div>
                    <a class="btn btn-primary mt-3 mt-md-0" href="<?php echo htmlspecialchars(store_url(''), ENT_QUOTES, 'UTF-8'); ?>">Alışverişe Devam Et</a>
                </div>

                <?php if ($orders): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-borderless align-middle mb-0">
                            <thead>
                                <tr class="text-muted small">
                                    <th scope="col">Sipariş #</th>
                                    <th scope="col">Ürün</th>
                                    <th scope="col">Durum</th>
                                    <th scope="col">Oluşturma</th>
                                    <th scope="col" class="text-end">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td class="fw-semibold">#<?php echo (int) $order['id']; ?></td>
                                        <td><?php echo htmlspecialchars('Modül #' . (int) $order['module_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <span class="badge rounded-pill <?php echo ($order['payment_status'] === 'paid') ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'; ?> px-3 py-2">
                                                <?php echo htmlspecialchars(strtoupper((string) $order['payment_status']), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        <?php
                                        $createdAt = isset($order['created_at']) ? (string) $order['created_at'] : '';
                                        $createdDisplay = $createdAt !== '' ? date('d.m.Y H:i', strtotime($createdAt)) : '-';
                                        ?>
                                        <td class="text-muted small"><?php echo htmlspecialchars($createdDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end">
                                            <?php if (!empty($order['license_key'])): ?>
                                                <button class="btn btn-outline btn-sm" type="button" data-license="<?php echo htmlspecialchars((string) $order['license_key'], ENT_QUOTES, 'UTF-8'); ?>">Lisansı Kopyala</button>
                                            <?php else: ?>
                                                <span class="text-muted small">Hazırlanıyor</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state text-center py-5">
                        <div class="display-6 fw-bold text-accent mb-3">Henüz sipariş yok</div>
                        <p class="text-muted mb-4">Satın aldığınız ürünler burada listelenecek. Şimdi katalogdan bir ürün seçerek başlayın.</p>
                        <a class="btn btn-primary btn-lg" href="<?php echo htmlspecialchars(store_url(''), ENT_QUOTES, 'UTF-8'); ?>">Ürünleri Keşfet</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
