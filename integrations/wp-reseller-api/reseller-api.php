<?php
/**
 * Plugin Name: Reseller API Connector
 * Description: WooCommerce için otomatik ürün ve sipariş senkronizasyonu sağlayan bayi API eklentisi.
 * Version: 1.1.0
 * Author: Reseller Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-reseller-api-client.php';
require_once __DIR__ . '/includes/class-reseller-api-admin.php';
require_once __DIR__ . '/includes/class-reseller-api-sync.php';

add_action('plugins_loaded', function () {
    $client = new \Reseller_Api_Connector\Client();
    $admin = new \Reseller_Api_Connector\Admin($client);
    $sync = new \Reseller_Api_Connector\Sync($client);

    $admin->hooks();
    $sync->hooks();
});
