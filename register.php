<?php
require __DIR__ . '/bootstrap.php';

use App\Database;
use App\Helpers;
use App\Mailer;
use App\Settings;
use App\Payments\PaymentGatewayManager;

if (!empty($_SESSION['user'])) {
    Helpers::redirect('/dashboard.php');
}

$pdo = Database::connection();
$packages = $pdo->query('SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC')->fetchAll();
$errors = [];
$selectedPackage = null;
$paymentTestMode = Settings::get('payment_test_mode') === '1';
$gateways = PaymentGatewayManager::getActiveGateways();
$hasLiveGateway = !empty($gateways);
$defaultGateway = null;
if ($hasLiveGateway) {
    foreach ($gateways as $identifier => $info) {
        $defaultGateway = $identifier;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageId = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $company = isset($_POST['company']) ? trim($_POST['company']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if (!$packageId) {
        $errors[] = 'Lütfen bir paket seçin.';
    }

    if (!$name || !$email) {
        $errors[] = 'Ad soyad ve e-posta alanları zorunludur.';
    }

    $selectedPackage = null;
    foreach ($packages as $package) {
        if ((int)$package['id'] === $packageId) {
            $selectedPackage = $package;
            break;
        }
    }

    if (!$selectedPackage) {
        $errors[] = 'Seçilen paket bulunamadı veya aktif değil.';
    }

    $selectedGateway = isset($_POST['payment_provider']) ? trim($_POST['payment_provider']) : '';
    if ($selectedGateway === '' && $hasLiveGateway) {
        $selectedGateway = $defaultGateway;
    }

    if (!$paymentTestMode && (!$hasLiveGateway || !isset($gateways[$selectedGateway]))) {
        $errors[] = 'Ödeme sağlayıcısı yapılandırılmadığı için başvurunuz tamamlanamadı.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO package_orders (package_id, name, email, phone, company, notes, form_data, status, total_amount, created_at) VALUES (:package_id, :name, :email, :phone, :company, :notes, :form_data, :status, :total_amount, NOW())');
            $stmt->execute([
                'package_id' => $packageId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'company' => $company,
                'notes' => $notes,
                'form_data' => json_encode($_POST, JSON_UNESCAPED_UNICODE),
                'status' => $paymentTestMode ? 'paid' : 'pending',
                'total_amount' => $selectedPackage['price'],
            ]);

            $orderId = (int)$pdo->lastInsertId();
            $displayReference = 'PKG-' . $orderId;

            if ($paymentTestMode) {
                $pdo->prepare('UPDATE package_orders SET payment_provider = :provider, payment_reference = :reference WHERE id = :id')
                    ->execute([
                        'provider' => 'test-mode',
                        'reference' => $displayReference,
                        'id' => $orderId,
                    ]);

                $pdo->commit();

                $adminEmails = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(\PDO::FETCH_COLUMN);
                $message = "Test modunda yeni bir bayilik başvurusu tamamlandı.\n\n" .
                    "Başvuru Sahibi: $name\n" .
                    "E-posta: $email\n" .
                    "Paket: {$selectedPackage['name']}\n" .
                    "Tutar: " . Helpers::formatCurrency((float)$selectedPackage['price'], 'USD') . "\n";

                foreach ($adminEmails as $adminEmail) {
                    Mailer::send($adminEmail, 'Test Modu Bayilik Başvurusu', $message);
                }

                $loadedOrder = PackageOrderService::loadOrder($orderId);
                if ($loadedOrder) {
                    PackageOrderService::fulfill($loadedOrder);
                    PackageOrderService::markCompleted($orderId, $loadedOrder);
                }

                $_SESSION['flash_success'] = 'Test modu aktif olduğu için başvurunuz otomatik onaylandı. Giriş bilgileri e-posta ile gönderildi.';
                Helpers::redirect('/index.php');
            }

            $pdo->commit();

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $baseUrl = $scheme . '://' . $host;

            $gateway = PaymentGatewayManager::createGateway($selectedGateway);

            $description = Settings::get('cryptomus_description');
            if ($description === null || $description === '') {
                $description = 'Bayilik paketi: ' . $selectedPackage['name'];
            }

            if ($selectedGateway === 'heleket') {
                $description = Settings::get('heleket_description');
                if ($description === null || $description === '') {
                    $description = 'Bayilik paketi: ' . $selectedPackage['name'];
                }
            }

            $successUrl = Settings::get('cryptomus_success_url');
            $failUrl = Settings::get('cryptomus_fail_url');

            if ($selectedGateway === 'heleket') {
                $successUrl = Settings::get('heleket_success_url');
                $failUrl = Settings::get('heleket_fail_url');
            }

            $callback = isset($gateways[$selectedGateway]) ? $gateways[$selectedGateway]['callback'] : '/webhooks/cryptomus.php';
            $callbackUrl = $baseUrl . $callback;

            $invoice = $gateway->createInvoice(
                (float)$selectedPackage['price'],
                'USD',
                $displayReference,
                $description,
                $email,
                $successUrl ?: $baseUrl . '/index.php',
                $failUrl ?: $baseUrl . '/register.php',
                $callbackUrl
            );

            $paymentReference = isset($invoice['uuid']) ? $invoice['uuid'] : (isset($invoice['order_id']) ? $invoice['order_id'] : null);
            $paymentUrl = isset($invoice['url']) ? $invoice['url'] : null;

            $pdo->prepare('UPDATE package_orders SET payment_provider = :provider, payment_reference = :reference, payment_url = :url WHERE id = :id')
                ->execute([
                    'provider' => $selectedGateway,
                    'reference' => $paymentReference,
                    'url' => $paymentUrl,
                    'id' => $orderId,
                ]);

            $adminEmails = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(\PDO::FETCH_COLUMN);
            $message = "Yeni bir bayilik başvurusu alındı.\n\n" .
                "Başvuru Sahibi: $name\n" .
                "E-posta: $email\n" .
                "Paket: {$selectedPackage['name']}\n" .
                "Tutar: " . Helpers::formatCurrency((float)$selectedPackage['price'], 'USD') . "\n" .
                "Ödeme Yöntemi: " . PaymentGatewayManager::getLabel($selectedGateway) . "\n";

            foreach ($adminEmails as $adminEmail) {
                Mailer::send($adminEmail, 'Yeni Bayilik Başvurusu', $message);
            }

            if ($paymentUrl) {
                Helpers::redirect($paymentUrl);
            }

            $errors[] = 'Ödeme bağlantısı oluşturulamadı. Lütfen tekrar deneyin.';
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($orderId) && $orderId > 0) {
                $pdo->prepare('DELETE FROM package_orders WHERE id = :id')->execute(['id' => $orderId]);
            }
            $errors[] = 'Ödeme işlemi hazırlanırken bir hata oluştu: ' . $exception->getMessage();
        }
    }
}

