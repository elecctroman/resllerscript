<?php
require __DIR__ . '/bootstrap.php';

use App\Database;
use App\Helpers;
use App\Mailer;

if (!empty($_SESSION['user'])) {
    Helpers::redirect('/dashboard.php');
}

$pdo = Database::connection();
$packages = $pdo->query('SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC')->fetchAll();
$errors = [];
$success = false;
$selectedPackage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageId = (int)($_POST['package_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$packageId) {
        $errors[] = 'Lütfen bir paket seçin.';
    }

    if (!$name || !$email) {
        $errors[] = 'Ad soyad ve e-posta alanları zorunludur.';
    }

    $selectedPackage = null;
    foreach ($packages as $package) {
        if ((int)$package['id'] === $packageId) {
            $selectedPackage = $package;
            break;
        }
    }

    if (!$selectedPackage) {
        $errors[] = 'Seçilen paket bulunamadı veya aktif değil.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO package_orders (package_id, name, email, phone, company, notes, form_data, status, total_amount, created_at) VALUES (:package_id, :name, :email, :phone, :company, :notes, :form_data, :status, :total_amount, NOW())');
        $stmt->execute([
            'package_id' => $packageId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
            'notes' => $notes,
            'form_data' => json_encode($_POST, JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'total_amount' => $selectedPackage['price'],
        ]);

        $success = true;

        $adminEmails = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(\PDO::FETCH_COLUMN);
        $message = "Yeni bir bayilik başvurusu alındı.\n\n" .
            "Başvuru Sahibi: $name\n" .
            "E-posta: $email\n" .
            "Paket: {$selectedPackage['name']}\n" .


        foreach ($adminEmails as $adminEmail) {
            Mailer::send($adminEmail, 'Yeni Bayilik Başvurusu', $message);
        }
    }
}

include __DIR__ . '/templates/auth-header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 720px;">
        <div class="mb-4 text-center">
            <div class="brand">Bayi Başvurusu</div>
            <p class="text-muted">Aşağıdan uygun paketi seçerek başvurunuzu iletebilirsiniz.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                Başvurunuz bize ulaştı. Ödeme tamamlandığında hesabınız oluşturulup e-posta ile gönderilecektir.
            </div>
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

        <?php if (!$packages): ?>
            <div class="alert alert-warning">Şu anda başvuruya açık paket bulunmuyor. Lütfen daha sonra tekrar deneyin.</div>
        <?php endif; ?>

        <form method="post" class="row g-3">
            <div class="col-12">
                <label class="form-label">Paket Seçimi</label>
                <select name="package_id" class="form-select" required <?= !$packages ? 'disabled' : '' ?>>
                    <option value="">Paket seçiniz</option>
                    <?php foreach ($packages as $package): ?>
                        <option value="<?= (int)$package['id'] ?>" <?= ((int)($selectedPackage['id'] ?? 0) === (int)$package['id']) ? 'selected' : '' ?>>

                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Ad Soyad</label>
                <input type="text" class="form-control" name="name" value="<?= Helpers::sanitize($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">E-posta</label>
                <input type="email" class="form-control" name="email" value="<?= Helpers::sanitize($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Telefon</label>
                <input type="text" class="form-control" name="phone" value="<?= Helpers::sanitize($_POST['phone'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Firma Adı</label>
                <input type="text" class="form-control" name="company" value="<?= Helpers::sanitize($_POST['company'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Notlar</label>
                <textarea class="form-control" rows="3" name="notes" placeholder="Eklemek istediğiniz notlar..."><?= Helpers::sanitize($_POST['notes'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary w-100" <?= !$packages ? 'disabled' : '' ?>>Başvuruyu Gönder</button>
            </div>
            <div class="col-12 text-center">
                <a href="/" class="small">Giriş sayfasına dön</a>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/templates/auth-footer.php';
