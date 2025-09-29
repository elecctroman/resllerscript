<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

if ($_SESSION['user']['role'] !== 'admin') {
    Helpers::redirect('/dashboard.php');
}

Helpers::redirect('/admin/dashboard.php');
