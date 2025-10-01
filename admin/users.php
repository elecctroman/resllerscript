<?php
require __DIR__ . '/../bootstrap.php';

use App\AuditLog;
use App\Helpers;
use App\Database;
use App\Auth;
use App\Mailer;

Auth::requireRoles(array('super_admin', 'admin'));

$currentUser = $_SESSION['user'];
$pdo = Database::connection();
$errors = array();
$success = '';
$assignableRoles = Auth::assignableRoles($currentUser);
$roleFilter = isset($_GET['role']) ? $_GET['role'] : '';

if ($roleFilter && !in_array($roleFilter, Auth::roles(), true)) {
    $roleFilter = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $balance = isset($_POST['balance']) ? (float)$_POST['balance'] : 0;
        $role = isset($_POST['role']) ? $_POST['role'] : 'reseller';

        if (!$name || !$email || !$password) {
            $errors[] = 'İsim, e-posta ve şifre zorunludur.';
        }

        if (!in_array($role, $assignableRoles, true)) {
            $errors[] = 'Seçtiğiniz rol için yetkiniz bulunmuyor.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Bu e-posta adresi zaten kayıtlı.';
            }
        }

        if (!$errors) {
            $userId = null;
            try {
                $userId = Auth::createUser($name, $email, $password, $role, $balance);
            } catch (\Throwable $exception) {
                $errors[] = 'Bayi hesabı oluşturulurken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
                error_log('Bayi oluşturma hatası: ' . $exception->getMessage());
            }

            if (!$errors && $userId !== null) {
                if ($balance > 0) {
                    try {
                        $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')->execute([
                            'user_id' => $userId,
                            'amount' => $balance,
                            'type' => 'credit',
                            'description' => 'Başlangıç bakiyesi',
                        ]);
                    } catch (\Throwable $exception) {
                        error_log('Başlangıç bakiyesi kaydedilemedi: ' . $exception->getMessage());
                    }
                }

                $emailSent = true;
                try {
                    Mailer::send($email, 'Bayi Hesabınız Oluşturuldu', "Merhaba $name,\n\nBayi hesabınız oluşturulmuştur.\nKullanıcı adı: $email\nŞifre: $password\n\nPanele giriş yaparak işlemlerinize başlayabilirsiniz.");
                } catch (\Throwable $exception) {
                    error_log('Bayi oluşturma e-postası gönderilemedi: ' . $exception->getMessage());
                    $emailSent = false;
                }

                $success = $emailSent
                    ? 'Bayi hesabı oluşturuldu ve bilgilendirme e-postası gönderildi.'
                    : 'Bayi hesabı oluşturuldu ancak bilgilendirme e-postası gönderilemedi.';

                AuditLog::record(
                    $currentUser['id'],
                    'user.create',
                    'user',
                    $userId,
                    sprintf('Yeni kullanıcı: %s (%s)', $email, $role)
                );
            }
        }
    } elseif ($action === 'balance') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $type = isset($_POST['type']) ? $_POST['type'] : 'credit';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';

        $user = Auth::findUser($userId);
        if (!$user) {
            $errors[] = 'Bayi bulunamadı.';
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

            AuditLog::record(
                $currentUser['id'],
                'user.balance_adjust',
                'user',
                $userId,
                sprintf('Bakiye %s: %0.2f (%s)', $type, $amount, $description ?: 'Bakiye düzenlemesi')
            );
        }
    } elseif ($action === 'status') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $status = isset($_POST['status']) ? $_POST['status'] : 'active';

        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors[] = 'Geçersiz durum seçildi.';
        } else {
            $pdo->prepare('UPDATE users SET status = :status WHERE id = :id')->execute([
                'status' => $status,
                'id' => $userId,
            ]);
            $success = 'Bayi durumu güncellendi.';

            AuditLog::record(
                $currentUser['id'],
                'user.status_change',
                'user',
                $userId,
                sprintf('Durum %s olarak güncellendi', $status)
            );
        }
    } elseif ($action === 'role') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $newRole = isset($_POST['role']) ? $_POST['role'] : '';

        if (!in_array($newRole, Auth::roles(), true)) {
            $errors[] = 'Geçersiz rol seçildi.';
        } elseif (!in_array($newRole, $assignableRoles, true)) {
            $errors[] = 'Bu rolü atama yetkiniz bulunmuyor.';
        } else {
            $target = Auth::findUser($userId);
            if (!$target) {
                $errors[] = 'Kullanıcı bulunamadı.';
            } elseif ($target['role'] === 'super_admin' && $currentUser['role'] !== 'super_admin') {
                $errors[] = 'Süper yönetici rolü yalnızca süper yöneticiler tarafından güncellenebilir.';
            } else {
                $pdo->prepare('UPDATE users SET role = :role WHERE id = :id')->execute([
                    'role' => $newRole,
                    'id' => $userId,
                ]);

                if ($userId === $currentUser['id']) {
                    $_SESSION['user']['role'] = $newRole;
                }

                $success = 'Kullanıcı rolü güncellendi.';

                AuditLog::record(
                    $currentUser['id'],
                    'user.role_change',
                    'user',
                    $userId,
                    sprintf('Rol %s olarak güncellendi', $newRole)
                );
            }
        }
    }
}

