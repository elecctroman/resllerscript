<?php
class Reseller_API_Admin
{
    private static $instance = null;

    /**
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_reseller_api_test', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_reseller_api_sync_products', array($this, 'ajax_sync_products'));
        add_action('wp_ajax_reseller_api_fetch_orders', array($this, 'ajax_fetch_orders'));

        add_action('woocommerce_thankyou', array($this, 'handle_woocommerce_order'), 10, 1);
    }

    public function register_settings(): void
    {
        register_setting('reseller_api_settings', Reseller_API_Client::OPTION_URL, array(
            'sanitize_callback' => array($this, 'sanitize_url'),
        ));

        register_setting('reseller_api_settings', Reseller_API_Client::OPTION_KEY, array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }

    /**
     * @param string $url
     * @return string
     */
    /**
     * @param mixed $url
     * @return string
     */
    public function sanitize_url($url): string
    {
        if (!is_string($url)) {
            $url = is_scalar($url) ? (string) $url : '';
        }

        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $url = esc_url_raw($url);
        return rtrim($url, '/');
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('Reseller API', 'reseller-panel-api'),
            __('Reseller API', 'reseller-panel-api'),
            'manage_options',
            'reseller-api',
            array($this, 'render_page'),
            'dashicons-rest-api',
            56
        );
    }

    public function enqueue_assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_reseller-api') {
            return;
        }

        wp_enqueue_style('reseller-api-admin', RESELLER_API_PLUGIN_URL . 'assets/admin.css', array(), RESELLER_API_PLUGIN_VERSION);
        wp_enqueue_script('reseller-api-admin', RESELLER_API_PLUGIN_URL . 'assets/admin.js', array('jquery'), RESELLER_API_PLUGIN_VERSION, true);
        wp_localize_script('reseller-api-admin', 'ResellerApiSettings', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('reseller-api'),
        ));
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Bu sayfayı görüntüleme yetkiniz yok.', 'reseller-panel-api'));
        }

        $apiUrl = Reseller_API_Client::get_api_url();
        $apiKey = Reseller_API_Client::get_api_key();
        $profile = array();
        if (Reseller_API_Client::is_configured()) {
            $response = Reseller_API_Client::fetch_profile();
            if (!is_wp_error($response)) {
                $profile = $response['data'];
            }
        }

        include RESELLER_API_PLUGIN_DIR . 'views/admin-page.php';
    }

    public function ajax_test_connection(): void
    {
        check_ajax_referer('reseller-api', 'nonce');

        $result = Reseller_API_Client::fetch_profile();
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message(), 'debug' => $result->get_error_data()));
        }

        wp_send_json_success(array('message' => __('Bağlantı başarılı.', 'reseller-panel-api'), 'data' => $result['data']));
    }

    public function ajax_sync_products(): void
    {
        check_ajax_referer('reseller-api', 'nonce');

        if (!class_exists('WC_Product')) {
            wp_send_json_error(array('message' => __('WooCommerce yüklü değil.', 'reseller-panel-api')));
        }

        $result = Reseller_API_Client::fetch_products();
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $created = 0;
        $updated = 0;

        foreach ($result['data'] as $product) {
            $productId = (int) ($product['id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $wcProductId = $this->find_wc_product_by_meta($productId);
            if ($wcProductId) {
                $wcProduct = wc_get_product($wcProductId);
            } else {
                $wcProduct = new WC_Product_Simple();
            }

            if (!$wcProduct) {
                continue;
            }

            $wcProduct->set_name($product['title'] ?? 'API Product #' . $productId);
            if (!empty($product['description'])) {
                $wcProduct->set_description($product['description']);
            }

            if (isset($product['amount'])) {
                $wcProduct->set_regular_price((string) $product['amount']);
            }

            $wcProduct->update_meta_data('_reseller_api_product_id', $productId);
            $wcProduct->update_meta_data('_manage_stock', 'no');
            $wcProduct->save();

            if ($wcProductId) {
                $updated++;
            } else {
                $created++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Ürün senkronizasyonu tamamlandı. %d yeni, %d güncel.', 'reseller-panel-api'), $created, $updated),
        ));
    }

    public function ajax_fetch_orders(): void
    {
        check_ajax_referer('reseller-api', 'nonce');

        $result = Reseller_API_Client::fetch_orders();
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('orders' => $result['data']));
    }

    /**
     * @param int $orderId
     */
    public function handle_woocommerce_order($orderId): void
    {
        if (!Reseller_API_Client::is_configured()) {
            return;
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $remoteId = (int) $product->get_meta('_reseller_api_product_id');
            if ($remoteId <= 0) {
                continue;
            }

            $note = sprintf(__('WooCommerce Siparişi #%1$s - Ürün: %2$s', 'reseller-panel-api'), $order->get_order_number(), $product->get_name());
            $response = Reseller_API_Client::create_order($remoteId, $note);

            if (is_wp_error($response)) {
                $order->add_order_note(sprintf(__('API siparişi oluşturulamadı: %s', 'reseller-panel-api'), $response->get_error_message()));
                continue;
            }

            if (!empty($response['data']['order_id'])) {
                $order->update_meta_data('_reseller_api_order_id', $response['data']['order_id']);
                $order->save();
                $order->add_order_note(sprintf(__('API siparişi oluşturuldu. ID: %s', 'reseller-panel-api'), $response['data']['order_id']));
            }
        }
    }

    /**
     * @param int $remoteId
     * @return int|null
     */
    private function find_wc_product_by_meta(int $remoteId): ?int
    {
        $query = new WP_Query(array(
            'post_type'  => 'product',
            'fields'     => 'ids',
            'meta_query' => array(
                array(
                    'key'   => '_reseller_api_product_id',
                    'value' => $remoteId,
                ),
            ),
            'posts_per_page' => 1,
        ));

        if (empty($query->posts)) {
            return null;
        }

        return (int) $query->posts[0];
    }
}

