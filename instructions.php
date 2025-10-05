<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if (Auth::isAdminRole($user['role'])) {
    Helpers::redirect('/admin/instructions.php');
}

$pdo = Database::connection();

try {
    $stmt = $pdo->prepare('SELECT id, title, summary, content, created_at FROM instructions WHERE is_active = 1 ORDER BY created_at DESC');
    $stmt->execute();
    $instructions = $stmt->fetchAll() ?: array();
} catch (\PDOException $exception) {
    $instructions = array();
}

$pageTitle = 'Talimatlar';

include __DIR__ . '/templates/header.php';
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 col-lg-8">
            <h1 class="h3 mb-1">Talimatlar</h1>
            <p class="text-muted mb-0">Ürünlerin kurulum ve kullanım adımlarını burada bulabilirsiniz.</p>
        </div>
    </div>

    <?php if (!$instructions): ?>
        <div class="alert alert-info">Şu anda görüntülenebilecek aktif talimat bulunmuyor. Lütfen daha sonra tekrar kontrol edin.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($instructions as $instruction): ?>
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div>
                                    <h2 class="h5 mb-1"><?= Helpers::sanitize($instruction['title']) ?></h2>
                                    <?php if (!empty($instruction['summary'])): ?>
                                        <p class="text-muted small mb-0"><?= Helpers::sanitize($instruction['summary']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-primary-subtle text-primary fw-semibold">Güncellendi <?= Helpers::sanitize(isset($instruction['created_at']) ? date('d.m.Y', strtotime($instruction['created_at'])) : '-') ?></span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="instruction-content small text-body-secondary">
                                    <?= nl2br(Helpers::sanitize($instruction['content'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/templates/footer.php';
