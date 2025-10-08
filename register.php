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

if (Auth::currentReseller()) {
    Helpers::redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /account/login?tab=application', true, 301);
    exit;
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
$registerBankNotice = array();
if ($selectedPackageId === 0 && !empty($packages)) {
    $selectedPackageId = (int)$packages[0]['id'];
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

$applicationOld = array(
    'package_id' => $selectedPackageId,
    'payment_provider' => $selectedGateway,
    'name' => isset($_POST['name']) ? trim($_POST['name']) : '',
    'email' => isset($_POST['email']) ? trim($_POST['email']) : '',
    'phone_country_code' => isset($_POST['phone_country_code']) ? trim((string)$_POST['phone_country_code']) : $defaultPhoneCountryCode,
    'phone_number' => isset($_POST['phone_number']) ? trim((string)$_POST['phone_number']) : '',
    'company' => isset($_POST['company']) ? trim($_POST['company']) : '',
    'notes' => isset($_POST['notes']) ? trim($_POST['notes']) : '',
    'telegram_bot_token' => $telegramBotTokenInput,
    'telegram_chat_id' => $telegramChatIdInput,
    'payment_reference' => $paymentReferenceInput,
    'payment_notice' => $paymentNoticeInput,
);

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
    $_SESSION['application_warnings'] = array('Yeni bayilik başvuruları şu anda kapalı. Lütfen daha sonra tekrar deneyin.');
    header('Location: /bayi/login.php?tab=application', true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $_SESSION['application_errors'] = array('Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    $_SESSION['application_old'] = $applicationOld;
    Helpers::redirect('/account/login?tab=application');
}

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
                    Helpers::formatCurrency((float)$selectedPackage['price'], 'TRY'),
                    $displayReference
                ));

                $loadedOrder = PackageOrderService::loadOrder($orderId);
                if ($loadedOrder) {
                    PackageOrderService::fulfill($loadedOrder);
                    PackageOrderService::markCompleted($orderId, $loadedOrder);
                }

                $_SESSION['application_success'] = 'Test modu aktif olduğu için başvurunuz otomatik onaylandı. Giriş bilgileri Telegram botunuza gönderildi.';
                Helpers::redirect('/account/login?tab=application');
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

                $_SESSION['application_success'] = 'Başvurunuz alındı. Havale/EFT talimatları Telegram botunuza gönderildi.';
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
                    Helpers::formatCurrency((float)$selectedPackage['price'], 'TRY'),
                    $displayReference
                ));

                Helpers::redirect('/account/login?tab=application');
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
                'TRY',
                $displayReference,
                $description,
                $email,
                $successUrl ?: $baseUrl . '/account/login',
                $failUrl ?: $baseUrl . '/account/login?tab=application',
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
                Helpers::formatCurrency((float)$selectedPackage['price'], 'TRY'),
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

$applicationOld = array(
    'package_id' => $selectedPackageId,
    'payment_provider' => $selectedGateway,
    'name' => isset($name) ? $name : '',
    'email' => isset($email) ? $email : '',
    'phone_country_code' => $phoneCountryCodeInput,
    'phone_number' => $phoneNumberInput,
    'company' => isset($company) ? $company : '',
    'notes' => isset($notes) ? $notes : '',
    'telegram_bot_token' => $telegramBotTokenInput,
    'telegram_chat_id' => $telegramChatIdInput,
    'payment_reference' => $paymentReferenceInput,
    'payment_notice' => $paymentNoticeInput,
);

if ($errors) {
    $_SESSION['application_errors'] = $errors;
    $_SESSION['application_old'] = $applicationOld;
} else {
    $_SESSION['application_old'] = array();
    if (!isset($_SESSION['application_success'])) {
        $_SESSION['application_success'] = 'Başvurunuz alındı.';
    }
}

if ($registerBankNotice) {
    $_SESSION['register_bank_transfer_notice'] = $registerBankNotice;
}

Helpers::redirect('/account/login?tab=application');
