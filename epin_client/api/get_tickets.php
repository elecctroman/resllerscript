<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_auth();

$pdo = db();
$hasAdminResponse = false;
try {
    $columnCheck = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'admin_response'");
    $hasAdminResponse = (bool)$columnCheck->fetch();
} catch (PDOException) {
    $hasAdminResponse = false;
}

$fields = 'id, subject, message, status, created_at';
if ($hasAdminResponse) {
    $fields .= ', admin_response';
}

$stmt = $pdo->prepare("SELECT $fields FROM tickets WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->execute(['user_id' => (int)session_get('user_id')]);

$statusLabels = [
    'open' => 'Açık',
    'answered' => 'Yanıtlandı',
    'closed' => 'Kapalı',
];

$tickets = [];
foreach ($stmt->fetchAll() as $ticket) {
    $status = $ticket['status'] ?? 'open';
    $tickets[] = [
        'id' => (int)$ticket['id'],
        'subject' => sanitize($ticket['subject'] ?? ''),
        'message' => nl2br(sanitize($ticket['message'] ?? '')),
        'status' => $status,
        'status_label' => $statusLabels[$status] ?? ucfirst($status),
        'created_at' => $ticket['created_at'],
        'admin_response' => $hasAdminResponse && isset($ticket['admin_response']) && $ticket['admin_response'] !== null
            ? nl2br(sanitize($ticket['admin_response']))
            : null,
    ];
}

json_response(['tickets' => $tickets]);
