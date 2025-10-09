<?php
use App\Auth;

$user = isset($user) && is_array($user) ? $user : array();
$roles = isset($user['roles']) && is_array($user['roles']) ? $user['roles'] : array();
$balance = isset($user['balance']) ? (float) $user['balance'] : 0.0;
$roleLabels = array(
    'customer' => 'Müşteri',
    'reseller' => 'Bayi',
    'admin' => 'Yönetici',
);
?>
<section class="py-5">
    <div class="container-xxl">
        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="card card-panel border-0 h-100">
                    <div class="card-body p-4 p-lg-5">
                        <h1 class="h3 fw-semibold mb-3">Hesap Bilgileri</h1>
                        <p class="text-muted mb-4">Mağaza profilinizden iletişim bilgilerinizi güncel tutun ve siparişlerinizi takip edin.</p>

                        <dl class="row text-muted small mb-0">
                            <dt class="col-4 col-md-3">Ad Soyad</dt>
                            <dd class="col-8 col-md-9 fw-semibold text-white"><?php echo htmlspecialchars(isset($user['name']) ? (string) $user['name'] : '', ENT_QUOTES, 'UTF-8'); ?></dd>

                            <dt class="col-4 col-md-3">E-posta</dt>
                            <dd class="col-8 col-md-9 fw-semibold text-white"><?php echo htmlspecialchars(isset($user['email']) ? (string) $user['email'] : '', ENT_QUOTES, 'UTF-8'); ?></dd>

                            <dt class="col-4 col-md-3">Roller</dt>
                            <dd class="col-8 col-md-9">
                                <?php if ($roles): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($roles as $role): ?>
                                            <?php $label = isset($roleLabels[$role]) ? $roleLabels[$role] : ucfirst($role); ?>
                                            <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis px-3 py-2"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Tanımlı rol bulunmuyor.</span>
                                <?php endif; ?>
                            </dd>

                            <dt class="col-4 col-md-3">Bakiye</dt>
                            <dd class="col-8 col-md-9 fw-semibold text-info">₺<?php echo number_format($balance, 2, ',', '.'); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card card-panel border-0">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-semibold mb-3">Hızlı Bağlantılar</h2>
                        <div class="d-grid gap-2">
                            <a class="btn btn-primary" href="<?php echo htmlspecialchars(store_url('account/orders'), ENT_QUOTES, 'UTF-8'); ?>">Siparişlerim</a>
                            <?php if (Auth::hasRole('reseller')): ?>
                                <a class="btn btn-outline" href="<?php echo htmlspecialchars(reseller_base_url(), ENT_QUOTES, 'UTF-8'); ?>">Bayi Paneli</a>
                            <?php endif; ?>
                            <?php if (Auth::hasRole('admin')): ?>
                                <a class="btn btn-outline" href="<?php echo htmlspecialchars(admin_base_url(), ENT_QUOTES, 'UTF-8'); ?>">Admin Paneli</a>
                            <?php endif; ?>
                            <a class="btn btn-outline" href="<?php echo htmlspecialchars(store_url('account/orders'), ENT_QUOTES, 'UTF-8'); ?>#destek">Destek Taleplerim</a>
                        </div>
                        <p class="text-muted small mt-3 mb-0">Rollerinize göre ilgili yönetim panellerine erişebilirsiniz.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
