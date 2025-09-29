<?php
require __DIR__ . '/bootstrap.php';

use App\Helpers;
use App\Database;
use App\Auth;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];
$pdo = Database::connection();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_ticket') {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $priority = $_POST['priority'] ?? 'normal';

        if (!$subject || !$message) {
            $errors[] = 'Konu ve mesaj alanları zorunludur.';
        } else {

        }
    } elseif ($action === 'reply') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if ($ticketId <= 0 || !$message) {
            $errors[] = 'Mesaj içeriği boş olamaz.';
        } else {

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
                                    <div class="ticket-message mb-3 <?= Auth::isAdminRole($message['role'] ?? null) ? 'admin' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong><?= Auth::isAdminRole($message['role'] ?? null) ? 'Destek Ekibi' : Helpers::sanitize($user['name']) ?></strong>
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
