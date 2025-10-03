<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Controllers\Admin\PremiumModuleController;
use App\View;

Auth::requireRoles(array('super_admin', 'admin'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        Helpers::redirectWithFlash('/admin/premium-modules.php', array('errors' => array('Oturum doğrulaması başarısız.')));
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $controller = new PremiumModuleController();

    if ($action === 'create') {
        $controller->store($_POST, $_FILES);
    } elseif ($action === 'toggle') {
        $moduleId = isset($_POST['module_id']) ? (int) $_POST['module_id'] : 0;
        $status = isset($_POST['status']) && $_POST['status'] === '1';
        $controller->updateStatus($moduleId, $status);
    } else {
        Helpers::redirectWithFlash('/admin/premium-modules.php', array('errors' => array('Geçersiz işlem.')));
    }

    exit;
}

$controller = new PremiumModuleController();
$data = $controller->index();

$pageTitle = 'Premium Modüller';
include __DIR__ . '/../templates/header.php';

View::render('admin/premium-modules/index.php', $data);

include __DIR__ . '/../templates/footer.php';
