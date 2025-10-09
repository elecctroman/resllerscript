<section class="checkout-view">
    <h1 class="h3 mb-4">Ödeme</h1>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Fatura Bilgileri</div>
                <div class="card-body">
                    <form class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="firstName">Ad</label>
                            <input type="text" class="form-control" id="firstName" placeholder="Adınız">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="lastName">Soyad</label>
                            <input type="text" class="form-control" id="lastName" placeholder="Soyadınız">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="email">E-posta</label>
                            <input type="email" class="form-control" id="email" placeholder="mail@ornek.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="note">Not</label>
                            <textarea class="form-control" id="note" rows="3" placeholder="Sipariş notu"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Siparişi tamamla</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Sipariş Özeti</div>
                <div class="card-body">
                    <ul class="list-unstyled mb-3">
                        <?php if (!empty($cart['items'])): ?>
                            <?php foreach ($cart['items'] as $item): ?>
                                <li class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span>₺<?php echo number_format(isset($item['price']) ? (float) $item['price'] : 0, 2, ',', '.'); ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="text-muted">Sepetiniz boş.</li>
                        <?php endif; ?>
                    </ul>
                    <div class="d-flex justify-content-between fw-semibold">
                        <span>Genel Toplam</span>
                        <span>₺<?php echo number_format(isset($cart['total']) ? (float) $cart['total'] : 0, 2, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
