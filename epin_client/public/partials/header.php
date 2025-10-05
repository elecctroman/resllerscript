<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= sanitize($title ?? 'E-PIN Platformu') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-MrUedvSY8DcpzMshflFMSo7EJ9j8F21Av9zCfnoFs5IunEzxZGCKMaAcW3XD2Le32cYE0C48SZ0pJr4MG5jV6g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="/epin_client/assets/css/app.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="/epin_client/public/index.php"><i class="fa-solid fa-bolt"></i> E-PIN Market</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/epin_client/public/index.php">Ana Sayfa</a></li>
                <?php if (is_authenticated()): ?>
                    <li class="nav-item"><a class="nav-link" href="/epin_client/public/dashboard.php">Panel</a></li>
                    <li class="nav-item"><a class="nav-link" href="/epin_client/public/orders.php">Siparişler</a></li>
                    <li class="nav-item"><a class="nav-link" href="/epin_client/public/wallet.php">Bakiye</a></li>
                    <li class="nav-item"><a class="nav-link" href="/epin_client/public/support.php">Destek</a></li>
                    <li class="nav-item"><a class="nav-link" href="/epin_client/public/profile.php">Profil</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" id="logout-link">Çıkış</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="/epin_client/public/login.php">Giriş</a></li>
                    <li class="nav-item"><a class="nav-link" href="/epin_client/public/register.php">Kayıt</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="py-5">
    <div class="container">
