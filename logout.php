<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Helpers;

Auth::logoutReseller();

Helpers::redirect('/bayi/login.php');
