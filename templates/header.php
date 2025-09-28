<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? App\Helpers::sanitize($pageTitle) . ' | ' : '' ?>Bayi Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="/dashboard.php">Bayi Paneli</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if ($user): ?>
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard.php">Panel</a>
                    </li>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">Yönetim</a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminMenu">
                                <li><a class="dropdown-item" href="/admin/packages.php">Paketler</a></li>
                                <li><a class="dropdown-item" href="/admin/orders.php">Siparişler</a></li>
                                <li><a class="dropdown-item" href="/admin/users.php">Bayiler</a></li>
                                <li><a class="dropdown-item" href="/admin/products.php">Ürünler</a></li>
                                <li><a class="dropdown-item" href="/admin/support.php">Destek</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/support.php">Destek</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <span class="nav-link text-white-50">Hoş geldiniz, <?= App\Helpers::sanitize($user['name']) ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/logout.php">Çıkış Yap</a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main class="container py-4">
