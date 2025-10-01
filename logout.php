<?php
require __DIR__ . '/bootstrap.php';

session_destroy();

App\Helpers::redirect('/index.php');
