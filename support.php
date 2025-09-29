<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Database;
use App\Telegram;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];
$pdo = Database::connection();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create_ticket') {
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        $priority = isset($_POST['priority']) ? $_POST['priority'] : 'normal';

        if (!$subject || !$message) {
            $errors[] = 'Konu ve mesaj alanları zorunludur.';
        } else {
            try {
                $pdo->prepare('INSERT INTO support_tickets (user_id, subject, priority, status, created_at) VALUES (:user_id, :subject, :priority, :status, NOW())')->execute([
                    'user_id' => $user['id'],
                    'subject' => $subject,
                    'priority' => $priority,
                    'status' => 'open',
                ]);

                $ticketId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO support_messages (ticket_id, user_id, message, created_at) VALUES (:ticket_id, :user_id, :message, NOW())')->execute([
                    'ticket_id' => $ticketId,
                    'user_id' => $user['id'],
                    'message' => $message,
                ]);

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
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';

        if ($ticketId <= 0 || !$message) {
            $errors[] = 'Mesaj içeriği boş olamaz.';
        } else {
            try {
                $ticketStmt = $pdo->prepare('SELECT * FROM support_tickets WHERE id = :id AND user_id = :user_id');
                $ticketStmt->execute([
                    'id' => $ticketId,
                    'user_id' => $user['id'],
                ]);
                $ticket = $ticketStmt->fetch();

                if (!$ticket) {
                    $errors[] = 'Destek talebi bulunamadı.';
                } else {
                    $pdo->prepare('INSERT INTO support_messages (ticket_id, user_id, message, created_at) VALUES (:ticket_id, :user_id, :message, NOW())')->execute([
                        'ticket_id' => $ticketId,
                        'user_id' => $user['id'],
                        'message' => $message,
                    ]);

                    $pdo->prepare("UPDATE support_tickets SET status = 'open', updated_at = NOW() WHERE id = :id")->execute(['id' => $ticketId]);

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

$tickets = [];

try {
    $ticketStmt = $pdo->prepare('SELECT * FROM support_tickets WHERE user_id = :user_id ORDER BY created_at DESC');
    $ticketStmt->execute(['user_id' => $user['id']]);
    $tickets = $ticketStmt->fetchAll();

    foreach ($tickets as $index => $ticket) {
        $messages = $pdo->prepare('SELECT sm.*, u.role FROM support_messages sm LEFT JOIN users u ON sm.user_id = u.id WHERE sm.ticket_id = :ticket_id ORDER BY sm.created_at ASC');
        $messages->execute(['ticket_id' => $ticket['id']]);
        $tickets[$index]['messages'] = $messages->fetchAll();
    }
} catch (\PDOException $exception) {
    $errors[] = 'Destek talepleriniz yüklenirken bir hata oluştu. Lütfen yöneticiyle iletişime geçin.';
    $tickets = [];
}
$pageTitle = 'Destek Merkezi';

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Destek Talebi</h5>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
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

                <form method="post">
                    <input type="hidden" name="action" value="create_ticket">
                    <div class="mb-3">
                        <label class="form-label">Konu</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Öncelik</label>
                        <select name="priority" class="form-select">
                            <option value="low">Düşük</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">Yüksek</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mesaj</label>
                        <textarea name="message" class="form-control" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Talebi Gönder</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Destek Taleplerim</h5>
            </div>
            <div class="card-body">
                <?php if (!$tickets): ?>
                    <p class="text-muted mb-0">Henüz bir destek talebi oluşturmadınız.</p>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <?php $messageRows = isset($ticket['messages']) ? $ticket['messages'] : []; ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="mb-1">#<?= (int)$ticket['id'] ?> - <?= Helpers::sanitize($ticket['subject']) ?></h6>
                                    <span class="badge bg-light text-dark">Öncelik: <?= strtoupper(Helpers::sanitize($ticket['priority'])) ?></span>
                                    <span class="badge-status <?= Helpers::sanitize($ticket['status']) ?> ms-2">Durum: <?= strtoupper(Helpers::sanitize($ticket['status'])) ?></span>
                                </div>
                                <small class="text-muted">Oluşturma: <?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?></small>
                            </div>
                            <div class="p-3 bg-light rounded">
                                <?php foreach ($messageRows as $message): ?>
                                    <?php $isStaffMessage = isset($message['role']) && Auth::isAdminRole($message['role']); ?>
                                    <div class="ticket-message mb-3 <?= $isStaffMessage ? 'admin' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong><?= $isStaffMessage ? Helpers::sanitize('Destek Ekibi') : Helpers::sanitize($user['name']) ?></strong>
                                            <small class="text-muted"><?= date('d.m.Y H:i', strtotime($message['created_at'])) ?></small>
                                        </div>
                                        <p class="mb-0"><?= nl2br(Helpers::sanitize($message['message'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <form method="post" class="mt-3">
                                <input type="hidden" name="action" value="reply">
                                <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                                <div class="mb-3">
                                    <textarea name="message" class="form-control" rows="3" placeholder="Yanıtınızı yazın..." required></textarea>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small">Yeni mesajlar destek ekibine bildirilir.</span>
                                    <button type="submit" class="btn btn-outline-primary">Yanıt Gönder</button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';
