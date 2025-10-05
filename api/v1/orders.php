<?php
require __DIR__ . '/../bootstrap.php';

$controller = new App\Api\Controllers\OrdersController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->create();
    return;
}

if (isset($_GET['id'])) {
    $controller->show((string) $_GET['id']);
    return;
}

$controller->index();
