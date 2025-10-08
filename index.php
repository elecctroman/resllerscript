<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Lang;

Lang::boot();

if (Auth::currentAdmin()) {
    Helpers::redirect('/admin/dashboard.php');
}

if (Auth::currentReseller()) {
    Helpers::redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
    header('Location: /bayi/login.php', true, 301);
    exit;
}

$siteName = Helpers::siteName() ?: 'Bayi Yönetim Sistemi';
$siteTagline = Helpers::siteTagline() ?: 'Reseller Automation Platform';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Helpers::sanitize($siteName) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 45%, #0f172a 100%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .landing-card {
            width: 100%;
            max-width: 900px;
            background: rgba(15, 23, 42, 0.8);
            border-radius: 2rem;
            box-shadow: 0 40px 70px -30px rgba(15, 23, 42, 0.75);
            overflow: hidden;
            backdrop-filter: blur(12px);
        }
        .content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }
        .info {
            padding: 3rem 3.5rem;
        }
        .info h1 {
            font-size: 2.4rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1rem;
        }
        .info p {
            color: rgba(226, 232, 240, 0.75);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }
        .actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.9rem;
            padding: 0.95rem 1.25rem;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .actions a.primary {
            background: linear-gradient(135deg, #2563eb, #60a5fa);
            color: #fff;
            box-shadow: 0 20px 35px -20px rgba(37, 99, 235, 0.7);
        }
        .actions a.secondary {
            background: rgba(15, 23, 42, 0.55);
            color: #cbd5f5;
            border: 1px solid rgba(148, 163, 184, 0.35);
        }
        .actions a:hover {
            transform: translateY(-2px);
        }
        .actions a.secondary:hover {
            box-shadow: 0 18px 30px -18px rgba(148, 163, 184, 0.35);
        }
        .hero {
            padding: 3rem;
            background: linear-gradient(160deg, rgba(37, 99, 235, 0.8), rgba(14, 165, 233, 0.65));
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1.2rem;
        }
        .hero h2 {
            font-size: 1.4rem;
            font-weight: 600;
        }
        .hero ul {
            list-style: none;
            color: rgba(241, 245, 249, 0.9);
            display: grid;
            gap: 0.75rem;
        }
        .hero li::before {
            content: '•';
            margin-right: 0.65rem;
            color: rgba(255, 255, 255, 0.85);
        }
        @media (max-width: 768px) {
            body {
                padding: 1.5rem 1rem;
            }
            .info {
                padding: 2.5rem 2rem;
            }
            .hero {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
<div class="landing-card">
    <div class="content">
        <div class="info">
            <h1><?= Helpers::sanitize($siteName) ?></h1>
            <p><?= Helpers::sanitize($siteTagline) ?></p>
            <div class="actions">
                <a class="primary" href="/bayi/login.php">Bayi Paneline Giriş</a>
                <a class="secondary" href="/admin/login.php">Yönetici Paneline Giriş</a>
            </div>
        </div>
        <div class="hero">
            <h2>Tek platformda tüm bayi süreçleri</h2>
            <ul>
                <li>Anlık stok ve otomatik teslimat yönetimi</li>
                <li>Detaylı sipariş ve finans raporları</li>
                <li>Telegram &amp; e-posta bildirim entegrasyonları</li>
                <li>7/24 destek ve dokümantasyon</li>
            </ul>
        </div>
    </div>
</div>
</body>
</html>
