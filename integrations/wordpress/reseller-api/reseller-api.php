<?php
/**
 * Plugin Name: Reseller Script WooCommerce Köprüsü
 * Description: Reseller Script REST API'si ile WooCommerce ürün ve siparişlerini senkronize eder.
 * Version: 2.0.0
 * Author: Reseller Script
 * Text Domain: reseller-api
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('RS_Api_Bridge')) {
    require_once __DIR__ . '/includes/class-rs-api-client.php';

    final class RS_Api_Bridge
    {
        public const OPTION_URL = 'rs_api_url';
        public const OPTION_KEY = 'rs_api_key';
        public const VERSION = '2.0.0';

        private RS_Api_Client $client;

        public function __construct()
        {
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_menu', array($this, 'register_menu'));
            add_action('admin_post_rs_sync_products', array($this, 'handle_product_sync'));
            add_action('woocommerce_checkout_order_processed', array($this, 'handle_checkout_order'), 10, 3);
            add_action('woocommerce_order_status_processing', array($this, 'refresh_remote_status'), 10, 1);
            add_action('woocommerce_order_status_completed', array($this, 'refresh_remote_status'), 10, 1);
            $this->client = new RS_Api_Client();
        }

        public function register_settings(): void
        {
            register_setting('rs_api_settings', self::OPTION_URL, array(
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
            ));

            register_setting('rs_api_settings', self::OPTION_KEY, array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ));
        }

        public function register_menu(): void
        {
            add_submenu_page(
                'woocommerce',
                __('Reseller API', 'reseller-api'),
                __('Reseller API', 'reseller-api'),
                'manage_woocommerce',
                'rs-api-settings',
                array($this, 'render_settings_page')
            );
        }

        public function render_settings_page(): void
        {
            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('Bu sayfaya erişim yetkiniz yok.', 'reseller-api'));
            }

            $apiUrl = esc_url(get_option(self::OPTION_URL, ''));
            $apiKey = esc_attr(get_option(self::OPTION_KEY, ''));
            $lastSync = esc_html(get_option('rs_api_last_sync', __('Senkr. yapılmadı', 'reseller-api')));
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Reseller Script API Ayarları', 'reseller-api'); ?></h1>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('rs_api_settings');
                    do_settings_sections('rs_api_settings');
                    ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="rs_api_url"><?php esc_html_e('API URL', 'reseller-api'); ?></label></th>
                            <td><input type="url" class="regular-text" id="rs_api_url" name="<?= esc_attr(self::OPTION_URL); ?>" value="<?= $apiUrl; ?>" placeholder="https://panel.example.com/api/v2" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="rs_api_key"><?php esc_html_e('API Key', 'reseller-api'); ?></label></th>
                            <td><input type="text" class="regular-text" id="rs_api_key" name="<?= esc_attr(self::OPTION_KEY); ?>" value="<?= $apiKey; ?>" required></td>
                        </tr>
                    </table>
                    <?php submit_button(__('Ayarları Kaydet', 'reseller-api')); ?>
                </form>

                <h2><?php esc_html_e('Ürün Senkronizasyonu', 'reseller-api'); ?></h2>
                <p><?php printf(esc_html__('Son senkronizasyon: %s', 'reseller-api'), $lastSync); ?></p>
                <form method="post" action="<?= esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('rs_sync_products'); ?>
                    <input type="hidden" name="action" value="rs_sync_products">
                    <?php submit_button(__('WooCommerce Ürünlerini Güncelle', 'reseller-api'), 'secondary'); ?>
                </form>
            </div>
            <?php
        }

        public function handle_product_sync(): void
        {
            if (!current_user_can('manage_woocommerce') || !check_admin_referer('rs_sync_products')) {
                wp_die(__('Geçersiz istek.', 'reseller-api'));
            }

            $response = $this->client->get_products();
            if (is_wp_error($response)) {
                wp_safe_redirect(add_query_arg('rs_sync', 'error', wp_get_referer()));
                exit;
            }

            $products = is_array($response) ? ($response['data']['products'] ?? array()) : array();

            $synced = 0;
            foreach ($products as $product) {
                $synced += $this->sync_product($product) ? 1 : 0;
            }

            update_option('rs_api_last_sync', current_time('mysql'));
            wp_safe_redirect(add_query_arg('rs_sync', $synced, wp_get_referer()));
            exit;
        }

        private function sync_product(array $remote): bool
        {
            if (!class_exists('WC_Product_Simple')) {
                return false;
            }

            if (empty($remote['name'])) {
                return false;
            }

            $sku = isset($remote['sku']) && $remote['sku'] !== '' ? $remote['sku'] : 'rs-' . (int) $remote['id'];
            $productId = function_exists('wc_get_product_id_by_sku') ? wc_get_product_id_by_sku($sku) : 0;

            if ($productId) {
                $product = wc_get_product($productId);
            } else {
                $product = new WC_Product_Simple();
                $product->set_sku($sku);
            }

            if (!$product) {
                return false;
            }

            $product->set_name($remote['name']);
            if (!empty($remote['description'])) {
                $product->set_description($remote['description']);
            }
            if (isset($remote['price'])) {
                $product->set_price((float) $remote['price']);
                $product->set_regular_price((float) $remote['price']);
            }
            $product->set_catalog_visibility('visible');
            $product->set_status('publish');
            $product->set_virtual(true);
            $product->save();

            update_post_meta($product->get_id(), '_rs_api_product_id', (int) $remote['id']);
            update_post_meta($product->get_id(), '_rs_api_provider', sanitize_text_field($remote['provider_code'] ?? '')); 

            return true;
        }

        public function handle_checkout_order(int $orderId, array $postedData, $order): void
        {
            if (!class_exists('WC_Order')) {
                return;
            }

            if (!$order instanceof WC_Order) {
                $order = wc_get_order($orderId);
            }

            if (!$order) {
                return;
            }

            foreach ($order->get_items() as $itemId => $item) {
                $product = $item->get_product();
                if (!$product) {
                    continue;
                }

                $remoteId = (int) get_post_meta($product->get_id(), '_rs_api_product_id', true);
                if ($remoteId <= 0) {
                    continue;
                }

                $payload = array(
                    'product_id' => $remoteId,
                    'quantity' => (int) $item->get_quantity(),
                    'external_reference' => sprintf('WC-%d-%d', $orderId, $itemId),
                    'note' => $order->get_customer_note(),
                );

                $result = $this->client->create_order($payload);
                if (is_wp_error($result)) {
                    $order->add_order_note(sprintf(__('Reseller API siparişi oluşturulamadı: %s', 'reseller-api'), $result->get_error_message()));
                    continue;
                }

                $data = is_array($result) ? ($result['data'] ?? array()) : array();
                if (!empty($data['order_id'])) {
                    update_post_meta($product->get_id(), '_rs_last_order_id', (int) $data['order_id']);
                    $order->add_order_note(sprintf(__('Reseller API siparişi oluşturuldu (#%d).', 'reseller-api'), (int) $data['order_id']));
                }
            }
        }

        public function refresh_remote_status(int $orderId): void
        {
            $order = wc_get_order($orderId);
            if (!$order) {
                return;
            }

            $firstItem = current($order->get_items());
            if (!$firstItem) {
                return;
            }

            $reference = sprintf('WC-%d-%d', $orderId, $firstItem->get_id());
            $result = $this->client->get_order_status(array('external_reference' => $reference));
            if (is_wp_error($result)) {
                $order->add_order_note(sprintf(__('API sipariş durumu alınamadı: %s', 'reseller-api'), $result->get_error_message()));
                return;
            }

            $orderData = is_array($result) ? ($result['data']['order'] ?? array()) : array();
            if (!empty($orderData['status'])) {
                $order->add_order_note(sprintf(__('Sağlayıcı sipariş durumu: %s', 'reseller-api'), $orderData['status']));
            }
        }
    }

    add_action('plugins_loaded', static function () {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', static function () {
                echo '<div class="notice notice-error"><p>' . esc_html__('Reseller Script WooCommerce Köprüsü için WooCommerce kurulmalıdır.', 'reseller-api') . '</p></div>';
            });
            return;
        }

        new RS_Api_Bridge();
    });
}
