<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Controllers\Reseller\PremiumModuleController;
use App\View;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if (Auth::isAdminRole($user['role'])) {
    Helpers::redirect('/admin/premium-modules.php');
}

if (!Helpers::featureEnabled('premium_modules')) {
    Helpers::setFlash('warning', 'Premium modüller şu anda devre dışı.');
    Helpers::redirect('/dashboard.php');
}

$controller = new PremiumModuleController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        Helpers::redirectWithFlash('/premium-modules.php', array('errors' => array('Oturum doğrulaması başarısız.')));
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'purchase') {
        $moduleId = isset($_POST['module_id']) ? (int) $_POST['module_id'] : 0;
        $method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'balance';
        $result = $controller->purchase((int) $user['id'], $moduleId, $method);

        if ($result['success']) {
            Helpers::redirectWithFlash('/premium-modules.php', array('success' => $result['message']));
        }

        Helpers::redirectWithFlash('/premium-modules.php', array('errors' => $result['errors']));
    }

    if ($action === 'download') {
        $purchaseId = isset($_POST['purchase_id']) ? (int) $_POST['purchase_id'] : 0;

        try {
            $link = $controller->downloadLink((int) $user['id'], $purchaseId);
            Helpers::redirect($link);
        } catch (\Throwable $exception) {
            Helpers::redirectWithFlash('/premium-modules.php', array('errors' => array($exception->getMessage())));
        }
    }

    Helpers::redirectWithFlash('/premium-modules.php', array('errors' => array('Geçersiz işlem.')));
}

$modules = $controller->availableForUser((int) $user['id']);

$pageTitle = 'Premium Modüller';
$errors = Helpers::getFlash('errors', array());
$success = Helpers::getFlash('success', '');

include __DIR__ . '/templates/header.php';

View::render('reseller/premium-modules/index.php', array(
    'modules' => $modules,
    'errors' => $errors,
    'success' => $success,
));

include __DIR__ . '/templates/footer.php';
