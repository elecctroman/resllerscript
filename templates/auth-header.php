<?php

use App\Lang;
use App\Helpers;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

Lang::boot();

if (!isset($GLOBALS['app_lang_buffer_started'])) {
    $GLOBALS['app_lang_buffer_started'] = true;
    ob_start(function ($buffer) {
        return Lang::filterOutput($buffer);
    });
}
?>
<!DOCTYPE html>
<html lang="<?= Lang::htmlLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Helpers::sanitize('Bayi Yönetim Sistemi') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
