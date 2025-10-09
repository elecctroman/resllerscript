<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Telegram;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if (!Helpers::featureEnabled('support')) {
    Helpers::setFlash('warning', 'Destek sistemi şu anda devre dışı.');
    Helpers::redirect('/account/index.php');
}

$pdo = Database::connection();
$errors = array();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $csrfToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($csrfToken)) {
        $errors[] = 'Oturum doğrulama anahtarınız geçersiz. Lütfen sayfayı yenileyin.';
    } elseif ($action === 'create_ticket') {
        $subject = isset($_POST['subject']) ? trim((string)$_POST['subject']) : '';
        $message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';
        $priority = isset($_POST['priority']) ? (string)$_POST['priority'] : 'normal';

        if ($subject === '' || $message === '') {
            $errors[] = 'Konu ve mesaj alanları zorunludur.';
        } else {
            try {
                $pdo->prepare('INSERT INTO support_tickets (user_id, subject, priority, status, created_at) VALUES (:user_id, :subject, :priority, :status, NOW())')->execute(array(
                    'user_id' => $user['id'],
                    'subject' => $subject,
                    'priority' => $priority,
                    'status' => 'open',
                ));

                $ticketId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO support_messages (ticket_id, user_id, message, created_at) VALUES (:ticket_id, :user_id, :message, NOW())')->execute(array(
                    'ticket_id' => $ticketId,
                    'user_id' => $user['id'],
                    'message' => $message,
                ));

                Telegram::notify(sprintf(
                    "🎫 Yeni destek talebi oluşturuldu!\nBayi: %s\nKonu: %s\nÖncelik: %s\nTalep No: #%d",
                    $user['name'],
                    $subject,
                    strtoupper($priority),
                    $ticketId
                ));

                $success = 'Destek talebiniz oluşturuldu.';
            } catch (\PDOException $exception) {
                $errors[] = 'Destek talebiniz kaydedilirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
            }
        }
    } elseif ($action === 'reply') {
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';

        if ($ticketId <= 0 || $message === '') {
            $errors[] = 'Mesaj içeriği boş olamaz.';
        } else {
            try {
                $ticketStmt = $pdo->prepare('SELECT * FROM support_tickets WHERE id = :id AND user_id = :user_id');
                $ticketStmt->execute(array(
                    'id' => $ticketId,
                    'user_id' => $user['id'],
                ));
                $ticket = $ticketStmt->fetch();

                if (!$ticket) {
                    $errors[] = 'Destek talebi bulunamadı.';
                } else {
                    $pdo->prepare('INSERT INTO support_messages (ticket_id, user_id, message, created_at) VALUES (:ticket_id, :user_id, :message, NOW())')->execute(array(
                        'ticket_id' => $ticketId,
                        'user_id' => $user['id'],
                        'message' => $message,
                    ));

                    $pdo->prepare("UPDATE support_tickets SET status = 'open', updated_at = NOW() WHERE id = :id")
                        ->execute(array('id' => $ticketId));

                    Telegram::notify(sprintf(
                        "💬 Yeni destek yanıtı var!\nBayi: %s\nTalep No: #%d",
                        $user['name'],
                        $ticketId
                    ));

                    $success = 'Mesajınız gönderildi.';
                }
            } catch (\PDOException $exception) {
                $errors[] = 'Mesajınız kaydedilirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
            }
        }
    }
}

$tickets = array();

try {
    $ticketStmt = $pdo->prepare('SELECT * FROM support_tickets WHERE user_id = :user_id ORDER BY created_at DESC');
    $ticketStmt->execute(array('user_id' => $user['id']));
    $tickets = $ticketStmt->fetchAll();

    foreach ($tickets as $index => $ticket) {
        $messages = $pdo->prepare('SELECT sm.*, u.role FROM support_messages sm LEFT JOIN users u ON sm.user_id = u.id WHERE sm.ticket_id = :ticket_id ORDER BY sm.created_at ASC');
        $messages->execute(array('ticket_id' => $ticket['id']));
        $tickets[$index]['messages'] = $messages->fetchAll();
    }
} catch (\PDOException $exception) {
    $errors[] = 'Destek talepleriniz yüklenirken bir hata oluştu. Lütfen yöneticiyle iletişime geçin.';
    $tickets = array();
}

$pageTitle = 'Destek Taleplerim';
$pageDescription = 'Yeni destek talepleri oluşturun ve mevcut taleplerinizin durumlarını takip edin.';
$activeMenu = 'support';

ob_start();
?>
<div class="account-section">
    <div class="account-section__header">
        <h5 class="account-section__title">Yeni Destek Talebi Oluştur</h5>
        <span class="text-muted small">En kısa sürede yanıtlanacaktır.</span>
    </div>
    <?php if ($errors && !$success): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= Helpers::sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
    <?php endif; ?>
    <form method="post" class="row g-3">
        <input type="hidden" name="action" value="create_ticket">
        <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
        <div class="col-md-8">
            <label class="form-label">Konu</label>
            <input type="text" name="subject" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Öncelik</label>
            <select name="priority" class="form-select">
                <option value="low">Düşük</option>
                <option value="normal" selected>Normal</option>
                <option value="high">Yüksek</option>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Mesaj</label>
            <textarea name="message" rows="5" class="form-control" required></textarea>
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-success">Talebi Gönder</button>
        </div>
    </form>
</div>

<div class="account-section mt-5">
    <div class="account-section__header">
        <h5 class="account-section__title">Destek Taleplerim</h5>
        <span class="text-muted small">Güncel durumlarını aşağıdan takip edebilirsiniz.</span>
    </div>
    <?php if (!$tickets): ?>
        <p class="text-muted mb-0">Henüz bir destek talebi oluşturmadınız.</p>
    <?php else: ?>
        <?php foreach ($tickets as $ticket): ?>
            <?php $messageRows = isset($ticket['messages']) ? $ticket['messages'] : array(); ?>
            <div class="mb-4 border rounded p-3 shadow-sm">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-2 gap-2">
                    <div>
                        <h6 class="mb-1">#<?= (int)$ticket['id'] ?> - <?= Helpers::sanitize($ticket['subject']) ?></h6>
                        <span class="badge bg-light text-dark me-2">Öncelik: <?= strtoupper(Helpers::sanitize($ticket['priority'])) ?></span>
                        <span class="badge-status <?= Helpers::sanitize($ticket['status']) ?>">Durum: <?= strtoupper(Helpers::sanitize($ticket['status'])) ?></span>
                    </div>
                    <small class="text-muted">Oluşturma: <?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?></small>
                </div>
                <div class="account-ticket__messages">
                    <?php foreach ($messageRows as $message): ?>
                        <?php $isStaffMessage = isset($message['role']) && Auth::isAdminRole($message['role']); ?>
                        <div class="account-ticket__message <?= $isStaffMessage ? 'admin' : '' ?>">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong><?= $isStaffMessage ? Helpers::sanitize('Destek Ekibi') : Helpers::sanitize($user['name']) ?></strong>
                                <small class="text-muted"><?= date('d.m.Y H:i', strtotime($message['created_at'])) ?></small>
                            </div>
                            <p class="mb-0"><?= nl2br(Helpers::sanitize($message['message'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form method="post" class="mt-3">
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                    <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                    <div class="mb-2">
                        <textarea name="message" rows="3" class="form-control" placeholder="Yanıtınızı yazın..." required></textarea>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Yeni mesajlar destek ekibine bildirilir.</span>
                        <button type="submit" class="btn btn-outline-primary">Yanıt Gönder</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

require __DIR__ . '/../themes/store/default/account/layout.php';
