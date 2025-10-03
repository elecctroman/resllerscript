<?php
require __DIR__ . '/bootstrap.php';

use App\Helpers;
use App\Services\PremiumPurchaseService;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

$purchaseId = isset($_GET['purchase']) ? (int) $_GET['purchase'] : 0;
$expires = isset($_GET['expires']) ? (int) $_GET['expires'] : 0;
$signature = isset($_GET['signature']) ? $_GET['signature'] : '';

if (!$purchaseId || !$expires || $signature === '') {
    Helpers::redirectWithFlash('/premium-modules.php', array('errors' => array('İndirme bağlantısı geçersiz.')));
}

$service = new PremiumPurchaseService();

try {
    $data = $service->validateDownloadRequest($purchaseId, $expires, $signature);
    $purchase = $data['purchase'];
    $module = $data['module'];

    if ((int) $purchase['user_id'] !== (int) $user['id']) {
        throw new RuntimeException('Bu indirme bağlantısına erişim yetkiniz bulunmuyor.');
    }

    $path = isset($module['file_path']) ? $module['file_path'] : '';
    if (!is_file($path)) {
        throw new RuntimeException('Modül paketi sunucuda bulunamadı.');
    }

    $filename = basename($path);
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: public');
    header('Cache-Control: must-revalidate, no-store');

    readfile($path);
    exit;
} catch (\Throwable $exception) {
    Helpers::redirectWithFlash('/premium-modules.php', array('errors' => array($exception->getMessage())));
}
