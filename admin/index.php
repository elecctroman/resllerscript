<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;
use App\Auth;

if (!Auth::currentAdmin()) {
    Helpers::redirect('/admin/login.php');
}

Helpers::redirect('/admin/dashboard.php');
