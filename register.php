<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Settings;
use App\Telegram;
use App\Payments\PaymentGatewayManager;
use App\Services\PackageOrderService;
use App\Notifications\ResellerNotifier;

if (!empty($_SESSION['user'])) {
    Helpers::redirect('/dashboard.php');
}

$pdo = Database::connection();
$packages = $pdo->query('SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC')->fetchAll();
$errors = [];
$selectedPackage = null;
$selectedPackageId = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
$paymentReferenceInput = isset($_POST['payment_reference']) ? trim($_POST['payment_reference']) : '';
$paymentNoticeInput = isset($_POST['payment_notice']) ? trim($_POST['payment_notice']) : '';
$passwordInput = isset($_POST['password']) ? (string)$_POST['password'] : '';
$passwordConfirmInput = isset($_POST['password_confirmation']) ? (string)$_POST['password_confirmation'] : '';
$telegramBotTokenInput = isset($_POST['telegram_bot_token']) ? trim($_POST['telegram_bot_token']) : '';
$telegramChatIdInput = isset($_POST['telegram_chat_id']) ? trim($_POST['telegram_chat_id']) : '';
$phoneCountryOptions = array(
    '+90' => 'Türkiye (+90)',
    '+1' => 'ABD / Kanada (+1)',
    '+44' => 'Birleşik Krallık (+44)',
    '+49' => 'Almanya (+49)',
    '+33' => 'Fransa (+33)',
    '+39' => 'İtalya (+39)',
    '+971' => 'Birleşik Arap Emirlikleri (+971)',
    '+966' => 'Suudi Arabistan (+966)',
);
$defaultPhoneCountryCode = '+90';
$phoneCountryCodeInput = isset($_POST['phone_country_code']) ? trim((string)$_POST['phone_country_code']) : $defaultPhoneCountryCode;
if (!isset($phoneCountryOptions[$phoneCountryCodeInput])) {
    $phoneCountryCodeInput = $defaultPhoneCountryCode;
}
$phoneNumberInput = isset($_POST['phone_number']) ? trim((string)$_POST['phone_number']) : '';
$composedPhone = '';
$flashSuccess = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
if ($selectedPackageId === 0 && !empty($packages)) {
    $selectedPackageId = (int)$packages[0]['id'];
}
if ($flashSuccess !== '') {
    unset($_SESSION['flash_success']);
}
$registerBankNotice = isset($_SESSION['register_bank_transfer_notice']) && is_array($_SESSION['register_bank_transfer_notice']) ? $_SESSION['register_bank_transfer_notice'] : array();
if ($registerBankNotice) {
    unset($_SESSION['register_bank_transfer_notice']);
}
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
$selectedGateway = isset($_POST['payment_provider']) ? trim($_POST['payment_provider']) : ($hasLiveGateway ? $defaultGateway : '');

$bankTransferDetails = PaymentGatewayManager::getBankTransferDetails();
$bankTransferSummary = array();
if (isset($gateways['bank-transfer'])) {
    if (!empty($bankTransferDetails['bank_name'])) {
        $bankTransferSummary[] = 'Banka: ' . $bankTransferDetails['bank_name'];
    }
    if (!empty($bankTransferDetails['account_name'])) {
        $bankTransferSummary[] = 'Hesap Sahibi: ' . $bankTransferDetails['account_name'];
    }
    if (!empty($bankTransferDetails['iban'])) {
        $bankTransferSummary[] = 'IBAN: ' . $bankTransferDetails['iban'];
    }
    if (!empty($bankTransferDetails['instructions'])) {
        $lines = preg_split('/\r\n|\r|\n/', $bankTransferDetails['instructions']);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $bankTransferSummary[] = $trimmed;
            }
        }
    }
}

