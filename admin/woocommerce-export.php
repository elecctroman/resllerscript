<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Database;
use App\Helpers;
use App\Importers\WooCommerceExporter;

Auth::requireRoles(array('super_admin', 'admin', 'content'));

$currentUser = $_SESSION['user'];
$pdo = Database::connection();
$errors = array();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Geçersiz istek. Lütfen tekrar deneyin.';
    } else {
        AuditLog::record(
            $currentUser['id'],
            'product.export_csv',
            'product',
            null,
            'WooCommerce CSV dışa aktarımı başlatıldı'
        );
        WooCommerceExporter::stream($pdo);
    }
}

$totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$activeProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$categoryCount = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();

$pageTitle = 'WooCommerce Dışa Aktar';

include __DIR__ . '/../templates/header.php';
?>
<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">WooCommerce CSV Dışa Aktar</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Tüm ürün ve kategorilerinizi WooCommerce tarafından desteklenen CSV formatında indirebilirsiniz. Dosya UTF-8 BOM içerecek şekilde hazırlanır.</p>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="export-stat">
                            <strong><?= Helpers::sanitize(number_format($totalProducts)) ?></strong>
                            <span class="text-muted">Toplam Ürün</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="export-stat">
                            <strong><?= Helpers::sanitize(number_format($activeProducts)) ?></strong>
                            <span class="text-muted">Aktif Ürün</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="export-stat">
                            <strong><?= Helpers::sanitize(number_format($categoryCount)) ?></strong>
                            <span class="text-muted">Kategori</span>
                        </div>
                    </div>
                </div>

                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <p class="text-muted small mb-0">Dosya WooCommerce > Ürünler > İçe Aktar ekranından doğrudan içeri alınabilir.</p>
                    <button type="submit" class="btn btn-primary btn-lg align-self-start">CSV Dosyasını İndir</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
