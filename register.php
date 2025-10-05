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
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authero - Başvuru Kapalı</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem;
            }
            
            .auth-card {
                background: white;
                border-radius: 1rem;
                padding: 2rem;
                max-width: 500px;
                width: 100%;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                text-align: center;
            }
            
            .brand {
                color: #3b82f6;
                font-size: 2rem;
                font-weight: bold;
                margin-bottom: 1rem;
            }
            
            .alert {
                padding: 1rem;
                border-radius: 0.5rem;
                background: #dbeafe;
                border: 1px solid #3b82f6;
                color: #1e40af;
            }
        </style>
    </head>
    <body>
        <div class="auth-card">
            <div class="brand">Authero</div>
            <p style="color: #6b7280; margin-bottom: 1.5rem;">Yeni bayilik başvuruları şu anda kapalı.</p>
            <div class="alert">Lütfen daha sonra tekrar deneyin veya destek ekibimizle iletişime geçin.</div>
        </div>
    </body>
    </html>
    <?php
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
                    "🧾 Test modunda bayilik başvurusu tamamlandı!
Ad: %s
E-posta: %s
Paket: %s
Tutar: %s
Başvuru No: %s",
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
                    "🧾 Yeni bayilik başvurusu alındı!
Ad: %s
E-posta: %s
Paket: %s
Tutar: %s
Başvuru No: %s",
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
                "🧾 Yeni bayilik başvurusu alındı!
