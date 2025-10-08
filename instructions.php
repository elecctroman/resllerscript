<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

$user = Auth::requireReseller();

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
        <div class="accordion" id="instructionsAccordion">
            <?php foreach ($instructions as $index => $instruction): ?>
                <?php
                $instructionId = (int) (isset($instruction['id']) ? $instruction['id'] : $index + 1);
                $headingId = 'instructionHeading' . $instructionId;
                $collapseId = 'instructionCollapse' . $instructionId;
                ?>
                <div class="accordion-item border-0 shadow-sm mb-3">
                    <h2 class="accordion-header" id="<?= Helpers::sanitize($headingId) ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= Helpers::sanitize($collapseId) ?>" aria-expanded="false" aria-controls="<?= Helpers::sanitize($collapseId) ?>">
                            <div class="d-flex flex-column flex-grow-1 text-start">
                                <span class="fw-semibold"><?= Helpers::sanitize($instruction['title']) ?></span>
                                <?php if (!empty($instruction['summary'])): ?>
                                    <small class="text-muted"><?= Helpers::sanitize($instruction['summary']) ?></small>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-primary-subtle text-primary ms-3">Güncellendi <?= Helpers::sanitize(isset($instruction['created_at']) ? date('d.m.Y', strtotime($instruction['created_at'])) : '-') ?></span>
                        </button>
                    </h2>
                    <div id="<?= Helpers::sanitize($collapseId) ?>" class="accordion-collapse collapse" aria-labelledby="<?= Helpers::sanitize($headingId) ?>" data-bs-parent="#instructionsAccordion">
                        <div class="accordion-body">
                            <div class="instruction-content small text-body-secondary">
                                <?= nl2br(Helpers::sanitize($instruction['content'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/templates/footer.php';
