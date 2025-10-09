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
    error_log('[Storefront Account] Kategori başlıkları yüklenemedi: ' . $exception->getMessage());
    $headerCategories = array();
}

store_render('account/profile', array(
    'pageTitle' => 'Hesabım',
    'user' => $user,
    'headerCategories' => $headerCategories,
    'metaDescription' => (string) get_setting('seo_description', ''),
));
