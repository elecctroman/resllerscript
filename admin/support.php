<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Helpers;
use App\Database;
use App\Notifications\ResellerNotifier;

Auth::requireRoles(array('super_admin', 'admin', 'support'));

$currentUser = $_SESSION['user'];
$pdo = Database::connection();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'reply') {
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';

        if ($ticketId <= 0 || !$message) {
            $errors[] = 'Mesaj boş olamaz.';
        } else {
            $ticketStmt = $pdo->prepare('SELECT st.*, u.name AS user_name, u.email AS user_email, u.notify_support_replied, u.telegram_bot_token, u.telegram_chat_id FROM support_tickets st INNER JOIN users u ON st.user_id = u.id WHERE st.id = :id');
            $ticketStmt->execute(['id' => $ticketId]);
            $ticket = $ticketStmt->fetch();

            if (!$ticket) {
                $errors[] = 'Destek kaydı bulunamadı.';
            } else {
                $pdo->prepare('INSERT INTO support_messages (ticket_id, user_id, message, created_at) VALUES (:ticket_id, :user_id, :message, NOW())')->execute([
                    'ticket_id' => $ticketId,
                    'user_id' => $currentUser['id'],
                    'message' => $message,
                ]);

                $pdo->prepare("UPDATE support_tickets SET status = 'answered', updated_at = NOW() WHERE id = :id")->execute(['id' => $ticketId]);
                $success = 'Yanıt gönderildi.';

                AuditLog::record(
                    $currentUser['id'],
                    'support.reply',
                    'support_ticket',
                    $ticketId,
                    'Destek talebine yanıt verildi'
                );

                $customerEmail = isset($ticket['user_email']) ? (string)$ticket['user_email'] : '';
                if ($customerEmail !== '') {
                    $userPayload = array(
                        'email' => $customerEmail,
                        'name' => isset($ticket['user_name']) ? $ticket['user_name'] : null,
                        'notify_support_replied' => isset($ticket['notify_support_replied']) ? $ticket['notify_support_replied'] : null,
                        'telegram_bot_token' => isset($ticket['telegram_bot_token']) ? $ticket['telegram_bot_token'] : null,
                        'telegram_chat_id' => isset($ticket['telegram_chat_id']) ? $ticket['telegram_chat_id'] : null,
                    );

                    $ticketPayload = array(
                        'subject' => isset($ticket['subject']) ? $ticket['subject'] : '',
                    );

                    ResellerNotifier::sendSupportReply($userPayload, $ticketPayload, $message);
                }
            }
        }
    } elseif ($action === 'status') {
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $status = isset($_POST['status']) ? $_POST['status'] : 'open';

        if (!in_array($status, ['open', 'answered', 'closed'], true)) {
            $errors[] = 'Geçersiz durum seçildi.';
        } else {
            $pdo->prepare('UPDATE support_tickets SET status = :status, updated_at = NOW() WHERE id = :id')->execute([
                'status' => $status,
                'id' => $ticketId,
            ]);
            $success = 'Destek durumu güncellendi.';
        }
    } elseif ($action === 'customer_reply') {
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        if ($ticketId <= 0 || $message === '') {
            $errors[] = 'Müşteri destek mesajı boş olamaz.';
        } else {
            $ticketStmt = $pdo->prepare('SELECT ct.*, c.email FROM customer_tickets ct INNER JOIN customers c ON c.id = ct.customer_id WHERE ct.id = :id');
            $ticketStmt->execute(array(':id' => $ticketId));
            $ticket = $ticketStmt->fetch();
            if (!$ticket) {
                $errors[] = 'Müşteri destek kaydı bulunamadı.';
            } else {
                $pdo->prepare('INSERT INTO customer_ticket_replies (ticket_id, author_type, author_id, message, created_at) VALUES (:ticket, "admin", :author, :message, NOW())')
                    ->execute(array(':ticket' => $ticketId, ':author' => $currentUser['id'], ':message' => $message));
                $pdo->prepare('UPDATE customer_tickets SET status = "acik", updated_at = NOW() WHERE id = :id')
                    ->execute(array(':id' => $ticketId));
                $success = 'Müşteri destek yanıtı gönderildi.';
            }
        }
    } elseif ($action === 'customer_status') {
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $status = isset($_POST['status']) ? $_POST['status'] : 'acik';
        if (!in_array($status, array('acik', 'kapali'), true)) {
            $errors[] = 'Geçersiz müşteri destek durumu seçildi.';
        } else {
            $pdo->prepare('UPDATE customer_tickets SET status = :status, updated_at = NOW() WHERE id = :id')->execute(array(':status' => $status, ':id' => $ticketId));
            $success = 'Müşteri destek durumu güncellendi.';
        }
    }
}

$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$query = 'SELECT st.*, u.name AS user_name, u.email FROM support_tickets st INNER JOIN users u ON st.user_id = u.id';
$params = [];

if ($statusFilter && in_array($statusFilter, ['open', 'answered', 'closed'], true)) {
    $query .= ' WHERE st.status = :status';
    $params['status'] = $statusFilter;
}

$query .= ' ORDER BY st.created_at DESC';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$customerTickets = $pdo->query('SELECT ct.*, c.email, CONCAT(c.name, " ", c.surname) AS customer_name FROM customer_tickets ct INNER JOIN customers c ON c.id = ct.customer_id ORDER BY ct.created_at DESC LIMIT 200')->fetchAll();

