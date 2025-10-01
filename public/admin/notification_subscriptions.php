<?php

declare(strict_types=1);

use App\Container;

require __DIR__ . '/../../app/bootstrap.php';

$pdo = Container::db();
$logger = Container::logger('admin-subscriptions');

$eventOptions = [
    'order_completed' => 'Sipariş tamamlandı',
    'support_replied' => 'Destek talebi yanıtlandı',
    'price_changed' => 'Fiyat değişimi',
    'balance_low' => 'Düşük bakiye uyarısı',
];

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $resellerId = (int) ($_POST['reseller_id'] ?? 0);
        $phone = trim((string) ($_POST['phone_number'] ?? ''));
        $events = $_POST['events'] ?? [];

        if ($resellerId <= 0) {
            $errors[] = 'Geçerli bir bayi ID girin.';
        }
        if (!preg_match('/^\+?[1-9]\d{7,14}$/', $phone)) {
            $errors[] = 'Telefon numarası E.164 formatında olmalı.';
        }
        if (!is_array($events) || count($events) === 0) {
            $errors[] = 'En az bir etkinlik seçin.';
        }

        if (empty($errors)) {
            $eventsFiltered = array_values(array_intersect(array_keys($eventOptions), $events));
            $stmt = $pdo->prepare('INSERT INTO notification_subscriptions (reseller_id, phone_number, channel, events, enabled, created_at) VALUES (:reseller, :phone, :channel, :events, 1, NOW())');
            $stmt->execute([
                ':reseller' => $resellerId,
                ':phone' => $phone,
                ':channel' => 'whatsapp',
                ':events' => json_encode($eventsFiltered, JSON_THROW_ON_ERROR),
            ]);
            $messages[] = 'Abonelik başarıyla eklendi.';
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $enabled = (int) ($_POST['enabled'] ?? 0) === 1 ? 1 : 0;
        try {
            $stmt = $pdo->prepare('UPDATE notification_subscriptions SET enabled = :enabled WHERE id = :id');
            $stmt->execute([':enabled' => $enabled, ':id' => $id]);
            $messages[] = 'Abonelik güncellendi.';
        } catch (\Throwable $exception) {
            $errors[] = 'Abonelik güncellenemedi: ' . $exception->getMessage();
            $logger->error('Subscription toggle failed', ['error' => $exception->getMessage()]);
        }
    }
}

$stmt = $pdo->query('SELECT * FROM notification_subscriptions ORDER BY created_at DESC');
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Bildirim Abonelikleri</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f4f6fb; }
        h1 { margin-bottom: 20px; }
        .grid { display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        label { display: block; font-weight: bold; margin-bottom: 6px; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 10px; border: 1px solid #c5d0e6; border-radius: 6px; margin-bottom: 10px; }
        button { background: #0052cc; color: #fff; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer; }
        button:hover { background: #0747a6; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 16px; border-bottom: 1px solid #e4e9f2; text-align: left; }
        th { background: #f1f4f9; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 10px; }
        .alert.success { background: #e6ffed; color: #046c4e; }
        .alert.error { background: #ffe5e5; color: #7a1222; }
        .events { display: flex; flex-wrap: wrap; gap: 8px; }
        .events label { font-weight: normal; }
    </style>
</head>
<body>
<h1>Bildirim Abonelikleri</h1>

<?php foreach ($messages as $message): ?>
    <div class="alert success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endforeach; ?>

<div class="grid">
    <div class="card">
        <h2>Yeni Abonelik Ekle</h2>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <label for="reseller_id">Bayi ID</label>
            <input type="number" id="reseller_id" name="reseller_id" min="1" required>

            <label for="phone_number">Telefon (E.164)</label>
            <input type="text" id="phone_number" name="phone_number" placeholder="Örn: +905551112233" required>

            <label>Etkinlikler</label>
            <div class="events">
                <?php foreach ($eventOptions as $key => $label): ?>
                    <label><input type="checkbox" name="events[]" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"> <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                <?php endforeach; ?>
            </div>

            <p><button type="submit">Kaydet</button></p>
        </form>
    </div>
</div>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Bayi ID</th>
        <th>Telefon</th>
        <th>Etkinlikler</th>
        <th>Durum</th>
        <th>Oluşturulma</th>
        <th>İşlemler</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($subscriptions as $subscription): ?>
        <tr>
            <td><?php echo (int) $subscription['id']; ?></td>
            <td><?php echo (int) $subscription['reseller_id']; ?></td>
            <td><?php echo htmlspecialchars($subscription['phone_number'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
                <?php
                $events = json_decode($subscription['events'] ?? '[]', true);
                if (is_array($events)) {
                    $labels = array_map(fn ($event) => $eventOptions[$event] ?? $event, $events);
                    echo htmlspecialchars(implode(', ', $labels), ENT_QUOTES, 'UTF-8');
                } else {
                    echo '—';
                }
                ?>
            </td>
            <td><?php echo ((int) $subscription['enabled'] === 1) ? 'Aktif' : 'Pasif'; ?></td>
            <td><?php echo htmlspecialchars($subscription['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
                <form method="post" style="display:inline-block;">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo (int) $subscription['id']; ?>">
                    <input type="hidden" name="enabled" value="<?php echo ((int) $subscription['enabled'] === 1) ? 0 : 1; ?>">
                    <button type="submit"><?php echo ((int) $subscription['enabled'] === 1) ? 'Pasifleştir' : 'Aktifleştir'; ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
