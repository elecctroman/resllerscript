<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;

Auth::logoutAdmin();

Helpers::redirect('/admin/login.php');
