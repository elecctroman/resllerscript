<?php
session_start();


spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

$errors = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPassword = $_POST['db_password'] ?? '';
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';
    $telegramToken = trim($_POST['telegram_token'] ?? '');
    $telegramChatId = trim($_POST['telegram_chat_id'] ?? '');

    if (!$dbHost || !$dbName || !$dbUser) {
        $errors[] = 'Lütfen veritabanı bilgilerini eksiksiz girin.';
    }

    if (!$adminName || !$adminEmail || !$adminPassword) {
        $errors[] = 'Lütfen yönetici bilgilerini eksiksiz girin.';
    }

    if (!$errors) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);
            $pdo = new PDO($dsn, $dbUser, $dbPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $schema = file_get_contents(__DIR__ . '/schema.sql');
            $pdo->exec($schema);

            $passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, balance, status, created_at) VALUES (:name, :email, :password_hash, :role, 0, :status, NOW())');
            $stmt->execute([
                'name' => $adminName,
                'email' => $adminEmail,
                'password_hash' => $passwordHash,
                'role' => 'admin',
                'status' => 'active',
            ]);



            $configTemplate = <<<CONFIG
<?php
const DB_HOST = '%s';
const DB_NAME = '%s';
const DB_USER = '%s';
const DB_PASSWORD = '%s';
const TELEGRAM_BOT_TOKEN = '%s';
const TELEGRAM_CHAT_ID = '%s';
CONFIG;

            $configContent = sprintf(
                $configTemplate,
                addslashes($dbHost),
                addslashes($dbName),
                addslashes($dbUser),
                addslashes($dbPassword),
                addslashes($telegramToken),
                addslashes($telegramChatId)
            );


        } catch (Throwable $exception) {
            $errors[] = 'Kurulum sırasında bir hata oluştu: ' . $exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bayi Yönetim Sistemi - Kurulum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Bayi Yönetim Sistemi Kurulumu</h4>
                </div>
                <div class="card-body">

                        <?php if ($errors): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <form method="post" novalidate>
                            <h5 class="mt-3">Veritabanı Bilgileri</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Veritabanı Sunucusu</label>
                                    <input type="text" class="form-control" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost', ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Veritabanı Adı</label>
                                    <input type="text" class="form-control" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Veritabanı Kullanıcısı</label>
                                    <input type="text" class="form-control" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Veritabanı Şifresi</label>
                                    <input type="password" class="form-control" name="db_password" value="<?= htmlspecialchars($_POST['db_password'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>

                            <h5 class="mt-4">Yönetici Hesabı</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Ad Soyad</label>
                                    <input type="text" class="form-control" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">E-posta</label>
                                    <input type="email" class="form-control" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Şifre</label>
                                    <input type="password" class="form-control" name="admin_password" required>
                                </div>
                            </div>

                            <h5 class="mt-4">Opsiyonel Telegram Bilgileri</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Bot Token</label>
                                    <input type="text" class="form-control" name="telegram_token" value="<?= htmlspecialchars($_POST['telegram_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Chat ID</label>
                                    <input type="text" class="form-control" name="telegram_chat_id" value="<?= htmlspecialchars($_POST['telegram_chat_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>

                            <div class="mt-4 text-end">
                                <button type="submit" class="btn btn-primary">Kurulumu Tamamla</button>
                            </div>
                        </form>

                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
