<?php
require __DIR__ . '/bootstrap.php';

use App\Helpers;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

Helpers::redirect('/account/support.php');
