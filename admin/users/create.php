<?php
require __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

Auth::requireAdmin(array('super_admin', 'admin'));

$pdo = Database::connection();

$tableExists = static function ($pdoConnection, $tableName) {
    $stmt = $pdoConnection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute(array('table' => $tableName));

    return (int) $stmt->fetchColumn() > 0;
};

$columnExists = static function ($pdoConnection, $tableName, $columnName) {
    $stmt = $pdoConnection->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute(array('table' => $tableName, 'column' => $columnName));

    return (int) $stmt->fetchColumn() > 0;
};

$usersTableExists = $tableExists($pdo, 'users');
if (!$usersTableExists) {
    Helpers::redirectWithFlash('/admin/users/index.php', array(
        'users.error' => 'Kullanıcı tablosu bulunamadı. Lütfen veritabanı kurulumunu tamamlayın.',
    ));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($token)) {
        Helpers::setFlash('users.errors', array('Oturum doğrulaması başarısız oldu. Lütfen yeniden deneyin.'));
        Helpers::redirect('/admin/users/create.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $company = trim((string) ($_POST['company'] ?? ''));
    $role = trim((string) ($_POST['role'] ?? 'reseller'));
    $status = trim((string) ($_POST['status'] ?? 'active'));
    $balanceInput = isset($_POST['balance']) ? (string) $_POST['balance'] : '0';
    $balance = is_numeric($balanceInput) ? (float) $balanceInput : 0.0;

    $allowedRoles = array(
        'customer' => Auth::roleLabel('customer'),
        'reseller' => Auth::roleLabel('reseller'),
        'admin' => Auth::roleLabel('admin'),
    );

    $allowedStatuses = array(
        'active' => 'Aktif',
        'inactive' => 'Pasif',
    );

    $errors = array();

    if ($name === '') {
        $errors[] = 'Ad soyad alanı zorunludur.';
    }

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Geçerli bir e-posta adresi girin.';
    }

    if (!isset($allowedRoles[$role])) {
        $role = 'customer';
    }

    if (!isset($allowedStatuses[$status])) {
        $status = 'active';
    }

    if (mb_strlen($password) < 8) {
        $errors[] = 'Parola en az 8 karakter olmalıdır.';
    }

    if ($balance < 0) {
        $errors[] = 'Başlangıç bakiyesi negatif olamaz.';
    }

    $formData = array(
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'company' => $company,
        'role' => $role,
        'status' => $status,
        'balance' => number_format($balance, 2, '.', ''),
    );

    if ($errors) {
        Helpers::setFlash('users.errors', $errors);
        Helpers::setFlash('users.form_data', $formData);
        Helpers::redirect('/admin/users/create.php');
    }

    $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $emailCheck->execute(array('email' => $email));
    if ($emailCheck->fetchColumn()) {
        $errors[] = 'Bu e-posta adresi zaten kayıtlı.';
    }

    if ($tableExists($pdo, 'resellers')) {
        $legacyCheck = $pdo->prepare('SELECT id FROM resellers WHERE email = :email LIMIT 1');
        $legacyCheck->execute(array('email' => $email));
        if ($legacyCheck->fetchColumn()) {
            $errors[] = 'Bu e-posta adresi eski bayi kayıtlarında mevcut. Lütfen farklı bir e-posta girin.';
        }
    }

    if ($errors) {
        Helpers::setFlash('users.errors', $errors);
        Helpers::setFlash('users.form_data', $formData);
        Helpers::redirect('/admin/users/create.php');
    }

    $columns = array('name', 'email', 'password_hash', 'role', 'status', 'balance', 'created_at');
    $placeholders = array(':name', ':email', ':password_hash', ':role', ':status', ':balance', 'NOW()');
    $params = array(
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        'role' => $role,
        'status' => $status,
        'balance' => $balance,
    );

    if ($columnExists($pdo, 'users', 'phone')) {
        $columns[] = 'phone';
        $placeholders[] = ':phone';
        $params['phone'] = $phone !== '' ? $phone : null;
    }

    if ($columnExists($pdo, 'users', 'company')) {
        $columns[] = 'company';
        $placeholders[] = ':company';
        $params['company'] = $company !== '' ? $company : null;
    }

    if ($columnExists($pdo, 'users', 'updated_at')) {
        $columns[] = 'updated_at';
        $placeholders[] = 'NOW()';
    }

    try {
        $sql = sprintf('INSERT INTO users (%s) VALUES (%s)', implode(', ', $columns), implode(', ', $placeholders));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } catch (\Throwable $exception) {
        Helpers::setFlash('users.errors', array('Üye oluşturulurken bir hata oluştu: ' . $exception->getMessage()));
        Helpers::setFlash('users.form_data', $formData);
        Helpers::redirect('/admin/users/create.php');
    }

    Helpers::redirectWithFlash('/admin/users/index.php', array(
        'users.success' => 'Yeni üye başarıyla oluşturuldu.',
    ));
}