Ad: %s
E-posta: %s
Paket: %s
Tutar: %s
Başvuru No: %s",
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
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authero - Yeni Bayi Başvurusu</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
        }
        
        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        .left-panel {
            flex: 1;
            background: white;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem;
            overflow-y: auto;
        }
        
        .right-panel {
            flex: 1;
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), #364352;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
        }
        
        .form-container {
            width: 100%;
            max-width: 600px;
            margin-top: 2rem;
        }
        
        .logo {
            color: #3b82f6;
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 2rem;
        }
        
        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .form-subtitle {
            color: #6b7280;
            margin-bottom: 2rem;
        }
        
        .form-subtitle a {
            color: #3b82f6;
            text-decoration: none;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.2s;
            background: #f9fafb;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
        }
        
        .form-input::placeholder, .form-textarea::placeholder {
            color: #9ca3af;
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }
        
        .input-group {
            display: flex;
        }
        
        .input-group .form-select {
            border-radius: 0.5rem 0 0 0.5rem;
            border-right: none;
            max-width: 150px;
        }
        
        .input-group .form-input {
            border-radius: 0 0.5rem 0.5rem 0;
            border-left: 1px solid #e5e7eb;
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .checkbox-input {
            margin-top: 0.25rem;
            width: 18px;
            height: 18px;
            accent-color: #3b82f6;
        }
        
        .checkbox-label {
            color: #374151;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        
        .checkbox-label a {
            color: #3b82f6;
            text-decoration: none;
        }
        
        .btn-primary {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #8b5cf6, #3b82f6);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            margin-bottom: 1.5rem;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            width: 100%;
            padding: 0.875rem;
            background: white;
            color: #374151;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            cursor: pointer;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: background-color 0.2s;
        }
        
        .btn-secondary:hover {
            background: #f9fafb;
        }
        
        .btn-outline-secondary {
            padding: 0.5rem 1rem;
            background: transparent;
            color: #6b7280;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-outline-secondary:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }
        
        .promo-content {
            text-align: center;
            z-index: 1;
            position: relative;
        }
        
        .promo-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 2rem;
            line-height: 1.2;
        }
        
        .features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }
        
        .feature-icon {
            width: 20px;
            height: 20px;
            background: #3b82f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .feature-icon::after {
            content: '✓';
            color: white;
            font-size: 0.75rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid;
        }
        
        .alert-success {
            background: #dcfce7;
            border-color: #16a34a;
            color: #166534;
        }
        
        .alert-warning {
            background: #fef3c7;
            border-color: #d97706;
            color: #92400e;
        }
        
        .alert-danger {
            background: #fee2e2;
            border-color: #dc2626;
            color: #991b1b;
        }
        
        .alert-info {
            background: #dbeafe;
            border-color: #3b82f6;
            color: #1e40af;
        }
        
        .alert ul {
            margin: 0;
            padding-left: 1rem;
        }
        
        .info-box {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 2rem;
            color: #0369a1;
            font-size: 0.875rem;
        }
        
        .info-box h3 {
            margin-bottom: 0.5rem;
            font-size: 1rem;
            color: #0c4a6e;
        }
        
        .package-grid {
            display: grid;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .package-card {
            display: block;
            padding: 1.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            background: #f9fafb;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
        }
        
        .package-card:hover {
            border-color: #3b82f6;
            background: #f0f9ff;
        }
        
        .btn-check:checked + .package-card {
            border-color: #3b82f6;
            background: #f0f9ff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .package-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .package-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .package-description {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .package-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #3b82f6;
        }
        
        .package-feature-list {
            list-style: none;
            margin: 1rem 0;
            padding: 0;
        }
        
        .package-feature-list li {
            padding: 0.25rem 0;
            color: #4b5563;
            font-size: 0.875rem;
        }
        
        .package-feature-list li::before {
            content: '✓';
            color: #10b981;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        
        .package-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .package-tag {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .bg-light {
            background: #f3f4f6;
        }
        
        .text-dark {
            color: #1f2937;
        }
        
        .form-check {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        
        .form-check-input {
            margin-top: 0.25rem;
            width: 18px;
            height: 18px;
            accent-color: #3b82f6;
        }
        
        .form-check-label {
            color: #374151;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        
        .btn-check {
            display: none;
        }
        
        .form-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #6b7280;
        }
        
        .small {
            font-size: 0.875rem;
        }
        
        .collapse {
            display: none;
        }
        
        .collapse.show {
            display: block;
        }
        
        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
        }
        
        .card-body {
            padding: 1rem;
        }
        
        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
        }
        
        .bg-light {
            background: #f9fafb;
        }
        
        .border-0 {
            border: none !important;
        }
        
        .shadow-sm {
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        
        .text-danger {
            color: #dc2626;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .right-panel {
                order: -1;
                min-height: 300px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .promo-title {
                font-size: 1.8rem;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
            
            .input-group {
                flex-direction: column;
            }
            
            .input-group .form-select,
            .input-group .form-input {
                border-radius: 0.5rem;
                border: 2px solid #e5e7eb;
            }
            
            .form-container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="form-container">
                <div class="logo">Authero</div>
                
                <h1 class="form-title">Yeni Bayi Başvurusu</h1>
                <p class="form-subtitle">
                    Zaten bayimiz misiniz? <a href="index.php">Giriş Yapın</a>
                </p>
                
                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($flashSuccess) ?></div>
                <?php endif; ?>

                <?php if ($registerBankNotice): ?>
                    <div class="alert alert-info">
                        <h6 style="margin-bottom: 0.5rem; font-weight: 600;">Banka Havalesi Talimatı</h6>
                        <ul style="margin-bottom: 0;">
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
                        <ul style="margin-bottom: 0;">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="info-box">
                    <h3>Bayi Olmak İçin</h3>
                    Aşağıdaki formu eksiksiz doldurmanız gerekmektedir. Başvurunuz değerlendirildikten sonra size geri dönüş yapılacaktır.
                </div>
                
                <form method="post">
                    <!-- Paket Seçimi -->
                    <div class="form-group">
                        <label class="form-label">Paket Seçimi *</label>
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
                                                    <p class="package-description"><?= Helpers::sanitize($package['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="package-price"><?= Helpers::formatCurrencyHtml((float)$package['price']) ?></span>
                                        </div>
                                        <?php if ($features): ?>
                                            <ul class="package-feature-list">
                                                <?php foreach ($features as $feature): ?>
                                                    <li><?= Helpers::sanitize($feature) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <div class="package-footer">
                                            <span class="badge bg-light text-dark">Başlangıç Bakiyesi: <?= Helpers::formatCurrencyHtml((float)$package['initial_balance']) ?></span>
                                            <span class="package-tag">ID #<?= $packageId ?></span>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">Şu anda başvuruya açık paket bulunmuyor. Lütfen daha sonra tekrar deneyin.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Test Modu Uyarısı -->
                    <?php if ($paymentTestMode): ?>
                        <div class="alert alert-info">Test modu aktif. Ödeme adımı otomatik onaylanır ve giriş bilgileriniz Telegram botunuza gönderilir.</div>
                    <?php endif; ?>

                    <!-- Ödeme Sağlayıcısı -->
                    <?php if ($hasLiveGateway): ?>
                        <div class="form-group">
                            <label class="form-label">Ödeme Sağlayıcısı *</label>
                            <?php foreach ($gateways as $identifier => $gateway): ?>
                                <?php $checked = $selectedGateway === $identifier ? 'checked' : ''; ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_provider" id="package-gateway-<?= Helpers::sanitize($identifier) ?>" value="<?= Helpers::sanitize($identifier) ?>" <?= $checked ?>>
                                    <label class="form-check-label" for="package-gateway-<?= Helpers::sanitize($identifier) ?>"><?= Helpers::sanitize($gateway['label']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Banka Havalesi Bilgileri -->
                        <?php if ($bankTransferSummary): ?>
                            <div class="alert alert-info" style="font-size: 0.875rem;">
                                <strong>Banka Havalesi Talimatı</strong>
                                <ul style="margin-bottom: 0;">
                                    <?php foreach ($bankTransferSummary as $line): ?>
                                        <li><?= Helpers::sanitize($line) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Banka Havalesi Alanları -->
                        <?php if (isset($gateways['bank-transfer'])): ?>
                            <div id="bank-transfer-fields" <?= $selectedGateway === 'bank-transfer' ? '' : 'style="display:none;"' ?>>
                                <div class="card border-0 shadow-sm" style="margin-bottom: 1.5rem;">
                                    <div class="card-body">
                                        <h6 class="card-title">Ödeme Bildirimi</h6>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label class="form-label">Dekont / Referans Numarası</label>
                                                <input type="text" class="form-input" name="payment_reference" value="<?= Helpers::sanitize($paymentReferenceInput) ?>" placeholder="Örn. EFT referansı">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Ödeme Açıklaması <span class="text-danger">*</span></label>
                                            <textarea class="form-textarea" name="payment_notice" rows="3" placeholder="Havale bilgilerinizi paylaşın" <?= $selectedGateway === 'bank-transfer' ? 'required' : '' ?>><?= Helpers::sanitize($paymentNoticeInput) ?></textarea>
                                            <div class="form-text">Hangi bankadan, hangi adla ve ne zaman gönderim yaptığınızı belirtmeniz değerlendirmeyi hızlandırır.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Kişisel Bilgiler -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Ad Soyad *</label>
                            <input type="text" class="form-input" name="name" value="<?= Helpers::sanitize(isset($_POST['name']) ? $_POST['name'] : '') ?>" placeholder="Ad ve soyadınızı girin" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">E-posta Adresi *</label>
                            <input type="email" class="form-input" name="email" value="<?= Helpers::sanitize(isset($_POST['email']) ? $_POST['email'] : '') ?>" placeholder="ornek@firma.com" required>
                        </div>
                    </div>

                    <!-- Şifre -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Şifre *</label>
                            <input type="password" class="form-input" name="password" placeholder="En az 8 karakter" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Şifre Tekrarı *</label>
                            <input type="password" class="form-input" name="password_confirmation" placeholder="Şifrenizi doğrulayın" required>
                        </div>
                    </div>

                    <!-- Telefon ve Firma -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Telefon Numarası *</label>
                            <div class="input-group">
                                <select class="form-select" name="phone_country_code">
                                    <?php foreach ($phoneCountryOptions as $code => $label): ?>
                                        <option value="<?= Helpers::sanitize($code) ?>" <?= $code === $phoneCountryCodeInput ? 'selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="tel" class="form-input" name="phone_number" value="<?= Helpers::sanitize($phoneNumberInput) ?>" placeholder="555 123 4567" required>
                            </div>
                            <div class="form-text">Lütfen alan kodu seçip telefon numaranızı girin. Bildirimler bu numara üzerinden iletilecektir.</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Firma Adı</label>
                            <input type="text" class="form-input" name="company" value="<?= Helpers::sanitize(isset($_POST['company']) ? $_POST['company'] : '') ?>" placeholder="Firma adınızı girin">
                        </div>
                    </div>

                    <!-- Telegram Bilgileri -->
                    <div class="form-group">
                        <label class="form-label">Telegram Bot Tokenı *</label>
                        <input type="text" class="form-input" name="telegram_bot_token" value="<?= Helpers::sanitize($telegramBotTokenInput) ?>" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" required>
                        <div class="form-text">BotFather üzerinden oluşturduğunuz botun erişim tokenını girin.</div>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <label class="form-label" style="margin-bottom: 0;">Telegram Chat ID *</label>
                            <button type="button" class="btn-outline-secondary" onclick="toggleTelegramGuide()">Rehberi Aç</button>
                        </div>
                        <input type="text" class="form-input" name="telegram_chat_id" value="<?= Helpers::sanitize($telegramChatIdInput) ?>" placeholder="@kullanici veya numerik ID" required>
                        <div class="form-text">Bildirimlerin gönderileceği kullanıcı veya kanal kimliği.</div>
                        
                        <div class="collapse" id="telegramGuide">
                            <div class="card bg-light border-0" style="margin-top: 1rem;">
                                <div class="card-body">
                                    <ol style="margin-bottom: 0; font-size: 0.875rem;">
                                        <li><strong>@BotFather</strong> üzerinden <code>/newbot</code> komutuyla bir bot oluşturun ve verdiği tokenı kopyalayın.</li>
                                        <li>Oluşturduğunuz bot ile konuşmayı başlatıp <code>/start</code> mesajı gönderin.</li>
                                        <li><a href="https://t.me/get_id_bot" target="_blank" rel="noopener">@get_id_bot</a> gibi bir araçla kullanıcı ID'nizi öğrenin veya botu eklediğiniz kanalın ID'sini alın.</li>
                                        <li>Tokenı ve chat ID'yi yukarıdaki alanlara girerek bildirimlerin Telegram üzerinden gelmesini sağlayın.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notlar -->
                    <div class="form-group">
                        <label class="form-label">Notlar</label>
                        <textarea class="form-textarea" name="notes" placeholder="Eklemek istediğiniz notlar..."><?= Helpers::sanitize(isset($_POST['notes']) ? $_POST['notes'] : '') ?></textarea>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">

                    <!-- Gönder Butonu -->
                    <button type="submit" class="btn-primary" <?= (!$packages || (!$paymentTestMode && !$hasLiveGateway)) ? 'disabled' : '' ?>>
                        Ödemeyi Tamamla
                    </button>
                    
                    <div class="text-center">
                        <a href="index.php" style="color: #6b7280; font-size: 0.875rem; text-decoration: none;">Giriş sayfasına dön</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="right-panel">
            <div class="promo-content">
                <h2 class="promo-title">Bayi Yönetim Sistemi</h2>
                <div class="features">
                    <div class="feature-item">
                        <div class="feature-icon"></div>
                        Esnek Paketler
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"></div>
                        Kolay Ödeme
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"></div>
                        Telegram Bildirimleri
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"></div>
                        7/24 Destek
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleTelegramGuide() {
            const guide = document.getElementById('telegramGuide');
            if (guide.style.display === 'none' || guide.style.display === '') {
                guide.style.display = 'block';
            } else {
                guide.style.display = 'none';
            }
        }

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
</body>
</html>
