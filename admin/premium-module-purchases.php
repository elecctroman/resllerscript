<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Controllers\Admin\PremiumModuleController;
use App\View;

Auth::requireRoles(array('super_admin', 'admin', 'finance'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        Helpers::redirectWithFlash('/admin/premium-module-purchases.php', array('errors' => array('Oturum doğrulaması başarısız.')));
    }

    if (isset($_POST['action']) && $_POST['action'] === 'mark_paid') {
        $controller = new PremiumModuleController();
        $controller->markPurchasePaid(isset($_POST['purchase_id']) ? (int) $_POST['purchase_id'] : 0);
        exit;
    }

    Helpers::redirectWithFlash('/admin/premium-module-purchases.php', array('errors' => array('Geçersiz işlem.')));
    exit;
}

$controller = new PremiumModuleController();
$data = $controller->purchases();

$pageTitle = 'Premium Modül Satın Almaları';
include __DIR__ . '/../templates/header.php';

View::render('admin/premium-modules/purchases.php', $data);

include __DIR__ . '/../templates/footer.php';
