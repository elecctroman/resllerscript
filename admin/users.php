<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;
use App\Database;
use App\Auth;
use App\Mailer;
use App\AuditLogger;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

Auth::requirePermission('manage_users');

$pdo = Database::connection();
$errors = [];
$success = '';
$currentUser = $_SESSION['user'];
$assignableRoles = Auth::assignableRoles($currentUser);
$filterRoles = $currentUser['role'] === 'super_admin' ? Auth::roleLabels() : $assignableRoles;
$roleFilter = $_GET['role'] ?? 'reseller';
$submittedRole = $_POST['role'] ?? 'reseller';

if (!isset($assignableRoles[$submittedRole])) {
    $submittedRole = 'reseller';
}

if ($roleFilter && $roleFilter !== 'all' && !isset($filterRoles[$roleFilter])) {
    $errors[] = 'Geçersiz rol filtresi seçildi. Bayi listesi varsayılana döndürüldü.';
    $roleFilter = 'reseller';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $balance = (float)($_POST['balance'] ?? 0);
        $role = $_POST['role'] ?? 'reseller';

        if (!$name || !$email || !$password) {
            $errors[] = 'İsim, e-posta ve şifre zorunludur.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Bu e-posta adresi zaten kayıtlı.';
            }
        }

        if (!isset($assignableRoles[$role])) {
            $errors[] = 'Geçersiz rol seçimi.';
        }

        if ($role !== 'reseller') {
            $balance = 0;
        }

        if (!$errors) {
            $userId = Auth::createUser($name, $email, $password, $role, $balance);

            if ($balance > 0) {
                $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')->execute([
                    'user_id' => $userId,
                    'amount' => $balance,
                    'type' => 'credit',
                    'description' => 'Başlangıç bakiyesi',
                ]);
            }

            Mailer::send($email, 'Hesabınız Oluşturuldu', "Merhaba $name,\n\n" . Auth::roleLabel($role) . " hesabınız oluşturulmuştur.\nKullanıcı adı: $email\nŞifre: $password\n\nPanele giriş yaparak işlemlerinize başlayabilirsiniz.");
            $success = 'Kullanıcı hesabı oluşturuldu ve bilgilendirme e-postası gönderildi.';

            AuditLogger::log('users.create', [
                'target_type' => 'user',
                'target_id' => $userId,
                'description' => 'Yeni kullanıcı oluşturuldu',
                'metadata' => [
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'initial_balance' => $balance,
                ],
            ]);
        }
    } elseif ($action === 'balance') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $type = $_POST['type'] ?? 'credit';
        $description = trim($_POST['description'] ?? '');

        $user = Auth::findUser($userId);
        if (!$user) {
            $errors[] = 'Bayi bulunamadı.';
        } elseif ($user['role'] !== 'reseller') {
            $errors[] = 'Yalnızca bayilerin bakiyesi düzenlenebilir.';
        } elseif ($amount <= 0) {
            $errors[] = 'Tutar sıfırdan büyük olmalıdır.';
        } else {
            $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')->execute([
                'user_id' => $userId,
                'amount' => $amount,
                'type' => $type,
                'description' => $description ?: 'Bakiye düzenlemesi',
            ]);

            if ($type === 'credit') {
                $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')->execute([
                    'amount' => $amount,
                    'id' => $userId,
                ]);
            } else {
                $pdo->prepare('UPDATE users SET balance = GREATEST(balance - :amount, 0) WHERE id = :id')->execute([
                    'amount' => $amount,
                    'id' => $userId,
                ]);
            }

            $success = 'Bakiye başarıyla güncellendi.';

            AuditLogger::log('users.balance_update', [
                'target_type' => 'user',
                'target_id' => $userId,
                'description' => 'Kullanıcı bakiyesi güncellendi',
                'metadata' => [
                    'amount' => $amount,
                    'type' => $type,
                    'description' => $description ?: 'Bakiye düzenlemesi',
                ],
            ]);
        }
    } elseif ($action === 'status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors[] = 'Geçersiz durum seçildi.';
        } else {
            $pdo->prepare('UPDATE users SET status = :status WHERE id = :id')->execute([
                'status' => $status,
                'id' => $userId,
            ]);
            $success = 'Bayi durumu güncellendi.';

            AuditLogger::log('users.status_update', [
                'target_type' => 'user',
                'target_id' => $userId,
                'description' => 'Kullanıcı durumu güncellendi',
                'metadata' => [
                    'status' => $status,
                ],
            ]);
        }
    }
}

