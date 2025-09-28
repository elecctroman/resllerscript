<?php
session_start();

$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

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

$configPath = __DIR__ . '/config/config.php';
$configDir = dirname($configPath);

if (file_exists($configPath)) {
    $_SESSION['flash_success'] = 'Kurulum zaten tamamlanmış durumda.';
    header('Location: /index.php');
    exit;
}

$errors = [];
$values = [
    'db_host' => $_POST['db_host'] ?? 'localhost',
    'db_name' => $_POST['db_name'] ?? '',
    'db_user' => $_POST['db_user'] ?? '',
    'db_password' => $_POST['db_password'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['db_host', 'db_name', 'db_user'] as $field) {
        if (trim($values[$field]) === '') {
            $errors[] = 'Lütfen tüm zorunlu alanları doldurun.';
            break;
        }
    }

    if (!$errors) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $values['db_host'], $values['db_name']);

        try {
            $pdo = new PDO($dsn, $values['db_user'], $values['db_password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            $errors[] = 'Veritabanına bağlanırken bir hata oluştu: ' . $exception->getMessage();
        }
    }

    if (!$errors) {
        $schemaPath = __DIR__ . '/schema.sql';
        if (!file_exists($schemaPath)) {
            $errors[] = 'schema.sql dosyası bulunamadı. Lütfen dosyanın mevcut olduğundan emin olun.';
        } else {
            $schemaSql = file_get_contents($schemaPath);
            if ($schemaSql === false) {
                $errors[] = 'schema.sql dosyası okunamadı. Dosya izinlerini kontrol edin.';
            }
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $statements = array_filter(array_map('trim', explode(';', $schemaSql)));
            foreach ($statements as $statement) {
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }

            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Veritabanı şeması uygulanırken bir hata oluştu: ' . $exception->getMessage();
        }
    }

    if (!$errors) {
        if (!is_dir($configDir)) {
            if (!mkdir($configDir, 0755, true) && !is_dir($configDir)) {
                $errors[] = 'config dizini oluşturulamadı. Lütfen yazma izinlerini kontrol edin.';
            }
        }

        if (!$errors) {
            $configTemplate = <<<'CONFIG'
<?php
const DB_HOST = '%s';
const DB_NAME = '%s';
const DB_USER = '%s';
const DB_PASSWORD = '%s';
CONFIG;

            $configContent = sprintf(
                $configTemplate,
                addslashes($values['db_host']),
                addslashes($values['db_name']),
                addslashes($values['db_user']),
                addslashes($values['db_password'])
            );

            if (file_put_contents($configPath, $configContent) === false) {
                $errors[] = 'config.php dosyası yazılamadı. Lütfen dizin izinlerini kontrol edin.';
            }
        }
    }

    if (!$errors) {
        $installRemoved = false;
        $installPath = __FILE__;

        if (is_writable(dirname($installPath))) {
            if (@unlink($installPath)) {
                $installRemoved = true;
            } else {
                $renamed = $installPath . '.bak';
                if (@rename($installPath, $renamed)) {
                    $installRemoved = true;
                }
            }
        }

        $_SESSION['flash_success'] = 'Kurulum başarıyla tamamlandı. Şimdi giriş yapabilirsiniz.';
        if (!$installRemoved) {
            $_SESSION['flash_warning'] = 'install.php silinemedi. Lütfen dosyayı manuel olarak kaldırın veya yeniden adlandırın.';
        }

        header('Location: /index.php');
        exit;
    }
}

include __DIR__ . '/templates/auth-header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="text-center mb-4">
            <div class="brand">Bayi Yönetim Sistemi</div>
            <p class="text-muted mt-2">Kurulumu tamamlamak için veritabanı bilgilerini girin</p>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="db_host" class="form-label">Veritabanı Sunucusu</label>
                <input type="text" class="form-control" id="db_host" name="db_host" required value="<?= htmlspecialchars($values['db_host'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="mb-3">
                <label for="db_name" class="form-label">Veritabanı Adı</label>
                <input type="text" class="form-control" id="db_name" name="db_name" required value="<?= htmlspecialchars($values['db_name'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="mb-3">
                <label for="db_user" class="form-label">Veritabanı Kullanıcısı</label>
                <input type="text" class="form-control" id="db_user" name="db_user" required value="<?= htmlspecialchars($values['db_user'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="mb-3">
                <label for="db_password" class="form-label">Veritabanı Şifresi</label>
                <input type="password" class="form-control" id="db_password" name="db_password" value="<?= htmlspecialchars($values['db_password'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <button type="submit" class="btn btn-primary w-100">Kurulumu Tamamla</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/templates/auth-footer.php';
