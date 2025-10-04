<?php
require __DIR__ . '/../bootstrap.php';

use App\Customers\CustomerAuth;
use App\Customers\TicketService;
use App\Helpers;

$customer = CustomerAuth::ensureCustomer();
$errors = array();
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
    } else {
        $subject = trim((string)($_POST['subject'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        if ($subject === '' || $message === '') {
            $errors[] = 'Konu ve mesaj alanları zorunludur.';
        } else {
            $ticketId = TicketService::create((int)$customer['id'], $subject, $message);
            $success = 'Destek talebiniz oluşturuldu. Ticket #' . $ticketId;
        }
    }
}

$tickets = TicketService::listForCustomer((int)$customer['id']);
$pageTitle = 'Destek';
require __DIR__ . '/../templates/customer-header.php';
?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card customer-card">
            <div class="card-header"><h5 class="card-title mb-0">Yeni Destek Talebi</h5></div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
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
                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                    <div class="col-12">
                        <label class="form-label">Konu</label>
                        <input type="text" class="form-control" name="subject" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Mesaj</label>
                        <textarea class="form-control" name="message" rows="5" required></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100">Talep Oluştur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card customer-card">
            <div class="card-header"><h5 class="card-title mb-0">Destek Kayıtları</h5></div>
            <div class="card-body">
                <?php if ($tickets): ?>
                    <div class="accordion" id="ticketAccordion">
                        <?php foreach ($tickets as $index => $ticket): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?= $ticket['id'] ?>">
                                    <button class="accordion-button<?= $index > 0 ? ' collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $ticket['id'] ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>">
                                        #<?= $ticket['id'] ?> - <?= Helpers::sanitize($ticket['subject']) ?>
                                        <span class="badge ms-2 text-bg-secondary text-capitalize"><?= Helpers::sanitize($ticket['status']) ?></span>
                                    </button>
                                </h2>
                                <div id="collapse<?= $ticket['id'] ?>" class="accordion-collapse collapse<?= $index === 0 ? ' show' : '' ?>" data-bs-parent="#ticketAccordion">
                                    <div class="accordion-body">
                                        <p class="mb-3"><?= nl2br(Helpers::sanitize($ticket['message'])) ?></p>
                                        <?php if (!empty($ticket['replies'])): ?>
                                            <div class="list-group">
                                                <?php foreach ($ticket['replies'] as $reply): ?>
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
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Henüz destek kaydınız bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../templates/customer-footer.php';
