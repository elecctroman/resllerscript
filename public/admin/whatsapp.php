<?php

declare(strict_types=1);

use App\Container;
use App\Environment;
use App\Services\NotificationService;
use App\WhatsApp\Gateway;

require __DIR__ . '/../../app/bootstrap.php';

$pdo = Container::db();
$redis = Container::redis();
$logger = Container::logger('admin-ui');

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_session') {
        $label = trim((string) ($_POST['label'] ?? ''));
        if ($label === '') {
            $errors[] = 'Oturum etiketi gereklidir.';
        } else {
            $clientId = 'session_' . bin2hex(random_bytes(5));
            $sessionBase = Environment::get('WHATSAPP_SESSION_DIR_BASE', dirname(__DIR__, 2) . '/storage/sessions');
            $sessionDir = rtrim($sessionBase ?? '', '/') . '/' . $clientId;
            if (!is_dir($sessionDir)) {
                mkdir($sessionDir, 0775, true);
            }

            $stmt = $pdo->prepare('INSERT INTO whatsapp_sessions (label, client_id, status, session_dir, created_at, updated_at) VALUES (:label, :client_id, :status, :session_dir, NOW(), NOW())');
            $stmt->execute([
                ':label' => $label,
                ':client_id' => $clientId,
                ':status' => 'pending',
                ':session_dir' => $sessionDir,
            ]);

            header('Location: whatsapp.php?session_id=' . (int) $pdo->lastInsertId());
            exit;
        }
    } elseif ($action === 'test_send') {
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        if (!preg_match('/^\+?[1-9]\d{7,14}$/', $phone)) {
            $errors[] = 'Telefon numarası E.164 formatında olmalıdır.';
        } elseif ($message === '') {
            $errors[] = 'Mesaj boş olamaz.';
        } else {
            $service = new NotificationService($pdo, $redis, $logger);
            try {
                $service->queueMessage(null, $phone, 'test', $message, ['trigger' => 'admin_test']);
                $messages[] = 'Test mesajı kuyruğa alındı.';
            } catch (\Throwable $exception) {
                $errors[] = 'Mesaj kuyruğa alınamadı: ' . $exception->getMessage();
                $logger->error('Test send failed', ['error' => $exception->getMessage()]);
            }
        }
    }
}

$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : null;

$stmt = $pdo->query('SELECT * FROM whatsapp_sessions ORDER BY created_at DESC');
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$currentSession = null;
foreach ($sessions as $session) {
    if ($sessionId !== null && (int) $session['id'] === $sessionId) {
        $currentSession = $session;
        break;
    }
}
if ($currentSession === null && count($sessions) > 0) {
    $currentSession = $sessions[0];
}

$statusData = null;
if ($currentSession !== null) {
    try {
        $gateway = new Gateway($pdo, $logger, $currentSession);
        $statusData = $gateway->getStatus();
    } catch (\Throwable $exception) {
        $errors[] = 'Gateway durumu alınamadı: ' . $exception->getMessage();
        $logger->error('Gateway status error', ['error' => $exception->getMessage()]);
    }
}

?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>WhatsApp Gateway Yönetimi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f7f9fc; }
        header { margin-bottom: 20px; }
        .card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .sessions { display: flex; gap: 12px; flex-wrap: wrap; }
        .session { padding: 12px 16px; border-radius: 6px; border: 1px solid #dce3f0; background: #fff; text-decoration: none; color: #1a1f36; }
        .session.active { background: #0052cc; color: #fff; border-color: #0052cc; }
        .status { font-size: 14px; margin-top: 8px; }
        .messages { margin-bottom: 20px; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 10px; }
        .alert.success { background: #e6ffed; color: #046c4e; }
        .alert.error { background: #ffe5e5; color: #7a1222; }
        form label { display: block; font-weight: bold; margin-bottom: 6px; }
        form input[type="text"], form textarea { width: 100%; padding: 10px; border: 1px solid #c5d0e6; border-radius: 6px; }
        form textarea { min-height: 120px; }
        button { background: #0052cc; color: #fff; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer; }
        button:hover { background: #0747a6; }
        .qr img { max-width: 260px; border: 10px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .grid { display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
    </style>
</head>
<body>
<header>
    <h1>WhatsApp Gateway Yönetimi</h1>
    <p>QR kodu tarayarak WhatsApp oturumunu bağlayın ve durumunu izleyin.</p>
</header>

<div class="messages">
    <?php foreach ($messages as $message): ?>
        <div class="alert success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2>Oturumlar</h2>
    <div class="sessions">
        <?php foreach ($sessions as $session): ?>
            <a class="session<?php echo ($currentSession && $currentSession['id'] === $session['id']) ? ' active' : ''; ?>" href="?session_id=<?php echo (int) $session['id']; ?>">
                <?php echo htmlspecialchars($session['label'], ENT_QUOTES, 'UTF-8'); ?><br>
                <span class="status"><?php echo htmlspecialchars($session['status'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid">
    <div class="card">
        <h2>Yeni Oturum Oluştur</h2>
        <form method="post">
            <input type="hidden" name="action" value="create_session">
            <label for="label">Oturum Etiketi</label>
            <input type="text" id="label" name="label" placeholder="Örn: Satış Ekibi">
            <p><button type="submit">Oluştur</button></p>
        </form>
    </div>

    <div class="card">
        <h2>Bağlantı Durumu</h2>
        <?php if ($currentSession === null): ?>
            <p>Henüz bir oturum oluşturulmadı.</p>
        <?php elseif ($statusData === null): ?>
            <p>Durum bilgisi alınamadı.</p>
        <?php else: ?>
            <p><strong>Durum:</strong> <?php echo htmlspecialchars($statusData['status'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Son Görülme:</strong> <?php echo htmlspecialchars($statusData['last_seen'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if (!empty($statusData['qr'])): ?>
                <div class="qr">
                    <img src="<?php echo $statusData['qr']; ?>" alt="WhatsApp QR">
                </div>
            <?php else: ?>
                <p>Oturum bağlı görünüyor. Yeniden bağlamak için WhatsApp Web'i kontrol edin.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>Test Mesajı Gönder</h2>
    <form method="post">
        <input type="hidden" name="action" value="test_send">
        <label for="phone">Telefon (E.164)</label>
        <input type="text" id="phone" name="phone" placeholder="Örn: +905551112233">
        <label for="message">Mesaj</label>
        <textarea id="message" name="message" placeholder="Gönderilecek mesaj"></textarea>
        <p><button type="submit">Kuyruğa Al</button></p>
    </form>
</div>

</body>
</html>
