<?php
require __DIR__ . '/../bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

unset($_SESSION['customer']);
App\Helpers::redirect('/customer/login.php');
