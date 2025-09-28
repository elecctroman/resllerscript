<?php
/**
 * Plugin Name: Reseller Sync Connector
 * Description: WooCommerce siparişlerini bayi yönetim sistemine aktarır ve durum güncellemelerini senkronize eder.
 * Version: 1.0.0
 * Author: Reseller Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Reseller_Sync_Connector
{
    const OPTION_API_URL = 'reseller_sync_api_url';
    const OPTION_API_KEY = 'reseller_sync_api_key';
    const OPTION_LAST_WEBHOOK_SYNC = 'reseller_sync_webhook_synced_at';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_order_status_processing', array($this, 'maybe_push_order'));
        add_action('woocommerce_order_status_completed', array($this, 'maybe_push_order'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_settings_page()
    {
        add_submenu_page(
            'woocommerce',
            __('Reseller Sync', 'reseller-sync'),
            __('Reseller Sync', 'reseller-sync'),
            'manage_woocommerce',
            'reseller-sync',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings()
    {
        register_setting('reseller_sync_settings', self::OPTION_API_URL, array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_api_url'),
        ));

        register_setting('reseller_sync_settings', self::OPTION_API_KEY, array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }

    public function sanitize_api_url($value)
    {
        $value = esc_url_raw(trim($value));
        if ($value !== '') {
            $value = trailingslashit($value);
        }
        return $value;
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $apiUrl = get_option(self::OPTION_API_URL, '');
        $apiKey = get_option(self::OPTION_API_KEY, '');

        if (isset($_POST['reseller_sync_settings_nonce']) && wp_verify_nonce($_POST['reseller_sync_settings_nonce'], 'reseller_sync_save_settings')) {
            $newApiUrl = isset($_POST[self::OPTION_API_URL]) ? $this->sanitize_api_url(wp_unslash($_POST[self::OPTION_API_URL])) : '';
            $newApiKey = isset($_POST[self::OPTION_API_KEY]) ? sanitize_text_field(wp_unslash($_POST[self::OPTION_API_KEY])) : '';

            update_option(self::OPTION_API_URL, $newApiUrl);
            update_option(self::OPTION_API_KEY, $newApiKey);

            $apiUrl = $newApiUrl;
            $apiKey = $newApiKey;

            if ($apiUrl && $apiKey) {
                $syncResult = $this->register_webhook_with_api($apiUrl, $apiKey);
                if ($syncResult === true) {
                    update_option(self::OPTION_LAST_WEBHOOK_SYNC, current_time('mysql'));
                    add_settings_error('reseller_sync_messages', 'reseller_sync_success', __('Webhook adresi başarıyla kaydedildi.', 'reseller-sync'), 'updated');
                } else {
                    add_settings_error('reseller_sync_messages', 'reseller_sync_error', sprintf(__('Webhook kaydı başarısız: %s', 'reseller-sync'), $syncResult), 'error');
                }
            } else {
                add_settings_error('reseller_sync_messages', 'reseller_sync_warning', __('API URL ve API anahtarı alanları zorunludur.', 'reseller-sync'), 'error');
            }
        }

        settings_errors('reseller_sync_messages');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Reseller Sync Ayarları', 'reseller-sync'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('reseller_sync_save_settings', 'reseller_sync_settings_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="reseller-sync-api-url"><?php esc_html_e('API URL', 'reseller-sync'); ?></label></th>
                        <td>
                            <input name="<?php echo esc_attr(self::OPTION_API_URL); ?>" type="text" id="reseller-sync-api-url" value="<?php echo esc_attr($apiUrl); ?>" class="regular-text" placeholder="https://panel.ornek.com/api/v1/">
                            <p class="description"><?php esc_html_e('Bayi yönetim sistemi API kök adresi (örn. https://panel.ornek.com/api/v1/).', 'reseller-sync'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="reseller-sync-api-key"><?php esc_html_e('API Anahtarı', 'reseller-sync'); ?></label></th>
                        <td>
                            <input name="<?php echo esc_attr(self::OPTION_API_KEY); ?>" type="password" id="reseller-sync-api-key" value="<?php echo esc_attr($apiKey); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Bayi panelindeki profil bölümünden aldığınız API anahtarı.', 'reseller-sync'); ?></p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <?php submit_button(__('Ayarları Kaydet', 'reseller-sync')); ?>
            </form>

            <?php if (get_option(self::OPTION_LAST_WEBHOOK_SYNC)): ?>
                <p class="description">
                    <?php printf(esc_html__('Son webhook senkronizasyonu: %s', 'reseller-sync'), esc_html(get_option(self::OPTION_LAST_WEBHOOK_SYNC))); ?>
                </p>
            <?php endif; ?>

            <p class="description">
                <?php printf(esc_html__('WordPress sitenizin webhook adresi: %s', 'reseller-sync'), '<code>' . esc_html(rest_url('reseller-sync/v1/order-status')) . '</code>'); ?>
            </p>
        </div>
        <?php
    }

    private function register_webhook_with_api($apiUrl, $apiKey)
    {
        $endpoint = trailingslashit($apiUrl) . 'token-webhook.php';
        if (substr($apiUrl, -1) === '/') {
            $endpoint = $apiUrl . 'token-webhook.php';
        }

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'webhook_url' => rest_url('reseller-sync/v1/order-status'),
            )),
            'timeout' => 20,
        ));

        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return true;
        }

        $body = wp_remote_retrieve_body($response);
        if ($body) {
            $decoded = json_decode($body, true);
            if (isset($decoded['error'])) {
                return $decoded['error'];
            }
        }

        return sprintf(__('Beklenmeyen API yanıtı (%d)', 'reseller-sync'), $code);
    }

    public function maybe_push_order($orderId)
    {
        $apiUrl = get_option(self::OPTION_API_URL);
        $apiKey = get_option(self::OPTION_API_KEY);

        if (!$apiUrl || !$apiKey) {
            return;
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }

        if ($order->get_meta('_reseller_sync_pushed') === 'yes') {
            return;
        }

        $items = array();
        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $sku = $product->get_sku();
            if (!$sku) {
                continue;
            }

            $items[] = array(
                'sku' => $sku,
                'quantity' => (int)$item->get_quantity(),
                'note' => '',
            );
        }

        if (!$items) {
            $order->add_order_note(__('Reseller Sync: SKU bulunamadığı için sipariş aktarılmadı.', 'reseller-sync'));
            return;
        }

        $payload = array(
            'order_id' => (string)$order->get_id(),
            'currency' => $order->get_currency(),
            'items' => $items,
            'customer' => array(
                'name' => trim($order->get_formatted_billing_full_name()),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ),
        );

        if ($order->get_customer_note()) {
            $payload['customer']['note'] = $order->get_customer_note();
        }

        $response = wp_remote_post(trailingslashit($apiUrl) . 'orders.php', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($payload),
            'timeout' => 20,
        ));

        if (is_wp_error($response)) {
            $order->add_order_note(sprintf(__('Reseller Sync: API hatası - %s', 'reseller-sync'), $response->get_error_message()));
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code >= 200 && $code < 300) {
            $decoded = json_decode($body, true);
            if (isset($decoded['data']['orders']) && is_array($decoded['data']['orders'])) {
                $orderIds = implode(', ', array_map('intval', $decoded['data']['orders']));
                $order->update_meta_data('_reseller_sync_pushed', 'yes');
                $order->update_meta_data('_reseller_sync_remote_orders', wp_json_encode($decoded['data']['orders']));
                $order->save_meta_data();
                $order->add_order_note(sprintf(__('Reseller Sync: Sipariş sisteme aktarıldı (ID: %s).', 'reseller-sync'), $orderIds));
            } else {
                $order->add_order_note(__('Reseller Sync: API yanıtı beklenen formatta değil.', 'reseller-sync'));
            }
        } else {
            if ($body) {
                $decoded = json_decode($body, true);
                if (isset($decoded['error'])) {
                    $order->add_order_note(sprintf(__('Reseller Sync: API hatası - %s', 'reseller-sync'), $decoded['error']));
                    return;
                }
            }

            $order->add_order_note(sprintf(__('Reseller Sync: API beklenmedik durum kodu döndürdü (%d).', 'reseller-sync'), $code));
        }
    }

    public function register_rest_routes()
    {
        register_rest_route('reseller-sync/v1', '/order-status', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_order_status_webhook'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_order_status_webhook(\WP_REST_Request $request)
    {
        $apiKey = get_option(self::OPTION_API_KEY);
        if (!$apiKey) {
            return new \WP_REST_Response(array('success' => false, 'error' => 'API anahtarı yapılandırılmamış.'), 403);
        }

        $authHeader = $request->get_header('authorization');
        $providedKey = '';
        if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
            $providedKey = trim(substr($authHeader, 7));
        }

        if (!$providedKey || $providedKey !== $apiKey) {
            return new \WP_REST_Response(array('success' => false, 'error' => 'Yetkilendirme başarısız.'), 403);
        }

        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return new \WP_REST_Response(array('success' => false, 'error' => 'Geçersiz yük.'), 400);
        }

        $externalReference = isset($payload['external_reference']) ? $payload['external_reference'] : (isset($payload['order_id']) ? $payload['order_id'] : '');
        if (!$externalReference) {
            return new \WP_REST_Response(array('success' => false, 'error' => 'external_reference alanı zorunludur.'), 422);
        }

        $order = wc_get_order($externalReference);
        if (!$order) {
            return new \WP_REST_Response(array('success' => false, 'error' => 'WooCommerce siparişi bulunamadı.'), 404);
        }

        $status = isset($payload['status']) ? $payload['status'] : '';
        if ($status === 'cancelled') {
            $this->process_cancelled_order($order);
        } elseif ($status === 'completed') {
            $order->add_order_note(__('Reseller Sync: Sipariş tamamlandı olarak işaretlendi.', 'reseller-sync'));
        }

        $order->update_meta_data('_reseller_sync_remote_status', $status);
        $order->save_meta_data();

        return new \WP_REST_Response(array('success' => true), 200);
    }

    private function process_cancelled_order(\WC_Order $order)
    {
        if ($order->has_status('cancelled')) {
            $order->add_order_note(__('Reseller Sync: Sipariş iptal bildirimi alındı.', 'reseller-sync'));
        } else {
            $order->update_status('cancelled', __('Reseller Sync tarafından iptal edildi.', 'reseller-sync'));
        }

        if (function_exists('wc_maybe_increase_stock_levels')) {
            wc_maybe_increase_stock_levels($order->get_id());
        }

        if (function_exists('woo_wallet') && method_exists(woo_wallet()->wallet, 'credit')) {
            $refunded = $order->get_meta('_reseller_sync_wallet_refunded');
            if ($refunded !== 'yes') {
                $amount = floatval($order->get_total());
                if ($amount > 0 && $order->get_customer_id()) {
                    woo_wallet()->wallet->credit(
                        $order->get_customer_id(),
                        $amount,
                        __('Reseller Sync iptal iadesi', 'reseller-sync')
                    );
                    $order->update_meta_data('_reseller_sync_wallet_refunded', 'yes');
                    $order->save_meta_data();
                    $order->add_order_note(__('Reseller Sync: TerraWallet bakiyesi iade edildi.', 'reseller-sync'));
                }
            }
        }
    }
}

new Reseller_Sync_Connector();
