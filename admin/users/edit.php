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

if (!$tableExists($pdo, 'users')) {
    Helpers::redirectWithFlash('/admin/users/index.php', array(
        'users.error' => 'Kullanıcı tablosu bulunamadı. Lütfen veritabanı kurulumunu doğrulayın.',
    ));
}

$buildRedirect = static function ($id, $source) {
    $query = array('id' => $id);
    if ($source && $source !== 'users') {
        $query['source'] = $source;
    }

    return '/admin/users/edit.php' . ($query ? '?' . http_build_query($query) : '');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $source = isset($_POST['source']) ? trim((string) $_POST['source']) : 'users';
    $redirectBack = $buildRedirect($userId, $source);

    if ($userId <= 0) {
        Helpers::redirectWithFlash('/admin/users/index.php', array('users.error' => 'Geçersiz üye bilgisi.'));
    }

    if (!Helpers::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        Helpers::setFlash('users.errors', array('Oturum doğrulaması başarısız oldu. Lütfen tekrar deneyin.'));
        Helpers::redirect($redirectBack);
    }

    if ($source !== 'users') {
        Helpers::setFlash('users.errors', array('Bu kayıt düzenlenmeden önce kullanıcı tablolarına aktarılmalıdır.'));
        Helpers::redirect($redirectBack);
    }

    $userStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute(array('id' => $userId));
    $existingUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingUser) {
        Helpers::redirectWithFlash('/admin/users/index.php', array('users.error' => 'Düzenlemek istediğiniz üye bulunamadı.'));
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $role = trim((string) ($_POST['role'] ?? ($existingUser['role'] ?? 'customer')));
    $status = trim((string) ($_POST['status'] ?? ($existingUser['status'] ?? 'active')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $company = trim((string) ($_POST['company'] ?? ''));
    $balanceInput = isset($_POST['balance']) ? (string) $_POST['balance'] : (string) ($existingUser['balance'] ?? '0');
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

    if ($balance < 0) {
        $errors[] = 'Bakiye değeri negatif olamaz.';
    }

    $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
    $emailCheck->execute(array('email' => $email, 'id' => $userId));
    if ($emailCheck->fetchColumn()) {
        $errors[] = 'Bu e-posta adresi başka bir üyeye ait.';
    }

    $formData = array(
        'id' => $userId,
        'source' => $source,
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
        Helpers::redirect($redirectBack);
    }

    $updates = array(
        'name = :name',
        'email = :email',
        'role = :role',
        'status = :status',
        'balance = :balance',
    );

    $params = array(
        'id' => $userId,
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'status' => $status,
        'balance' => $balance,
    );

    if ($password !== '') {
        if (mb_strlen($password) < 8) {
            $errors[] = 'Yeni parola en az 8 karakter olmalıdır.';
        } else {
            $updates[] = 'password_hash = :password_hash';
            $params['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }
    }

    if ($errors) {
        Helpers::setFlash('users.errors', $errors);
        Helpers::setFlash('users.form_data', $formData);
        Helpers::redirect($redirectBack);
    }

    if ($columnExists($pdo, 'users', 'phone')) {
        $updates[] = 'phone = :phone';
        $params['phone'] = $phone !== '' ? $phone : null;
    }

    if ($columnExists($pdo, 'users', 'company')) {
        $updates[] = 'company = :company';
        $params['company'] = $company !== '' ? $company : null;
    }

    if ($columnExists($pdo, 'users', 'updated_at')) {
        $updates[] = 'updated_at = NOW()';
    }

    try {
        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } catch (\Throwable $exception) {
        Helpers::setFlash('users.errors', array('Üye güncellenirken bir hata oluştu: ' . $exception->getMessage()));
        Helpers::setFlash('users.form_data', $formData);
        Helpers::redirect($redirectBack);
    }

    $flashes = array('users.success' => 'Üye bilgileri başarıyla güncellendi.');
    if (($existingUser['role'] === 'reseller' && $role === 'customer') || ($existingUser['role'] === 'customer' && $role === 'reseller')) {
        $flashes['users.warning'] = 'Rol değiştirildi. Bayi ve müşteri yetkileri buna göre güncellendi.';
    }

    Helpers::redirectWithFlash('/admin/users/index.php', $flashes);
}

$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$source = isset($_GET['source']) ? trim((string) $_GET['source']) : 'users';

if ($userId <= 0) {
    Helpers::redirectWithFlash('/admin/users/index.php', array('users.error' => 'Düzenlenecek üye seçilmedi.'));
}

if ($source !== 'users') {
    Helpers::redirectWithFlash('/admin/users/index.php', array('users.error' => 'Bu kayıt düzenlenmeden önce kullanıcı tablolarına aktarılmalıdır.'));
}

$userStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$userStmt->execute(array('id' => $userId));
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    Helpers::redirectWithFlash('/admin/users/index.php', array('users.error' => 'Üye kaydı bulunamadı.'));
}

$errors = Helpers::getFlash('users.errors', array());
$formData = Helpers::getFlash('users.form_data', null);
$successFlash = Helpers::getFlash('users.success', '');
$warningFlash = Helpers::getFlash('users.warning', '');

$roleOptions = array(
    'customer' => Auth::roleLabel('customer'),
    'reseller' => Auth::roleLabel('reseller'),
    'admin' => Auth::roleLabel('admin'),
);

$statusOptions = array(
    'active' => 'Aktif',
    'inactive' => 'Pasif',
);

$defaultValues = array(
    'id' => $userId,
    'source' => $source,
    'name' => isset($user['name']) ? (string) $user['name'] : '',
    'email' => isset($user['email']) ? (string) $user['email'] : '',
    'phone' => isset($user['phone']) ? (string) $user['phone'] : '',
    'company' => isset($user['company']) ? (string) $user['company'] : '',
    'role' => isset($user['role']) ? (string) $user['role'] : 'customer',
    'status' => isset($user['status']) ? (string) $user['status'] : 'active',
    'balance' => isset($user['balance']) ? number_format((float) $user['balance'], 2, '.', '') : '0.00',
);

$formValues = $formData !== null ? array_merge($defaultValues, $formData) : $defaultValues;
$csrfToken = Helpers::csrfToken();

include __DIR__ . '/../../templates/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Üye Düzenle</h1>
        <p class="text-muted mb-0">Üyenin bilgilerini, rolünü ve durumunu güncelleyebilirsiniz.</p>
    </div>
    <a href="/admin/users/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Üye listesine dön
    </a>
</div>

<?php if ($successFlash): ?>
    <div class="alert alert-success"><?= Helpers::sanitize($successFlash) ?></div>
<?php endif; ?>

<?php if ($warningFlash): ?>
    <div class="alert alert-warning"><?= Helpers::sanitize($warningFlash) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h2 class="h6 mb-2">Düzenleme tamamlanamadı</h2>
        <ul class="mb-0">
            <?php foreach ($errors as $errorMessage): ?>
                <li><?= Helpers::sanitize($errorMessage) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="alert alert-info">
    Rolü “Bayi” ile “Müşteri” arasında değiştirirseniz kullanıcının panel yetkileri buna göre güncellenecektir.
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="post" action="/admin/users/edit.php" class="row g-4">
            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int) $formValues['id'] ?>">
            <input type="hidden" name="source" value="<?= Helpers::sanitize($formValues['source']) ?>">

            <div class="col-12 col-md-6">
                <label for="user-name" class="form-label">Ad Soyad</label>
                <input type="text" class="form-control" id="user-name" name="name" value="<?= Helpers::sanitize($formValues['name']) ?>" required>
            </div>

            <div class="col-12 col-md-6">
                <label for="user-email" class="form-label">E-posta</label>
                <input type="email" class="form-control" id="user-email" name="email" value="<?= Helpers::sanitize($formValues['email']) ?>" required>
            </div>

            <div class="col-12 col-md-6">
                <label for="user-password" class="form-label">Parola</label>
                <input type="password" class="form-control" id="user-password" name="password" placeholder="Yeni parola (opsiyonel)">
                <small class="text-muted">Boş bırakırsanız mevcut parola korunur.</small>
            </div>

            <div class="col-12 col-md-6">
                <label for="user-phone" class="form-label">Telefon</label>
                <input type="tel" class="form-control" id="user-phone" name="phone" value="<?= Helpers::sanitize($formValues['phone']) ?>">
            </div>

            <div class="col-12 col-md-6">
                <label for="user-company" class="form-label">Firma</label>
                <input type="text" class="form-control" id="user-company" name="company" value="<?= Helpers::sanitize($formValues['company']) ?>">
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
                <label for="user-balance" class="form-label">Bakiye</label>
                <div class="input-group">
                    <span class="input-group-text">₺</span>
                    <input type="number" step="0.01" class="form-control" id="user-balance" name="balance" value="<?= Helpers::sanitize($formValues['balance']) ?>">
                </div>
                <small class="text-muted">Negatif değerler kabul edilmez.</small>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Değişiklikleri Kaydet
                </button>
                <a href="/admin/users/index.php" class="btn btn-link">İptal</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h6 mb-3">Üye Özeti</h2>
        <dl class="row mb-0 small">
            <dt class="col-sm-3 text-muted">Kayıt Tarihi</dt>
            <dd class="col-sm-9"><?= Helpers::sanitize(isset($user['created_at']) ? $user['created_at'] : '-') ?></dd>
            <dt class="col-sm-3 text-muted">Son Güncelleme</dt>
            <dd class="col-sm-9"><?= Helpers::sanitize(isset($user['updated_at']) ? $user['updated_at'] : '-') ?></dd>
            <dt class="col-sm-3 text-muted">Kaynak</dt>
            <dd class="col-sm-9">users</dd>
        </dl>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