$userQuery = 'SELECT * FROM users';
$queryParams = [];

if ($roleFilter !== 'all') {
    $userQuery .= ' WHERE role = :role';
    $queryParams['role'] = $roleFilter;
}

$userQuery .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($userQuery);
$stmt->execute($queryParams);
$users = $stmt->fetchAll();
$pageTitle = 'Kullanıcı Yönetimi';
include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Kullanıcı Oluştur</h5>
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
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Ad Soyad</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-posta</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Şifre</label>
                        <input type="text" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select name="role" class="form-select" required>
                            <?php foreach ($assignableRoles as $value => $label): ?>
                                <option value="<?= Helpers::sanitize($value) ?>" <?= $submittedRole === $value ? 'selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Yalnızca yetkili olduğunuz roller listelenir.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Başlangıç Bakiyesi</label>
                        <input type="number" step="0.01" class="form-control" name="balance" value="0">
                        <small class="text-muted">Başlangıç bakiyesi yalnızca bayi (reseller) rolleri için uygulanır.</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Kullanıcı Oluştur</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Kullanıcılar</h5>
                <form method="get" class="d-flex gap-2">
                    <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>Tüm Roller</option>
                        <?php foreach ($filterRoles as $value => $label): ?>
                            <option value="<?= Helpers::sanitize($value) ?>" <?= $roleFilter === $value ? 'selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($roleFilter !== 'reseller'): ?>
                        <a href="?role=reseller" class="btn btn-sm btn-outline-secondary">Sıfırla</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>İsim</th>
                            <th>E-posta</th>
                            <th>Rol</th>
                            <th>Bakiye</th>
                            <th>Durum</th>
                            <th>Oluşturma</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= (int)$user['id'] ?></td>
                                <td><?= Helpers::sanitize($user['name']) ?></td>
                                <td><?= Helpers::sanitize($user['email']) ?></td>
                                <td><span class="badge bg-light text-dark"><?= Helpers::sanitize(Auth::roleLabel($user['role'])) ?></span></td>
                                <td>$<?= number_format((float)$user['balance'], 2, '.', ',') ?></td>
                                <td>
                                    <?php if ($user['status'] === 'active'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></td>
                                <td class="text-end">
                                    <?php if ($user['role'] === 'reseller'): ?>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#balanceModal<?= (int)$user['id'] ?>">Bakiye</button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal<?= (int)$user['id'] ?>">Durum</button>
                                </td>
                            </tr>

                            <?php if ($user['role'] === 'reseller'): ?>
                                <div class="modal fade" id="balanceModal<?= (int)$user['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="post">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Bakiye Güncelle</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="balance">
                                                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">İşlem Tipi</label>
                                                        <select name="type" class="form-select">
                                                            <option value="credit">Bakiye Ekle</option>
                                                        <option value="debit">Bakiye Çıkar</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Tutar</label>
                                                        <input type="number" step="0.01" name="amount" class="form-control" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Açıklama</label>
                                                        <textarea name="description" class="form-control" rows="2" placeholder="İşlem açıklaması"></textarea>
                                                    </div>
                                                    <p class="text-muted small mb-0">Bakiye işlemleri kayıt altına alınır.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                                    <button type="submit" class="btn btn-primary">Güncelle</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="modal fade" id="statusModal<?= (int)$user['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Kullanıcı Durumu</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="status">
                                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Durum</label>
                                                    <select name="status" class="form-select">
                                                        <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                                                        <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Pasif</option>
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
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