$userQuery = 'SELECT * FROM users';
$conditions = array();
$params = array();

if ($roleFilter) {
    $conditions[] = 'role = :role';
    $params['role'] = $roleFilter;
}

if ($currentUser['role'] !== 'super_admin') {
    $conditions[] = "role != 'super_admin'";
}

if ($conditions) {
    $userQuery .= ' WHERE ' . implode(' AND ', $conditions);
}

$userQuery .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($userQuery);
$stmt->execute($params);
$users = $stmt->fetchAll();
$pageTitle = 'Bayi Yönetimi';
include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Bayi Oluştur</h5>
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
                        <select class="form-select" name="role">
                            <?php foreach ($assignableRoles as $roleOption): ?>
                                <option value="<?= htmlspecialchars($roleOption, ENT_QUOTES, 'UTF-8') ?>" <?= $roleOption === 'reseller' ? 'selected' : '' ?>><?= Helpers::sanitize(Auth::roleLabel($roleOption)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Başlangıç Bakiyesi</label>
                        <input type="number" step="0.01" class="form-control" name="balance" value="0">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Bayi Oluştur</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Bayiler</h5>
                <form method="get" class="d-flex align-items-center gap-2">
                    <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Tümü</option>
                        <?php foreach (Auth::roles() as $roleOption): ?>
                            <?php if ($currentUser['role'] !== 'super_admin' && $roleOption === 'super_admin') { continue; } ?>
                            <option value="<?= htmlspecialchars($roleOption, ENT_QUOTES, 'UTF-8') ?>" <?= $roleFilter === $roleOption ? 'selected' : '' ?>><?= Helpers::sanitize(Auth::roleLabel($roleOption)) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                                <td><?= Helpers::sanitize(Auth::roleLabel($user['role'])) ?></td>
                                <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$user['balance'])) ?></td>
                                <td>
                                    <?php if ($user['status'] === 'active'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#balanceModal<?= (int)$user['id'] ?>">Bakiye</button>
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal<?= (int)$user['id'] ?>">Durum</button>
                                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#roleModal<?= (int)$user['id'] ?>">Rol</button>
                                </td>
                            </tr>

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
                                                        <option value="debit">Bakiye Düş</option>
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
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                                <button type="submit" class="btn btn-primary">Güncelle</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="statusModal<?= (int)$user['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Bayi Durumu</h5>
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

                            <div class="modal fade" id="roleModal<?= (int)$user['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Rol Güncelle</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="role">
                                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Rol</label>
                                                    <select name="role" class="form-select">
                                                        <?php foreach ($assignableRoles as $roleOption): ?>
                                                            <option value="<?= htmlspecialchars($roleOption, ENT_QUOTES, 'UTF-8') ?>" <?= $user['role'] === $roleOption ? 'selected' : '' ?>><?= Helpers::sanitize(Auth::roleLabel($roleOption)) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                                <button type="submit" class="btn btn-primary">Kaydet</button>
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
