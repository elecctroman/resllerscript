<?php
require_once __DIR__ . '/../includes/bootstrap.php';
redirect_if_not_authenticated();
$title = 'Destek Merkezi';
require __DIR__ . '/partials/header.php';
?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Yeni Ticket Oluştur</h2>
                <div class="alert alert-danger d-none" id="ticket-error"></div>
                <div class="alert alert-success d-none" id="ticket-success"></div>
                <form id="ticket-form">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label for="ticket-subject" class="form-label">Konu</label>
                        <input type="text" class="form-control" id="ticket-subject" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label for="ticket-message" class="form-label">Mesajınız</label>
                        <textarea class="form-control" id="ticket-message" name="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Gönder</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Ticket Geçmişi</h2>
            <button class="btn btn-outline-primary btn-sm" id="tickets-refresh"><i class="fa-solid fa-rotate"></i> Yenile</button>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="list-group" id="ticket-list"></div>
                <div class="text-center text-muted" id="tickets-empty">Henüz bir ticket açmadınız.</div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
