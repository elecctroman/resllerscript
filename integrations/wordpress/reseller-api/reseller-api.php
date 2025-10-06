<?php
/**
 * Plugin Name: Reseller Panel API Connector
 * Description: WooCommerce için bayi paneli API entegrasyonu. Ürünleri senkronize eder, siparişleri iletir ve bakiye durumunu gösterir.
 * Version: 1.0.1
 * Author: Reseller Platform
 * Text Domain: reseller-panel-api
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RESELLER_API_PLUGIN_VERSION', '1.0.1');
define('RESELLER_API_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RESELLER_API_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once RESELLER_API_PLUGIN_DIR . 'includes/class-reseller-api-client.php';
require_once RESELLER_API_PLUGIN_DIR . 'includes/class-reseller-api-admin.php';

Reseller_API_Admin::instance();

