<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;
use App\Database;
use App\Auth;
use App\Mailer;

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    Helpers::redirect('/');
}

$pdo = Database::connection();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $balance = isset($_POST['balance']) ? (float)$_POST['balance'] : 0;

        if (!$name || !$email || !$password) {
            $errors[] = 'İsim, e-posta ve şifre zorunludur.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Bu e-posta adresi zaten kayıtlı.';
            }
        }

        if (!$errors) {
            $userId = Auth::createUser($name, $email, $password, 'reseller', $balance);

            if ($balance > 0) {
                $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')->execute([
                    'user_id' => $userId,
                    'amount' => $balance,
                    'type' => 'credit',
                    'description' => 'Başlangıç bakiyesi',
                ]);
            }

            Mailer::send($email, 'Bayi Hesabınız Oluşturuldu', "Merhaba $name,\n\nBayi hesabınız oluşturulmuştur.\nKullanıcı adı: $email\nŞifre: $password\n\nPanele giriş yaparak işlemlerinize başlayabilirsiniz.");
            $success = 'Bayi hesabı oluşturuldu ve bilgilendirme e-postası gönderildi.';
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
        }
    }
}

$users = $pdo->query("SELECT * FROM users WHERE role = 'reseller' ORDER BY created_at DESC")->fetchAll();
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
            <div class="card-header bg-white">
                <h5 class="mb-0">Bayiler</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>İsim</th>
                            <th>E-posta</th>
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
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
