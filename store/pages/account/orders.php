<?php
require_once __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

if (!Auth::check()) {
    Helpers::redirect('/account/login');
}

$user = Auth::user();
$pdo = Database::connection();

$orders = array();
$userId = isset($user['id']) ? (int) $user['id'] : 0;
if ($userId > 0) {
    try {
        $stmt = $pdo->prepare('SELECT id, module_id, payment_status, license_key, created_at FROM user_purchases WHERE user_id = :user ORDER BY created_at DESC LIMIT 50');
        $stmt->execute(array('user' => $userId));
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    } catch (\PDOException $exception) {
        error_log('[Storefront Orders] Siparişler alınamadı: ' . $exception->getMessage());
        $orders = array();
    }
}

$headerCategories = array();
try {
    $categoryStmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC LIMIT 9');
    if ($categoryStmt !== false) {
        foreach ($categoryStmt->fetchAll(PDO::FETCH_ASSOC) as $category) {
            if (!isset($category['id'], $category['name'])) {
                continue;
            }

            $headerCategories[] = array(
                'name' => (string) $category['name'],
                'url' => store_url('category/' . (int) $category['id']),
                'icon' => '',
            );
        }
    }
} catch (\PDOException $exception) {
    error_log('[Storefront Orders] Kategori başlıkları yüklenemedi: ' . $exception->getMessage());
    $headerCategories = array();
}

store_render('account/orders', array(
    'pageTitle' => 'Siparişlerim',
    'orders' => $orders,
    'headerCategories' => $headerCategories,
    'metaDescription' => (string) get_setting('seo_description', ''),
));
