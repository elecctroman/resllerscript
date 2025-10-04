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
$brandUrl = isset($brandUrl) && $brandUrl ? $brandUrl : '/blog/';
$navLinks = isset($navLinks) && is_array($navLinks) && $navLinks ? $navLinks : array(
    array('label' => 'Bayi Girişi', 'url' => '/index.php'),
    array('label' => 'Müşteri Girişi', 'url' => '/customer/login.php'),
    array('label' => 'Bayi Kaydı', 'url' => '/register.php'),
);
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
        <a class="navbar-brand fw-semibold" href="<?= Helpers::sanitize($brandUrl) ?>"><?= Helpers::sanitize($siteName) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                <?php foreach ($navLinks as $item): ?>
                    <?php
                    $label = isset($item['label']) ? (string)$item['label'] : '';
                    $url = isset($item['url']) ? (string)$item['url'] : '#';
                    $isButton = isset($item['is_button']) ? (bool)$item['is_button'] : false;
                    if ($label === '') {
                        continue;
                    }
                    $linkClass = $isButton ? 'btn btn-primary px-3 py-1' : 'nav-link';
                    $itemClass = 'nav-item' . ($isButton ? ' ms-lg-2' : '');
                    ?>
                    <li class="<?= $itemClass ?>">
                        <a class="<?= $linkClass ?>" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
                            <?= Helpers::sanitize($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="public-main py-5">
    <div class="container">
