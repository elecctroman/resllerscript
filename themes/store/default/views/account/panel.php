<?php
/** @var array $user */
/** @var array $profile */
/** @var array $errors */
/** @var array $success */
/** @var float $balance */
/** @var string $csrf_token */
/** @var string $logout_token */

$firstName = isset($profile['first_name']) ? (string) $profile['first_name'] : '';
$lastName  = isset($profile['last_name']) ? (string) $profile['last_name'] : '';
$phone     = isset($profile['phone']) ? (string) $profile['phone'] : '';
$country   = isset($profile['country']) ? (string) $profile['country'] : '';
$city      = isset($profile['city']) ? (string) $profile['city'] : '';
$district  = isset($profile['district']) ? (string) $profile['district'] : '';
$address   = isset($profile['address']) ? (string) $profile['address'] : '';

$menuItems = array(
    array('label' => 'Kullanıcı Bilgilerim', 'href' => store_url('panel/index'), 'icon' => 'bi-person'),
    array('label' => 'Siparişlerim', 'href' => store_url('panel/orders'), 'icon' => 'bi-bag-check'),
    array('label' => 'Destek Taleplerim', 'href' => store_url('panel/tickets'), 'icon' => 'bi-life-preserver'),
    array('label' => 'Şifre Değişikliği', 'href' => store_url('panel/pass'), 'icon' => 'bi-shield-lock'),
);

$currentPath = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
?>
<section class="account-panel">
    <div class="account-panel__grid">
        <aside class="account-panel__sidebar">
            <div class="account-profile-card">
                <div class="account-profile-card__header">
                    <span class="account-profile-card__title">Profilim</span>
                    <span class="account-profile-card__icon"><i class="bi bi-gear"></i></span>
                </div>
                <div class="account-profile-card__body">
                    <div class="account-profile-card__avatar" aria-hidden="true">
                        <span><?= htmlspecialchars(mb_substr($user['name'] ?? $user['email'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="account-profile-card__name">
                        <?= htmlspecialchars($user['name'] !== '' ? $user['name'] : $user['email'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="account-profile-card__balance">
                        <span>Bakiye</span>
                        <strong><?= htmlspecialchars(money_format_try($balance), ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                </div>
            </div>

            <nav class="account-menu" aria-label="Hesap menüsü">
                <div class="account-menu__heading">
                    <span>Menü</span>
                    <i class="bi bi-list"></i>
                </div>
                <ul class="account-menu__list">
                    <?php foreach ($menuItems as $item): ?>
                        <?php $active = strpos($currentPath, parse_url($item['href'], PHP_URL_PATH)) === 0; ?>
                        <li class="account-menu__item<?= $active ? ' is-active' : '' ?>">
                            <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li class="account-menu__item account-menu__item--logout">
                        <form method="post" action="<?= htmlspecialchars(store_url('account/logout'), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($logout_token, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit">
                                <i class="bi bi-box-arrow-right"></i>
                                Çıkış Yap
                            </button>
                        </form>
                    </li>
                </ul>
            </nav>
        </aside>

        <div class="account-panel__content">
            <div class="account-panel__section">
                <div class="account-panel__section-head">
                    <div class="account-panel__section-icon"><i class="bi bi-person"></i></div>
                    <div>
                        <h1 class="account-panel__section-title">Kullanıcı Bilgilerim</h1>
                        <p class="account-panel__section-sub">Bilgilerinizi güncel tutun, sipariş ve destek süreçleri hızlı ilerlesin.</p>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" role="status">
                        <?php foreach ($success as $message): ?>
                            <div><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $message): ?>
                                <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="account-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label" for="first_name">İsim</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="last_name">Soyisim</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">E-Posta</label>
                            <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Telefon</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" placeholder="05xx xxx xx xx">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="country">Ülke</label>
                            <input type="text" class="form-control" id="country" name="country" value="<?= htmlspecialchars($country, ENT_QUOTES, 'UTF-8') ?>" placeholder="Türkiye">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="city">Şehir</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?= htmlspecialchars($city, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="district">İlçe</label>
                            <input type="text" class="form-control" id="district" name="district" value="<?= htmlspecialchars($district, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Adres</label>
                            <textarea class="form-control" id="address" name="address" rows="4" placeholder="Açık adresinizi girin"><?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    </div>
                    <div class="account-form__actions">
                        <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet <i class="bi bi-arrow-right"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
