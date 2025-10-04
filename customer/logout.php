<?php
require __DIR__ . '/../bootstrap.php';

use App\Customers\CustomerAuth;
use App\Helpers;

CustomerAuth::logout();

Helpers::redirect('/customer/login.php');
