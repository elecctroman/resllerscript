<?php

declare(strict_types=1);

use App\Container;

require __DIR__ . '/../app/bootstrap.php';

$pdo = Container::db();
$logger = Container::logger('admin-sessions');

$messages = [];
$errors = [];

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    try {
        $stmt = $pdo->prepare('SELECT * FROM whatsapp_sessions WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($session) {
            $pdo->prepare('DELETE FROM whatsapp_sessions WHERE id = :id')->execute([':id' => $id]);
            $messages[] = 'Oturum silindi: ' . htmlspecialchars($session['label'], ENT_QUOTES, 'UTF-8');
            $sessionDir = $session['session_dir'] ?? null;
            if ($sessionDir && is_dir($sessionDir)) {
                deleteDirectory($sessionDir);
            }
        }
    } catch (\Throwable $exception) {
        $errors[] = 'Oturum silinemedi: ' . $exception->getMessage();
        $logger->error('Delete session failed', ['error' => $exception->getMessage()]);
    }
}

$stmt = $pdo->query('SELECT * FROM whatsapp_sessions ORDER BY created_at DESC');
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>WhatsApp Oturumları</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f4f6fb; }
        h1 { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 12px 16px; border-bottom: 1px solid #e4e9f2; text-align: left; }
        th { background: #f1f4f9; }
        a.button { display: inline-block; padding: 8px 14px; border-radius: 6px; background: #d9534f; color: #fff; text-decoration: none; }
        a.button:hover { background: #c9302c; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 10px; }
        .alert.success { background: #e6ffed; color: #046c4e; }
        .alert.error { background: #ffe5e5; color: #7a1222; }
    </style>
</head>
<body>
<h1>WhatsApp Oturumları</h1>

<?php foreach ($messages as $message): ?>
    <div class="alert success"><?php echo $message; ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endforeach; ?>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Etiket</th>
        <th>Status</th>
        <th>Son Görülme</th>
        <th>Oluşturulma</th>
        <th>İşlemler</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($sessions as $session): ?>
        <tr>
            <td><?php echo (int) $session['id']; ?></td>
            <td><?php echo htmlspecialchars($session['label'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($session['status'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($session['last_seen'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($session['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><a class="button" href="?delete=<?php echo (int) $session['id']; ?>" onclick="return confirm('Silmek istediğinize emin misiniz?');">Sil</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
<?php
function deleteDirectory(string $dir): void
{
    $files = scandir($dir);
    if ($files === false) {
        return;
    }
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}
