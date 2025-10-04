<?php
use App\Helpers;
use App\Lang;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

Lang::boot();

$siteName = Helpers::siteName();
$pageTitle = isset($pageTitle) && $pageTitle ? $pageTitle : 'Blog';
$metaDescription = isset($metaDescription) && $metaDescription ? $metaDescription : Helpers::seoDescription();
$metaKeywords = isset($metaKeywords) && $metaKeywords ? $metaKeywords : Helpers::seoKeywords();
?>
<!DOCTYPE html>
<html lang="<?= Lang::htmlLocale() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Helpers::sanitize($pageTitle) ?> | <?= Helpers::sanitize($siteName) ?></title>
    <meta name="description" content="<?= Helpers::sanitize($metaDescription) ?>">
    <meta name="keywords" content="<?= Helpers::sanitize($metaKeywords) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/public.css">
</head>
<body class="public-app">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="/blog/"><?= Helpers::sanitize($siteName) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                <li class="nav-item"><a class="nav-link" href="/">Bayi Girişi</a></li>
                <li class="nav-item"><a class="nav-link" href="/customer/login.php">Müşteri Girişi</a></li>
                <li class="nav-item"><a class="nav-link" href="/register.php">Bayi Kaydı</a></li>
            </ul>
        </div>
    </div>
</nav>
<main class="public-main py-5">
    <div class="container">