if (!Helpers::featureEnabled('packages')) {
    Helpers::includeTemplate('auth-header.php');
    ?>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="text-center mb-4">
                <div class="brand"><?= Helpers::sanitize(Helpers::siteName()) ?></div>
                <p class="text-muted mt-2">Yeni bayilik başvuruları şu anda kapalı.</p>
            </div>
            <div class="alert alert-info mb-0">Lütfen daha sonra tekrar deneyin veya destek ekibimizle iletişime geçin.</div>
        </div>
    </div>
    <?php
    Helpers::includeTemplate('auth-footer.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageId = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phoneCountryCode = isset($_POST['phone_country_code']) ? trim((string)$_POST['phone_country_code']) : $phoneCountryCodeInput;
    if (!isset($phoneCountryOptions[$phoneCountryCode])) {
        $phoneCountryCode = $defaultPhoneCountryCode;
    }
    $phoneCountryCodeInput = $phoneCountryCode;
    $phoneNumberRaw = isset($_POST['phone_number']) ? trim((string)$_POST['phone_number']) : '';
    $phoneNumberInput = $phoneNumberRaw;
    $digitsOnlyPhone = preg_replace('/\D+/', '', $phoneNumberRaw);
    if ($digitsOnlyPhone === '') {
        $errors[] = 'Telefon numarası zorunludur.';
    }
    $composedPhone = $phoneCountryCode . $digitsOnlyPhone;
    $_POST['phone'] = $composedPhone;
    if ($digitsOnlyPhone !== '' && !preg_match('/^\+[1-9]\d{7,14}$/', $composedPhone)) {
        $errors[] = 'Telefon numarası geçerli bir uluslararası formatta olmalıdır.';
    }
    $phone = $composedPhone;
    $company = isset($_POST['company']) ? trim($_POST['company']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $password = $passwordInput;
    $passwordConfirm = $passwordConfirmInput;
    $telegramBotToken = $telegramBotTokenInput;
    $telegramChatId = $telegramChatIdInput;

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

    $selectedPackageId = $packageId;

    if (!$selectedPackage) {
        $errors[] = 'Seçilen paket bulunamadı veya aktif değil.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Şifreniz en az 8 karakter olmalıdır.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'Şifre ve şifre tekrar alanları eşleşmiyor.';
    }

    if ($telegramBotToken === '' || $telegramChatId === '') {
        $errors[] = 'Telegram bot tokenı ve sohbet kimliği zorunludur.';
    }

    $selectedGateway = isset($_POST['payment_provider']) ? trim($_POST['payment_provider']) : $selectedGateway;
    if ($selectedGateway === '' && $hasLiveGateway) {
        $selectedGateway = $defaultGateway;
    }

    $paymentReferenceInput = isset($_POST['payment_reference']) ? trim($_POST['payment_reference']) : $paymentReferenceInput;
    $paymentNoticeInput = isset($_POST['payment_notice']) ? trim($_POST['payment_notice']) : $paymentNoticeInput;

    $userCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $userCheck->execute(['email' => $email]);
    if ($userCheck->fetchColumn()) {
        $errors[] = 'Bu e-posta adresiyle zaten bir hesap mevcut. Lütfen giriş yapmayı deneyin.';
    }

    if (!$paymentTestMode && (!$hasLiveGateway || !isset($gateways[$selectedGateway]))) {
        $errors[] = 'Ödeme sağlayıcısı yapılandırılmadığı için başvurunuz tamamlanamadı.';
    }

    if ($selectedGateway === 'bank-transfer' && $paymentNoticeInput === '') {
        $errors[] = 'Banka havalesi ile ödeme bildirimi yaparken açıklama alanı zorunludur.';
    }

    if (!$errors) {
        $methodLabel = $paymentTestMode ? 'Test Modu' : PaymentGatewayManager::getLabel($selectedGateway);
        $pdo->beginTransaction();
        $orderId = 0;
        $orderPersisted = false;
        try {
            $pdo->beginTransaction();

            $userId = Auth::createUser(
                $name,
                $email,
                $password,
                'reseller',
                0,
                array(
                    'status' => 'inactive',
                    'telegram_bot_token' => $telegramBotToken,
                    'telegram_chat_id' => $telegramChatId,
                )
            );

            $formPayload = $_POST;
            unset($formPayload['password'], $formPayload['password_confirmation'], $formPayload['telegram_bot_token'], $formPayload['telegram_chat_id']);
            if (isset($formPayload['csrf_token'])) {
                unset($formPayload['csrf_token']);
            }

            $stmt = $pdo->prepare('INSERT INTO package_orders (package_id, user_id, name, email, phone, company, notes, form_data, status, total_amount, created_at) VALUES (:package_id, :user_id, :name, :email, :phone, :company, :notes, :form_data, :status, :total_amount, NOW())');
            $stmt->execute([
                'package_id' => $packageId,
                'user_id' => $userId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'company' => $company,
                'notes' => $notes,
                'form_data' => json_encode($formPayload, JSON_UNESCAPED_UNICODE),
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
                $orderPersisted = true;

                Telegram::notify(sprintf(
                    "🧾 Test modunda bayilik başvurusu tamamlandı!\nAd: %s\nE-posta: %s\nPaket: %s\nTutar: %s\nBaşvuru No: %s",
                    $name,
                    $email,
                    $selectedPackage['name'],
                    Helpers::formatCurrency((float)$selectedPackage['price'], 'USD'),
                    $displayReference
                ));

                $loadedOrder = PackageOrderService::loadOrder($orderId);
                if ($loadedOrder) {
                    PackageOrderService::fulfill($loadedOrder);
                    PackageOrderService::markCompleted($orderId, $loadedOrder);
                }

                $_SESSION['flash_success'] = 'Test modu aktif olduğu için başvurunuz otomatik onaylandı. Giriş bilgileri Telegram botunuza gönderildi.';
                Helpers::redirect('/index.php');
            }

            if ($selectedGateway === 'bank-transfer') {
                $pdo->prepare('UPDATE package_orders SET payment_provider = :provider, payment_reference = :reference WHERE id = :id')
                    ->execute([
                        'provider' => $selectedGateway,
                        'reference' => $displayReference,
                        'id' => $orderId,
                    ]);

                $referenceValue = $paymentReferenceInput !== '' ? $paymentReferenceInput : $displayReference;
                $pdo->prepare('INSERT INTO balance_requests (user_id, package_order_id, amount, payment_method, payment_provider, payment_reference, reference, notes, status, created_at) VALUES (:user_id, :package_order_id, :amount, :payment_method, :payment_provider, :payment_reference, :reference, :notes, :status, NOW())')
                    ->execute([
                        'user_id' => $userId,
                        'package_order_id' => $orderId,
                        'amount' => (float)$selectedPackage['price'],
                        'payment_method' => $methodLabel,
                        'payment_provider' => $selectedGateway,
                        'payment_reference' => $paymentReferenceInput !== '' ? $paymentReferenceInput : null,
                        'reference' => $referenceValue,
                        'notes' => $paymentNoticeInput !== '' ? $paymentNoticeInput : null,
                        'status' => 'pending',
                    ]);

                $pdo->commit();
                $orderPersisted = true;

                $noticeLines = array();
                if (!empty($bankTransferDetails['bank_name'])) {
                    $noticeLines[] = 'Banka: ' . $bankTransferDetails['bank_name'];
                }
                if (!empty($bankTransferDetails['account_name'])) {
                    $noticeLines[] = 'Hesap Sahibi: ' . $bankTransferDetails['account_name'];
                }
                if (!empty($bankTransferDetails['iban'])) {
                    $noticeLines[] = 'IBAN: ' . $bankTransferDetails['iban'];
                }
                $noticeLines[] = 'Tutar: ' . Helpers::formatCurrency((float)$selectedPackage['price']);
                $noticeLines[] = 'Başvuru No: ' . $displayReference;
                if ($paymentReferenceInput !== '') {
                    $noticeLines[] = 'Dekont / Referans: ' . $paymentReferenceInput;
                }
                if (!empty($bankTransferDetails['instructions'])) {
                    $lines = preg_split('/\r\n|\r|\n/', $bankTransferDetails['instructions']);
                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if ($trimmed !== '') {
                            $noticeLines[] = $trimmed;
                        }
                    }
                }
                if ($paymentNoticeInput !== '') {
                    $noticeLines[] = 'Bildirilen Açıklama: ' . $paymentNoticeInput;
                }

                $_SESSION['flash_success'] = 'Başvurunuz alındı. Havale/EFT talimatları Telegram botunuza gönderildi.';
                $_SESSION['register_bank_transfer_notice'] = $noticeLines;

                $userRecord = Auth::findUser($userId);
                if ($userRecord) {
                    $amountText = Helpers::formatCurrency((float)$selectedPackage['price']);
                    $messageLines = array(
                        '🏦 <b>Ödeme talimatı</b>',
                        '',
                        '📦 Paket: <b>' . htmlspecialchars($selectedPackage['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>',
                        '💰 Tutar: <b>' . htmlspecialchars($amountText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>',
                        '🔖 Referans: <code>' . htmlspecialchars($referenceValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>',
                    );

                    if (!empty($bankTransferDetails['bank_name'])) {
                        $messageLines[] = '🏛 Banka: ' . htmlspecialchars($bankTransferDetails['bank_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    }
                    if (!empty($bankTransferDetails['account_name'])) {
                        $messageLines[] = '👤 Hesap Sahibi: ' . htmlspecialchars($bankTransferDetails['account_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    }
                    if (!empty($bankTransferDetails['iban'])) {
                        $messageLines[] = '🏷 IBAN: <code>' . htmlspecialchars($bankTransferDetails['iban'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
                    }
                    if (!empty($bankTransferDetails['instructions'])) {
                        $messageLines[] = '';
                        $messageLines[] = '📝 Talimatlar:';
                        $instructionLines = preg_split('/\r\n|\r|\n/', $bankTransferDetails['instructions']);
                        foreach ($instructionLines as $line) {
                            $trimmed = trim($line);
                            if ($trimmed !== '') {
                                $messageLines[] = '• ' . htmlspecialchars($trimmed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            }
                        }
                    }
                    if ($paymentNoticeInput !== '') {
                        $messageLines[] = '';
                        $messageLines[] = '📨 Bildirdiğiniz not: ' . htmlspecialchars($paymentNoticeInput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    }

                    $messageLines[] = '';
                    $messageLines[] = '✅ Ödeme sonrasında dekontu iletmeyi unutmayın.';

                    ResellerNotifier::sendDirect($userRecord, implode("\n", $messageLines));
                }

                Telegram::notify(sprintf(
                    "🧾 Yeni bayilik başvurusu alındı!\nAd: %s\nE-posta: %s\nPaket: %s\nTutar: %s\nBaşvuru No: %s",
                    $name,
                    $email,
                    $selectedPackage['name'],
                    Helpers::formatCurrency((float)$selectedPackage['price'], 'USD'),
                    $displayReference
                ));

                Helpers::redirect('/register.php');
            }

            $pdo->commit();
            $orderPersisted = true;

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

            Telegram::notify(sprintf(
                "🧾 Yeni bayilik başvurusu alındı!\nAd: %s\nE-posta: %s\nPaket: %s\nTutar: %s\nBaşvuru No: %s",
                $name,
                $email,
                $selectedPackage['name'],
                Helpers::formatCurrency((float)$selectedPackage['price'], 'USD'),
                $displayReference
            ));

            if ($paymentUrl) {
                Helpers::redirect($paymentUrl);
            }

            $errors[] = 'Ödeme bağlantısı oluşturulamadı. Lütfen tekrar deneyin.';
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($orderId) && $orderId > 0 && !$orderPersisted) {
                try {
                    $pdo->prepare('DELETE FROM balance_requests WHERE package_order_id = :id')->execute(['id' => $orderId]);
                    $pdo->prepare('DELETE FROM package_orders WHERE id = :id')->execute(['id' => $orderId]);
                } catch (\Throwable $cleanupException) {
                    error_log(sprintf('Register cleanup failed for order %d: %s', $orderId, $cleanupException->getMessage()));
                }
            }
            $errors[] = 'Ödeme işlemi hazırlanırken bir hata oluştu: ' . $exception->getMessage();
        }
    }
}

Helpers::includeTemplate('auth-header.php');
?>
<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 720px;">
        <div class="mb-4 text-center">
            <div class="brand">Bayi Başvurusu</div>
            <p class="text-muted">Aşağıdan uygun paketi seçerek başvurunuzu iletebilirsiniz.</p>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= Helpers::sanitize($flashSuccess) ?></div>
        <?php endif; ?>

        <?php if ($registerBankNotice): ?>
            <div class="alert alert-info">
                <h6 class="mb-2">Banka Havalesi Talimatı</h6>
                <ul class="mb-0">
                    <?php foreach ($registerBankNotice as $line): ?>
                        <li><?= Helpers::sanitize($line) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

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

        <form method="post" class="row g-3">
            <div class="col-12">
                <label class="form-label">Paket Seçimi</label>
                <?php if ($packages): ?>
                    <div class="package-grid">
                        <?php foreach ($packages as $package): ?>
                            <?php
                            $packageId = (int)$package['id'];
                            $packageInputId = 'package-option-' . $packageId;
                            $features = isset($package['features']) ? array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$package['features']))) : array();
                            $isSelected = $selectedPackageId === $packageId;
                            ?>
                            <input type="radio" class="btn-check" name="package_id" id="<?= Helpers::sanitize($packageInputId) ?>" value="<?= $packageId ?>" <?= $isSelected ? 'checked' : '' ?> required>
                            <label class="package-card" for="<?= Helpers::sanitize($packageInputId) ?>">
                                <div class="package-card-header">
                                    <div>
                                        <span class="package-name"><?= Helpers::sanitize($package['name']) ?></span>
                                        <?php if (!empty($package['description'])): ?>
                                            <p class="package-description mb-0"><?= Helpers::sanitize($package['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="package-price"><?= Helpers::sanitize(Helpers::formatCurrency((float)$package['price'])) ?></span>
                                </div>
                                <?php if ($features): ?>
                                    <ul class="package-feature-list">
                                        <?php foreach ($features as $feature): ?>
                                            <li><?= Helpers::sanitize($feature) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <div class="package-footer d-flex justify-content-between align-items-center">
                                    <span class="badge bg-light text-dark">Başlangıç Bakiyesi: <?= Helpers::sanitize(Helpers::formatCurrency((float)$package['initial_balance'])) ?></span>
                                    <span class="package-tag">ID #<?= $packageId ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">Şu anda başvuruya açık paket bulunmuyor. Lütfen daha sonra tekrar deneyin.</div>
                <?php endif; ?>
            </div>
            <?php if ($paymentTestMode): ?>
                <div class="col-12">
                    <div class="alert alert-info mb-0">Test modu aktif. Ödeme adımı otomatik onaylanır ve giriş bilgileriniz Telegram botunuza gönderilir.</div>
                </div>
            <?php endif; ?>
            <?php if ($hasLiveGateway): ?>
                <div class="col-12">
                    <label class="form-label">Ödeme Sağlayıcısı</label>
                    <?php foreach ($gateways as $identifier => $gateway): ?>
                        <?php $checked = $selectedGateway === $identifier ? 'checked' : ''; ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_provider" id="package-gateway-<?= Helpers::sanitize($identifier) ?>" value="<?= Helpers::sanitize($identifier) ?>" <?= $checked ?>>
                            <label class="form-check-label" for="package-gateway-<?= Helpers::sanitize($identifier) ?>"><?= Helpers::sanitize($gateway['label']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($bankTransferSummary): ?>
                    <div class="col-12">
                        <div class="alert alert-secondary small mb-0">
                            <strong>Banka Havalesi Talimatı</strong>
                            <ul class="mb-0">
                                <?php foreach ($bankTransferSummary as $line): ?>
                                    <li><?= Helpers::sanitize($line) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (isset($gateways['bank-transfer'])): ?>
                    <div class="col-12" id="bank-transfer-fields" <?= $selectedGateway === 'bank-transfer' ? '' : 'style="display:none;"' ?>>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title mb-3">Ödeme Bildirimi</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Dekont / Referans Numarası</label>
                                        <input type="text" class="form-control" name="payment_reference" value="<?= Helpers::sanitize($paymentReferenceInput) ?>" placeholder="Örn. EFT referansı">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Ödeme Açıklaması <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="payment_notice" rows="3" placeholder="Havale bilgilerinizi paylaşın" <?= $selectedGateway === 'bank-transfer' ? 'required' : '' ?>><?= Helpers::sanitize($paymentNoticeInput) ?></textarea>
                                        <small class="text-muted">Hangi bankadan, hangi adla ve ne zaman gönderim yaptığınızı belirtmeniz değerlendirmeyi hızlandırır.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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
                <label class="form-label">Şifre</label>
                <input type="password" class="form-control" name="password" placeholder="En az 8 karakter" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Şifre (Tekrar)</label>
                <input type="password" class="form-control" name="password_confirmation" placeholder="Şifrenizi doğrulayın" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Telefon</label>
                <div class="input-group">
                    <select class="form-select" name="phone_country_code">
                        <?php foreach ($phoneCountryOptions as $code => $label): ?>
                            <option value="<?= Helpers::sanitize($code) ?>" <?= $code === $phoneCountryCodeInput ? 'selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="tel" class="form-control" name="phone_number" value="<?= Helpers::sanitize($phoneNumberInput) ?>" placeholder="555 123 4567" required>
                </div>
                <div class="form-text">Lütfen alan kodu seçip telefon numaranızı girin. Bildirimler bu numara üzerinden iletilecektir.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Firma Adı</label>
                <input type="text" class="form-control" name="company" value="<?= Helpers::sanitize(isset($_POST['company']) ? $_POST['company'] : '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Telegram Bot Tokenı</label>
                <input type="text" class="form-control" name="telegram_bot_token" value="<?= Helpers::sanitize($telegramBotTokenInput) ?>" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" required>
                <small class="text-muted">BotFather üzerinden oluşturduğunuz botun erişim tokenını girin.</small>
            </div>
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label mb-0">Telegram Chat ID</label>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#telegramGuide" aria-expanded="false" aria-controls="telegramGuide">Rehberi Aç</button>
                </div>
                <input type="text" class="form-control mt-2" name="telegram_chat_id" value="<?= Helpers::sanitize($telegramChatIdInput) ?>" placeholder="@kullanici veya numerik ID" required>
                <small class="text-muted">Bildirimlerin gönderileceği kullanıcı veya kanal kimliği.</small>
                <div class="collapse mt-3" id="telegramGuide">
                    <div class="card card-body bg-light border-0">
                        <ol class="mb-0 small">
                            <li><strong>@BotFather</strong> üzerinden <code>/newbot</code> komutuyla bir bot oluşturun ve verdiği tokenı kopyalayın.</li>
                            <li>Oluşturduğunuz bot ile konuşmayı başlatıp <code>/start</code> mesajı gönderin.</li>
                            <li><a href="https://t.me/get_id_bot" target="_blank" rel="noopener">@get_id_bot</a> gibi bir araçla kullanıcı ID'nizi öğrenin veya botu eklediğiniz kanalın ID'sini alın.</li>
                            <li>Tokenı ve chat ID'yi yukarıdaki alanlara girerek bildirimlerin Telegram üzerinden gelmesini sağlayın.</li>
                        </ol>
                    </div>
                </div>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    var gatewayInputs = document.querySelectorAll('input[name="payment_provider"]');
    var bankFields = document.getElementById('bank-transfer-fields');
    var noticeField = bankFields ? bankFields.querySelector('textarea[name="payment_notice"]') : null;

    function toggleBankFields() {
        if (!bankFields) {
            return;
        }

        var selected = document.querySelector('input[name="payment_provider"]:checked');
        var isBankTransfer = selected && selected.value === 'bank-transfer';

        bankFields.style.display = isBankTransfer ? '' : 'none';

        if (noticeField) {
            noticeField.required = !!isBankTransfer;
        }
    }

    gatewayInputs.forEach(function (input) {
        input.addEventListener('change', toggleBankFields);
    });

    toggleBankFields();
});
</script>
<?php Helpers::includeTemplate('auth-footer.php'); ?>