$pageTitle = 'Destek Yönetimi';
include __DIR__ . '/../templates/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Destek Talepleri</h5>
        <form method="get" class="d-flex gap-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Tümü</option>
                <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Açık</option>
                <option value="answered" <?= $statusFilter === 'answered' ? 'selected' : '' ?>>Yanıtlandı</option>
                <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Kapalı</option>
            </select>
        </form>
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

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Bayi</th>
                    <th>Konu</th>
                    <th>Öncelik</th>
                    <th>Durum</th>
                    <th>Oluşturma</th>
                    <th class="text-end">İşlemler</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td><?= (int)$ticket['id'] ?></td>
                        <td>
                            <strong><?= Helpers::sanitize($ticket['user_name']) ?></strong><br>
                            <small class="text-muted"><?= Helpers::sanitize($ticket['email']) ?></small>
                        </td>
                        <td><?= Helpers::sanitize($ticket['subject']) ?></td>
                        <td><?= strtoupper(Helpers::sanitize($ticket['priority'])) ?></td>
                        <td><span class="badge-status <?= Helpers::sanitize($ticket['status']) ?>"><?= strtoupper(Helpers::sanitize($ticket['status'])) ?></span></td>
                        <td><?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#ticketDetail<?= (int)$ticket['id'] ?>">Görüntüle</button>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ticketStatus<?= (int)$ticket['id'] ?>">Durum</button>
                        </td>
                    </tr>

                    <div class="modal fade" id="ticketDetail<?= (int)$ticket['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Destek Talebi Detayı</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <?php
                                    $messages = $pdo->prepare('SELECT sm.*, u.name, u.role FROM support_messages sm LEFT JOIN users u ON sm.user_id = u.id WHERE ticket_id = :ticket_id ORDER BY sm.created_at ASC');
                                    $messages->execute(['ticket_id' => $ticket['id']]);
                                    $messageRows = $messages->fetchAll();
                                    ?>
                                    <div class="mb-3">
                                        <strong>Konu:</strong> <?= Helpers::sanitize($ticket['subject']) ?><br>
                                        <strong>Öncelik:</strong> <?= strtoupper(Helpers::sanitize($ticket['priority'])) ?><br>
                                        <strong>Durum:</strong> <?= strtoupper(Helpers::sanitize($ticket['status'])) ?>
                                    </div>
                                    <div class="bg-light p-3 rounded">
                                        <?php foreach ($messageRows as $message): ?>
                                            <?php $isStaff = isset($message['role']) && Auth::isAdminRole($message['role']); ?>
                                            <div class="ticket-message mb-3 <?= $isStaff ? 'admin' : '' ?>">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <?php
                                                    $authorName = isset($message['name']) ? $message['name'] : 'Sistem';
                                                    if ($isStaff) {
                                                        $authorName = Auth::roleLabel($message['role']);
                                                    }
                                                    ?>
                                                    <strong><?= Helpers::sanitize($authorName) ?></strong>
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
                                            <label class="form-label">Yanıtınız</label>
                                            <textarea name="message" class="form-control" rows="3" required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Yanıt Gönder</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="ticketStatus<?= (int)$ticket['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="post">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Destek Durumu</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Durum</label>
                                            <select name="status" class="form-select">
                                                <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>Açık</option>
                                                <option value="answered" <?= $ticket['status'] === 'answered' ? 'selected' : '' ?>>Yanıtlandı</option>
                                                <option value="closed" <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>>Kapalı</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                        <button type="submit" class="btn btn-primary">Güncelle</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Müşteri Destek Talepleri</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Müşteri</th>
                        <th>Konu</th>
                        <th>Durum</th>
                        <th>Oluşturulma</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($customerTickets as $ticket): ?>
                    <tr>
                        <td>#<?= (int)$ticket['id'] ?></td>
                        <td><?= Helpers::sanitize($ticket['customer_name']) ?><br><small class="text-muted"><?= Helpers::sanitize($ticket['email']) ?></small></td>
                        <td><?= Helpers::sanitize($ticket['subject']) ?></td>
                        <td><span class="badge text-bg-secondary text-capitalize"><?= Helpers::sanitize($ticket['status']) ?></span></td>
                        <td><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($ticket['created_at']))) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#customerTicket<?= (int)$ticket['id'] ?>">Görüntüle</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($customerTickets as $ticket): ?>
    <div class="modal fade" id="customerTicket<?= (int)$ticket['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ticket #<?= (int)$ticket['id'] ?> - <?= Helpers::sanitize($ticket['subject']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?= nl2br(Helpers::sanitize($ticket['message'])) ?></p>
                    <?php
                    $replies = $pdo->prepare('SELECT * FROM customer_ticket_replies WHERE ticket_id = :ticket ORDER BY created_at ASC');
                    $replies->execute(array(':ticket' => $ticket['id']));
                    $replyRows = $replies->fetchAll();
                    ?>
                    <?php if ($replyRows): ?>
                        <div class="list-group mb-3">
                            <?php foreach ($replyRows as $reply): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= Helpers::sanitize(ucfirst($reply['author_type'])) ?></strong>
                                        <small class="text-muted"><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($reply['created_at']))) ?></small>
                                    </div>
                                    <div><?= nl2br(Helpers::sanitize($reply['message'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" class="mb-3">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                        <input type="hidden" name="action" value="customer_reply">
                        <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Yanıtınız</label>
                            <textarea name="message" class="form-control" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Yanıt Gönder</button>
                    </form>
                    <form method="post" class="text-end">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                        <input type="hidden" name="action" value="customer_status">
                        <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                        <select name="status" class="form-select d-inline-block w-auto">
                            <option value="acik" <?= $ticket['status'] === 'acik' ? 'selected' : '' ?>>Açık</option>
                            <option value="kapali" <?= $ticket['status'] === 'kapali' ? 'selected' : '' ?>>Kapalı</option>
                        </select>
                        <button type="submit" class="btn btn-outline-secondary ms-2">Durumu Güncelle</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../templates/footer.php';