$errors = Helpers::getFlash('users.errors', array());
$formData = Helpers::getFlash('users.form_data', array());

$roleOptions = array(
    'customer' => Auth::roleLabel('customer'),
    'reseller' => Auth::roleLabel('reseller'),
    'admin' => Auth::roleLabel('admin'),
);

$statusOptions = array(
    'active' => 'Aktif',
    'inactive' => 'Pasif',
);

$defaultData = array(
    'name' => '',
    'email' => '',
    'phone' => '',
    'company' => '',
    'role' => 'customer',
    'status' => 'active',
    'balance' => '0.00',
);
$formValues = array_merge($defaultData, $formData);

$csrfToken = Helpers::csrfToken();

include __DIR__ . '/../../templates/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Yeni Üye Oluştur</h1>
        <p class="text-muted mb-0">Platforma yeni bir üye ekleyin ve rolünü belirleyin.</p>
    </div>
    <a href="/admin/users/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Üye listesine dön
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h2 class="h6 mb-2">Kayıt tamamlanamadı</h2>
        <ul class="mb-0">
            <?php foreach ($errors as $errorMessage): ?>
                <li><?= Helpers::sanitize($errorMessage) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="post" action="/admin/users/create.php" class="row g-4">
            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
            <div class="col-12 col-md-6">
                <label for="user-name" class="form-label">Ad Soyad</label>
                <input type="text" class="form-control" id="user-name" name="name" value="<?= Helpers::sanitize($formValues['name']) ?>" placeholder="Örn. Ayşe Yılmaz" required>
            </div>
            <div class="col-12 col-md-6">
                <label for="user-email" class="form-label">E-posta</label>
                <input type="email" class="form-control" id="user-email" name="email" value="<?= Helpers::sanitize($formValues['email']) ?>" placeholder="ornek@site.com" required>
            </div>
            <div class="col-12 col-md-6">
                <label for="user-password" class="form-label">Parola</label>
                <input type="password" class="form-control" id="user-password" name="password" placeholder="En az 8 karakter" required>
            </div>
            <div class="col-12 col-md-6">
                <label for="user-phone" class="form-label">Telefon</label>
                <input type="tel" class="form-control" id="user-phone" name="phone" value="<?= Helpers::sanitize($formValues['phone']) ?>" placeholder="5XX XXX XX XX">
            </div>
            <div class="col-12 col-md-6">
                <label for="user-company" class="form-label">Firma</label>
                <input type="text" class="form-control" id="user-company" name="company" value="<?= Helpers::sanitize($formValues['company']) ?>" placeholder="Şirket adı (opsiyonel)">
            </div>
            <div class="col-12 col-md-6">
                <label for="user-role" class="form-label">Rol</label>
                <select id="user-role" name="role" class="form-select" required>
                    <?php foreach ($roleOptions as $value => $label): ?>
                        <option value="<?= Helpers::sanitize($value) ?>"<?= $formValues['role'] === $value ? ' selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label for="user-status" class="form-label">Durum</label>
                <select id="user-status" name="status" class="form-select" required>
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= Helpers::sanitize($value) ?>"<?= $formValues['status'] === $value ? ' selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label for="user-balance" class="form-label">Başlangıç Bakiyesi</label>
                <div class="input-group">
                    <span class="input-group-text">₺</span>
                    <input type="number" step="0.01" min="0" class="form-control" id="user-balance" name="balance" value="<?= Helpers::sanitize($formValues['balance']) ?>">
                </div>
                <small class="text-muted">İsteğe bağlı. Bakiye hareketi kaydı ileride eklenecektir.</small>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Kaydet
                </button>
                <button type="button" class="btn btn-outline-secondary" disabled>Kaydet ve davet gönder (yakında)</button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
