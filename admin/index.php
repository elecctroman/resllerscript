<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;
use App\Auth;

Auth::requireRoles(Auth::adminRoles(), '/dashboard.php');

Helpers::redirect('/admin/dashboard.php');
