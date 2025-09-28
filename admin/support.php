<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;
use App\Database;

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    Helpers::redirect('/');
}

$pdo = Database::connection();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'reply') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if ($ticketId <= 0 || !$message) {
            $errors[] = 'Mesaj boş olamaz.';
        } else {
            $ticketStmt = $pdo->prepare('SELECT * FROM support_tickets WHERE id = :id');
            $ticketStmt->execute(['id' => $ticketId]);
            $ticket = $ticketStmt->fetch();

            if (!$ticket) {
                $errors[] = 'Destek kaydı bulunamadı.';
            } else {
                $pdo->prepare('INSERT INTO support_messages (ticket_id, user_id, message, created_at) VALUES (:ticket_id, :user_id, :message, NOW())')->execute([
                    'ticket_id' => $ticketId,
                    'user_id' => $_SESSION['user']['id'],
                    'message' => $message,
                ]);

                $pdo->prepare("UPDATE support_tickets SET status = 'answered', updated_at = NOW() WHERE id = :id")->execute(['id' => $ticketId]);
                $success = 'Yanıt gönderildi.';
            }
        }
    } elseif ($action === 'status') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $status = $_POST['status'] ?? 'open';

        if (!in_array($status, ['open', 'answered', 'closed'], true)) {
            $errors[] = 'Geçersiz durum seçildi.';
        } else {
            $pdo->prepare('UPDATE support_tickets SET status = :status, updated_at = NOW() WHERE id = :id')->execute([
                'status' => $status,
                'id' => $ticketId,
            ]);
            $success = 'Destek durumu güncellendi.';
        }
    }
}

$statusFilter = $_GET['status'] ?? '';
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
                                            <div class="ticket-message mb-3 <?= $message['role'] === 'admin' ? 'admin' : '' ?>">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <strong><?= Helpers::sanitize($message['name'] ?? 'Sistem') ?></strong>
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
<?php include __DIR__ . '/../templates/footer.php';
