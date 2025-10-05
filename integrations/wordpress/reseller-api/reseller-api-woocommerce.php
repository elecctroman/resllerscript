<?php
/**
 * Plugin Name: Reseller API WooCommerce Bridge
 * Plugin URI:  https://resellers.pckeystore.com/
 * Description: Connects your reseller panel to WooCommerce for automatic product, order and balance synchronisation.
 * Version:     1.0.0
 * Author:      Lotus Reseller Panel
 * License:     GPL-2.0+
 * Text Domain: reseller-api
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Reseller_API_WooCommerce_Plugin')) {
    final class Reseller_API_WooCommerce_Plugin
    {
        const OPTION_KEY = 'reseller_api_settings';
        const CRON_HOOK = 'reseller_api_sync_hook';

        /**
         * @var Reseller_API_Client
         */
        private $client;

        /**
         * @var Reseller_API_Admin
         */
        private $admin;

        public function __construct()
        {
            require_once __DIR__ . '/includes/class-reseller-api-client.php';
            require_once __DIR__ . '/includes/class-reseller-api-admin.php';
            require_once __DIR__ . '/includes/class-reseller-api-sync.php';

            $this->client = new Reseller_API_Client($this);
            $this->admin = new Reseller_API_Admin($this, $this->client);

            add_action('init', array($this, 'load_textdomain'));
            add_action('plugins_loaded', array($this, 'on_plugins_loaded'));
            add_action('wp_ajax_reseller_api_test_connection', array($this->admin, 'ajax_test_connection'));
            add_action('wp_ajax_reseller_api_sync_products', array($this->admin, 'ajax_sync_products'));
            add_action('wp_ajax_reseller_api_fetch_balance', array($this->admin, 'ajax_fetch_balance'));
            add_action('wp_ajax_reseller_api_fetch_orders', array($this->admin, 'ajax_fetch_orders'));
            add_action(self::CRON_HOOK, array($this, 'scheduled_sync'));
        }

        public function load_textdomain()
        {
            load_plugin_textdomain('reseller-api', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }

        public function on_plugins_loaded()
        {
            $this->admin->register_hooks();

            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', array($this, 'missing_wc_notice'));
            }
        }

        public function missing_wc_notice()
        {
            if (!current_user_can('manage_options')) {
                return;
            }

            echo '<div class="notice notice-error"><p>' . esc_html__('Reseller API WooCommerce Bridge requires WooCommerce to be installed and active.', 'reseller-api') . '</p></div>';
        }

        /**
         * @return array
         */
        public function get_settings()
        {
            $defaults = array(
                'api_url' => '',
                'api_key' => '',
                'auto_sync' => 'hourly',
                'default_status' => 'publish',
                'price_markup' => '0',
            );

            $settings = get_option(self::OPTION_KEY, array());
            if (!is_array($settings)) {
                $settings = array();
            }

            return wp_parse_args($settings, $defaults);
        }

        /**
         * @param array $settings
         * @return void
         */
        public function update_settings(array $settings)
        {
            update_option(self::OPTION_KEY, $settings);
            $this->maybe_schedule_cron($settings);
        }

        /**
         * @param array|null $settings
         * @return void
         */
        public function maybe_schedule_cron($settings = null)
        {
            if ($settings === null) {
                $settings = $this->get_settings();
            }

            wp_clear_scheduled_hook(self::CRON_HOOK);

            $interval = isset($settings['auto_sync']) ? $settings['auto_sync'] : '';
            if ($interval && $interval !== 'manual') {
                if (!wp_next_scheduled(self::CRON_HOOK)) {
                    wp_schedule_event(time() + 300, $interval, self::CRON_HOOK);
                }
            }
        }

        public function scheduled_sync()
        {
            if (!class_exists('WooCommerce')) {
                return;
            }

            $sync = new Reseller_API_Sync($this, $this->client);
            $sync->sync_products();
        }

        public static function activate()
        {
            if (!wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time() + 300, 'hourly', self::CRON_HOOK);
            }
        }

        public static function deactivate()
        {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }
}

function reseller_api_init_plugin()
{
    $GLOBALS['reseller_api_plugin'] = new Reseller_API_WooCommerce_Plugin();
}

add_action('plugins_loaded', 'reseller_api_init_plugin');

register_activation_hook(__FILE__, array('Reseller_API_WooCommerce_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('Reseller_API_WooCommerce_Plugin', 'deactivate'));

