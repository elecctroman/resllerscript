<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Notifications\ResellerNotifier;
use App\Settings;
use App\Telegram;
use App\Payments\PaymentGatewayManager;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if (Auth::isAdminRole($user['role'])) {
    Helpers::redirect('/admin/balances.php');
}

if (!Helpers::featureEnabled('balance')) {
    Helpers::setFlash('warning', 'Bakiye işlemleri şu anda devre dışı.');
    Helpers::redirect('/dashboard.php');
}

$pdo = Database::connection();
$errors = [];
$flashSuccess = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
if ($flashSuccess !== '') {
    unset($_SESSION['flash_success']);
}
$bankTransferNotice = isset($_SESSION['bank_transfer_notice']) && is_array($_SESSION['bank_transfer_notice']) ? $_SESSION['bank_transfer_notice'] : array();
if ($bankTransferNotice) {
    unset($_SESSION['bank_transfer_notice']);
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

$autoTopupConfig = null;
try {
    $autoStmt = $pdo->prepare('SELECT threshold, topup_amount, payment_method, status FROM balance_auto_topups WHERE user_id = :user_id LIMIT 1');
    $autoStmt->execute(array('user_id' => $user['id']));
    $autoTopupConfig = $autoStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
} catch (\PDOException $exception) {
    $autoTopupConfig = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($amount <= 0) {
        $errors[] = 'Lütfen geçerli bir yükleme tutarı belirtin.';
    }

    $selectedGateway = isset($_POST['payment_provider']) ? trim($_POST['payment_provider']) : '';
    if ($selectedGateway === '' && $hasLiveGateway) {
        $selectedGateway = $defaultGateway;
    }

    if (!$paymentTestMode && (!$hasLiveGateway || !isset($gateways[$selectedGateway]))) {
        $errors[] = 'Şu anda aktif bir ödeme sağlayıcısı bulunamadı.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $methodLabel = $paymentTestMode ? 'Test Modu' : PaymentGatewayManager::getLabel($selectedGateway);
            $stmt = $pdo->prepare('INSERT INTO balance_requests (user_id, package_order_id, amount, payment_method, notes, status, created_at) VALUES (:user_id, :package_order_id, :amount, :payment_method, :notes, :status, NOW())');
            $stmt->execute([
                'user_id' => $user['id'],
                'package_order_id' => null,
                'amount' => $amount,
                'payment_method' => $methodLabel,
                'notes' => $notes ?: null,
                'status' => $paymentTestMode ? 'approved' : 'pending',
            ]);

            $requestId = (int)$pdo->lastInsertId();
            $displayReference = 'BAL-' . $requestId;

            if ($paymentTestMode) {
                $pdo->prepare('UPDATE balance_requests SET payment_provider = :provider, payment_reference = :reference, reference = :display_reference, processed_at = NOW() WHERE id = :id')
                    ->execute([
                        'provider' => 'test-mode',
                        'reference' => $displayReference,
                        'display_reference' => $displayReference,
                        'id' => $requestId,
                    ]);

                $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')
                    ->execute([
                        'user_id' => $user['id'],
                        'amount' => $amount,
                        'type' => 'credit',
                        'description' => 'Test modunda bakiye yükleme',
                    ]);

                $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')
                    ->execute([
                        'amount' => $amount,
                        'id' => $user['id'],
                    ]);

                $pdo->commit();
                $freshUser = Auth::findUser($user['id']);
                if ($freshUser) {
                    $_SESSION['user'] = $freshUser;
                    $user = $freshUser;

                    $requestPayload = array(
                        'amount' => $amount,
                        'payment_method' => $methodLabel,
                        'reference' => $displayReference,
                    );

                    ResellerNotifier::sendBalanceApproved($freshUser, $requestPayload, 'Test modu otomatik onaylandı.');
                }

                Telegram::notify(sprintf(
                    "💳 Test modunda bakiye yüklendi!\nBayi: %s\nTutar: %s\nTalep No: %s",
                    $user['name'],
                    Helpers::formatCurrency($amount, 'USD'),
                    $displayReference
                ));
                $_SESSION['flash_success'] = 'Test modu aktif olduğu için bakiye yüklemesi otomatik onaylandı. Bildirimler Telegram botunuza gönderildi.';
                Helpers::redirect('/balance.php');
            }

            if ($selectedGateway === 'bank-transfer') {
                $pdo->prepare('UPDATE balance_requests SET payment_provider = :provider, reference = :display_reference WHERE id = :id')
                    ->execute([
                        'provider' => $selectedGateway,
                        'display_reference' => $displayReference,
                        'id' => $requestId,
                    ]);

                $pdo->commit();
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
                $noticeLines[] = 'Tutar: ' . Helpers::formatCurrency($amount);
                $noticeLines[] = 'Talep No: ' . $displayReference;
                if (!empty($bankTransferDetails['instructions'])) {
                    $lines = preg_split('/\r\n|\r|\n/', $bankTransferDetails['instructions']);
                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if ($trimmed !== '') {
                            $noticeLines[] = $trimmed;
                        }
                    }
                }

                $_SESSION['flash_success'] = 'Bakiye talebiniz oluşturuldu. Havale/EFT talimatları Telegram botunuza gönderildi.';
                $_SESSION['bank_transfer_notice'] = $noticeLines;

                $freshUser = Auth::findUser($user['id']);
                if ($freshUser) {
                    $_SESSION['user'] = $freshUser;
                    $user = $freshUser;

                    $messageLines = array(
                        '💳 <b>Bakiye talimatı</b>',
                        '',
                        'Tutar: <b>' . htmlspecialchars(Helpers::formatCurrency($amount), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>',
                        'Talep No: <code>' . htmlspecialchars($displayReference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>',
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

                    $messageLines[] = '';
                    $messageLines[] = '✅ Ödeme sonrası dekontu iletmeyi unutmayın.';

                    ResellerNotifier::sendDirect($freshUser, implode("\n", $messageLines));
                }

                Telegram::notify(sprintf(
                    "💳 Yeni bakiye talebi alındı!\nBayi: %s\nTutar: %s\nYöntem: %s\nTalep No: %s",
                    $user['name'],
                    Helpers::formatCurrency($amount, 'USD'),
                    $methodLabel,
                    $displayReference
                ));

                Helpers::redirect('/balance.php');
            }

            $pdo->commit();

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $baseUrl = $scheme . '://' . $host;

            $gateway = PaymentGatewayManager::createGateway($selectedGateway);

            $description = Settings::get('cryptomus_description');
            if ($selectedGateway === 'heleket') {
                $description = Settings::get('heleket_description');
            }
            if ($description === null || $description === '') {
                $description = 'Bakiye yükleme talebi';
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
                $amount,
                'USD',
                $displayReference,
                $description,
                $user['email'],
                $successUrl ?: $baseUrl . '/balance.php',
                $failUrl ?: $baseUrl . '/balance.php',
                $callbackUrl
            );

            $paymentReference = isset($invoice['uuid']) ? $invoice['uuid'] : (isset($invoice['order_id']) ? $invoice['order_id'] : null);
            $paymentUrl = isset($invoice['url']) ? $invoice['url'] : null;

            $pdo->prepare('UPDATE balance_requests SET payment_provider = :provider, payment_reference = :reference, payment_url = :url, reference = :display_reference WHERE id = :id')
                ->execute([
                    'provider' => $selectedGateway,
                    'reference' => $paymentReference,
                    'url' => $paymentUrl,
                    'display_reference' => $displayReference,
                    'id' => $requestId,
                ]);

            Telegram::notify(sprintf(
                "💳 Yeni bakiye talebi alındı!\nBayi: %s\nTutar: %s\nYöntem: %s\nTalep No: %s",
                $user['name'],
                Helpers::formatCurrency($amount, 'USD'),
                $methodLabel,
                $displayReference
            ));

            $freshUser = Auth::findUser($user['id']);
            if ($freshUser) {
                $_SESSION['user'] = $freshUser;
                $user = $freshUser;

                $messageLines = array(
                    '💳 <b>Bakiye talebiniz oluşturuldu</b>',
                    '',
                    'Tutar: <b>' . htmlspecialchars(Helpers::formatCurrency($amount), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>',
                    'Talep No: <code>' . htmlspecialchars($displayReference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>',
                    'Ödeme Sağlayıcısı: <b>' . htmlspecialchars($methodLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>',
                );

                if ($paymentUrl) {
                    $messageLines[] = '';
                    $messageLines[] = '🔗 <a href="' . htmlspecialchars($paymentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Ödeme bağlantısını aç</a>';
                }

                ResellerNotifier::sendDirect($freshUser, implode("\n", $messageLines));
            }

            if ($paymentUrl) {
                Helpers::redirect($paymentUrl);
            }

            $errors[] = 'Ödeme bağlantısı oluşturulamadı. Lütfen tekrar deneyin.';
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($requestId) && $requestId > 0) {
                $pdo->prepare('DELETE FROM balance_requests WHERE id = :id')->execute(['id' => $requestId]);
            }
            $errors[] = 'Bakiye talebiniz kaydedilirken bir hata oluştu: ' . $exception->getMessage();
            $success = '';
        }
    }
}

$requests = [];
$transactions = [];

try {
    $requestStmt = $pdo->prepare('SELECT * FROM balance_requests WHERE user_id = :user_id ORDER BY created_at DESC');
    $requestStmt->execute(['user_id' => $user['id']]);
    $requests = $requestStmt->fetchAll();

    $transactionsStmt = $pdo->prepare('SELECT * FROM balance_transactions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20');
    $transactionsStmt->execute(['user_id' => $user['id']]);
    $transactions = $transactionsStmt->fetchAll();
} catch (\PDOException $exception) {
    $errors[] = 'Bakiye hareketleri yüklenirken bir veritabanı hatası oluştu. Lütfen yöneticiyle iletişime geçin.';
}

$pageTitle = 'Bakiye Yönetimi';

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Bakiye Yükleme</h5>
            </div>
            <div class="card-body">
                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($flashSuccess) ?></div>
                <?php endif; ?>
                <?php if ($bankTransferNotice): ?>
                    <div class="alert alert-info">
                        <h6 class="mb-2">Banka Havalesi Talimatı</h6>
                        <ul class="mb-0">
                            <?php foreach ($bankTransferNotice as $line): ?>
                                <li><?= Helpers::sanitize($line) ?></li>
                            <?php endforeach; ?>
                        </ul>
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

                <?php if (!$paymentTestMode && !$hasLiveGateway): ?>
                    <div class="alert alert-warning">Ödeme sağlayıcısı yapılandırılana kadar bakiye yükleme işlemi pasif durumdadır.</div>
                <?php endif; ?>

                <form method="post" class="vstack gap-3">
                    <div>
                        <label class="form-label"><?= Helpers::sanitize('Yüklenecek Tutar') ?> (<?= Helpers::sanitize(Helpers::currencySymbol()) ?>)</label>
                        <input type="number" step="0.01" min="1" name="amount" class="form-control" required>
                    </div>
                    <?php if ($paymentTestMode): ?>
                        <div class="alert alert-info">Test modu aktif. Ödeme sağlayıcısı seçiminizden bağımsız olarak işlemler otomatik onaylanır.</div>
                    <?php endif; ?>
                    <?php if ($hasLiveGateway): ?>
                        <div>
                            <label class="form-label">Ödeme Sağlayıcısı</label>
                            <?php foreach ($gateways as $identifier => $gateway): ?>
                                <div class="form-check">
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
                                    <input class="form-check-input" type="radio" name="payment_provider" id="gateway-<?= Helpers::sanitize($identifier) ?>" value="<?= Helpers::sanitize($identifier) ?>" <?= $checked ?>>
                                    <label class="form-check-label" for="gateway-<?= Helpers::sanitize($identifier) ?>"><?= Helpers::sanitize($gateway['label']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($bankTransferSummary): ?>
                            <div class="alert alert-secondary small">
                                <strong>Banka Havalesi Talimatı</strong>
                                <ul class="mb-0">
                                    <?php foreach ($bankTransferSummary as $line): ?>
                                        <li><?= Helpers::sanitize($line) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div>
                        <label class="form-label">Açıklama</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Ek bilgi iletmek isterseniz yazabilirsiniz."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" <?= (!$paymentTestMode && !$hasLiveGateway) ? 'disabled' : '' ?>>Ödemeyi Başlat</button>
                    <p class="text-muted small mb-0">Ödeme tamamlandığında işlem durumu otomatik olarak güncellenir.</p>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Otomatik Bakiye Yükleme</h5>
                <?php if ($autoTopupConfig): ?>
                    <span class="badge bg-success">Aktif</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Pasif</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <p class="text-muted small">Bakiye belirlediğiniz eşiğin altına düştüğünde otomatik olarak yükleme talimatı oluşturabilirsiniz.</p>
                <form id="autoTopupForm" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                    <div>
                        <label class="form-label">Minimum Bakiye (Eşik)</label>
                        <input type="number" step="0.01" min="1" class="form-control" name="threshold" value="<?= $autoTopupConfig ? Helpers::sanitize((string)$autoTopupConfig['threshold']) : '' ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Yüklenecek Tutar</label>
                        <input type="number" step="0.01" min="1" class="form-control" name="amount" value="<?= $autoTopupConfig ? Helpers::sanitize((string)$autoTopupConfig['topup_amount']) : '' ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Ödeme Yöntemi</label>
                        <select class="form-select" name="method" required>
                            <?php
                            $methods = array(
                                'stripe' => 'Stripe',
                                'paypal' => 'PayPal',
                                'iyzico' => 'Iyzico',
                                'paytr' => 'PayTR',
                                'bank-transfer' => 'Banka Havalesi',
                                'crypto-wallet' => 'Kripto Cüzdan',
                            );
                            $activeMethod = $autoTopupConfig ? (string)$autoTopupConfig['payment_method'] : '';
                            foreach ($methods as $value => $label):
                                $selected = $value === $activeMethod ? 'selected' : '';
                                ?>
                                <option value="<?= Helpers::sanitize($value) ?>" <?= $selected ?>><?= Helpers::sanitize($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary flex-grow-1" data-auto-topup-save>Kaydet</button>
                        <button type="button" class="btn btn-outline-danger" data-auto-topup-remove <?= $autoTopupConfig ? '' : 'disabled' ?>>Kapat</button>
                    </div>
                    <div class="alert d-none" role="alert" data-auto-topup-feedback></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Bakiye Talepleri</h5>
            </div>
            <div class="card-body">
                <?php if (!$requests): ?>
                    <p class="text-muted mb-0">Henüz bir bakiye talebi oluşturmadınız.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Tutar</th>
                                <th>Yöntem</th>
                                <th>Referans</th>
                                <th>Durum</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime($request['created_at'])) ?></td>
                                    <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$request['amount'])) ?></td>
                                    <td><?= Helpers::sanitize($request['payment_method']) ?></td>
                                    <?php
                                    $displayReference = '-';
                                    if (!empty($request['payment_reference'])) {
                                        $displayReference = $request['payment_reference'];
                                    } elseif (!empty($request['reference'])) {
                                        $displayReference = $request['reference'];
                                    }
                                    ?>
                                    <td><?= Helpers::sanitize($displayReference) ?></td>
                                    <td><span class="badge-status <?= Helpers::sanitize($request['status']) ?>"><?= strtoupper(Helpers::sanitize($request['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Son İşlemler</h5>
            </div>
            <div class="card-body">
                <?php if (!$transactions): ?>
                    <p class="text-muted mb-0">Herhangi bir hareket bulunamadı.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Tutar</th>
                                <th>Tip</th>
                                <th>Açıklama</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime($transaction['created_at'])) ?></td>
                                    <td><?= Helpers::sanitize($transaction['type'] === 'credit' ? '+' : '-') ?><?= Helpers::sanitize(Helpers::formatCurrency((float)$transaction['amount'])) ?></td>
                                    <td><?= strtoupper(Helpers::sanitize($transaction['type'])) ?></td>
                                    <td><?= Helpers::sanitize(isset($transaction['description']) ? $transaction['description'] : '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('autoTopupForm');
        if (!form) { return; }
        var csrfToken = form.querySelector('input[name="csrf_token"]').value;
        var saveButton = form.querySelector('[data-auto-topup-save]');
        var removeButton = form.querySelector('[data-auto-topup-remove]');
        var feedback = form.querySelector('[data-auto-topup-feedback]');

        function showFeedback(type, message) {
            if (!feedback) { return; }
            feedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
            feedback.classList.add('alert-' + type);
            feedback.textContent = message;
        }

        function submitAutoTopup(action) {
            var formData = new URLSearchParams();
            formData.append('action', action);
            formData.append('csrf_token', csrfToken);
            if (action === 'save_auto_topup') {
                formData.append('threshold', form.elements.threshold.value);
                formData.append('amount', form.elements.amount.value);
                formData.append('method', form.elements.method.value);
            }

            fetch('/reseller-actions.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('İstek başarısız (' + response.status + ')');
                }
                return response.json();
            }).then(function (data) {
                if (!data.success) {
                    throw new Error(data.error || 'İşlem tamamlanamadı');
                }
                if (action === 'save_auto_topup') {
                    showFeedback('success', 'Otomatik bakiye yükleme talimatınız kaydedildi.');
                    removeButton.disabled = false;
                } else {
                    showFeedback('warning', 'Otomatik bakiye yükleme devre dışı bırakıldı.');
                    removeButton.disabled = true;
                }
            }).catch(function (error) {
                showFeedback('danger', error.message);
            });
        }

        if (saveButton) {
            saveButton.addEventListener('click', function (event) {
                event.preventDefault();
                submitAutoTopup('save_auto_topup');
            });
        }

        if (removeButton) {
            removeButton.addEventListener('click', function (event) {
                event.preventDefault();
                if (removeButton.disabled) { return; }
                submitAutoTopup('remove_auto_topup');
            });
        }
    });
</script>
<?php include __DIR__ . '/templates/footer.php';