include __DIR__ . '/templates/auth-header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 720px;">
        <div class="mb-4 text-center">
            <div class="brand">Bayi Başvurusu</div>
            <p class="text-muted">Aşağıdan uygun paketi seçerek başvurunuzu iletebilirsiniz.</p>
        </div>

        <?php if (!$paymentTestMode && !$hasLiveGateway): ?>
            <div class="alert alert-warning">
                Ödeme sağlayıcısı henüz yapılandırılmadığı için başvuru işlemi geçici olarak kapalıdır.
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= Helpers::sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$packages): ?>
            <div class="alert alert-warning">Şu anda başvuruya açık paket bulunmuyor. Lütfen daha sonra tekrar deneyin.</div>
        <?php endif; ?>

        <form method="post" class="row g-3">
            <div class="col-12">
                <label class="form-label">Paket Seçimi</label>
                <select name="package_id" class="form-select" required <?= !$packages ? 'disabled' : '' ?>>
                    <option value="">Paket seçiniz</option>
                    <?php foreach ($packages as $package): ?>
                        <option value="<?= (int)$package['id'] ?>" <?= ((int)(isset($selectedPackage['id']) ? $selectedPackage['id'] : 0) === (int)$package['id']) ? 'selected' : '' ?>>
                            <?= Helpers::sanitize($package['name']) ?> - <?= Helpers::sanitize(Helpers::formatCurrency((float)$package['price'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($paymentTestMode): ?>
                <div class="col-12">
                    <div class="alert alert-info mb-0">Test modu aktif. Ödeme adımı otomatik onaylanır ve giriş bilgileriniz e-posta ile gönderilir.</div>
                </div>
            <?php endif; ?>
            <?php if ($hasLiveGateway): ?>
                <div class="col-12">
                    <label class="form-label">Ödeme Sağlayıcısı</label>
                    <?php foreach ($gateways as $identifier => $gateway): ?>
                        <?php
                        $checked = '';
                        if (isset($_POST['payment_provider'])) {
                            if ($_POST['payment_provider'] === $identifier) {
                                $checked = 'checked';
                            }
                        } elseif ($identifier === $defaultGateway) {
                            $checked = 'checked';
                        }
                        ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_provider" id="package-gateway-<?= Helpers::sanitize($identifier) ?>" value="<?= Helpers::sanitize($identifier) ?>" <?= $checked ?>>
                            <label class="form-check-label" for="package-gateway-<?= Helpers::sanitize($identifier) ?>"><?= Helpers::sanitize($gateway['label']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="col-md-6">
                <label class="form-label">Ad Soyad</label>
                <input type="text" class="form-control" name="name" value="<?= Helpers::sanitize(isset($_POST['name']) ? $_POST['name'] : '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">E-posta</label>
                <input type="email" class="form-control" name="email" value="<?= Helpers::sanitize(isset($_POST['email']) ? $_POST['email'] : '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Telefon</label>
                <input type="text" class="form-control" name="phone" value="<?= Helpers::sanitize(isset($_POST['phone']) ? $_POST['phone'] : '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Firma Adı</label>
                <input type="text" class="form-control" name="company" value="<?= Helpers::sanitize(isset($_POST['company']) ? $_POST['company'] : '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Notlar</label>
                <textarea class="form-control" rows="3" name="notes" placeholder="Eklemek istediğiniz notlar..."><?= Helpers::sanitize(isset($_POST['notes']) ? $_POST['notes'] : '') ?></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary w-100" <?= (!$packages || (!$paymentTestMode && !$hasLiveGateway)) ? 'disabled' : '' ?>>Ödemeyi Tamamla</button>
            </div>
            <div class="col-12 text-center">
                <a href="/" class="small">Giriş sayfasına dön</a>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/templates/auth-footer.php';
