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
    const DEFAULT_API_BASE = 'https://your-reseller-panel.com/api/v1/';
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

    private function api_base()
    {
        $stored = get_option(self::OPTION_API_URL, '');
        $base = $stored ? $stored : self::DEFAULT_API_BASE;

        if (defined('RESELLER_SYNC_API_BASE') && RESELLER_SYNC_API_BASE) {
            $base = RESELLER_SYNC_API_BASE;
        }

        $base = apply_filters('reseller_sync_api_base', $base);

        return trailingslashit($base);
    }

    private function build_endpoint($path)
    {
        return $this->api_base() . ltrim($path, '/');
    }

    private function build_headers($apiKey)
    {
        return array(
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        );
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
            'sanitize_callback' => 'esc_url_raw',
        ));

        register_setting('reseller_sync_settings', self::OPTION_API_KEY, array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $apiUrl = get_option(self::OPTION_API_URL, '');
        $apiKey = get_option(self::OPTION_API_KEY, '');

        if (isset($_POST['reseller_sync_settings_nonce']) && wp_verify_nonce($_POST['reseller_sync_settings_nonce'], 'reseller_sync_save_settings')) {
            $newApiUrl = isset($_POST[self::OPTION_API_URL]) ? esc_url_raw(wp_unslash($_POST[self::OPTION_API_URL])) : '';
            if ($newApiUrl && !filter_var($newApiUrl, FILTER_VALIDATE_URL)) {
                $newApiUrl = '';
                add_settings_error('reseller_sync_messages', 'reseller_sync_invalid_url', __('Geçerli bir API adresi giriniz.', 'reseller-sync'), 'error');
            }
            if ($newApiUrl) {
                $newApiUrl = trailingslashit($newApiUrl);
            }
            $newApiKey = isset($_POST[self::OPTION_API_KEY]) ? sanitize_text_field(wp_unslash($_POST[self::OPTION_API_KEY])) : '';

            update_option(self::OPTION_API_URL, $newApiUrl);
            update_option(self::OPTION_API_KEY, $newApiKey);

            $apiUrl = $newApiUrl;
            $apiKey = $newApiKey;

            if ($apiUrl && $apiKey) {
                $syncResult = $this->register_webhook_with_api($apiKey);
                if ($syncResult === true) {
                    update_option(self::OPTION_LAST_WEBHOOK_SYNC, current_time('mysql'));
                    add_settings_error('reseller_sync_messages', 'reseller_sync_success', __('Webhook adresi başarıyla kaydedildi.', 'reseller-sync'), 'updated');
                } else {
                    add_settings_error('reseller_sync_messages', 'reseller_sync_error', sprintf(__('Webhook kaydı başarısız: %s', 'reseller-sync'), $syncResult), 'error');
                }
            } else {
                add_settings_error('reseller_sync_messages', 'reseller_sync_warning', __('API adresi ve API anahtarı alanları zorunludur.', 'reseller-sync'), 'error');
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
                        <th scope="row"><label for="reseller-sync-api-url"><?php esc_html_e('API Adresi', 'reseller-sync'); ?></label></th>
                        <td>
                            <input name="<?php echo esc_attr(self::OPTION_API_URL); ?>" type="url" id="reseller-sync-api-url" value="<?php echo esc_attr($apiUrl); ?>" class="regular-text" placeholder="https://panel.ornek.com/api/v1/">
                            <p class="description"><?php esc_html_e('Bayi paneli profil ekranında görüntülenen API URL bilgisini girin.', 'reseller-sync'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="reseller-sync-api-key"><?php esc_html_e('API Anahtarı', 'reseller-sync'); ?></label></th>
                        <td>
                            <input name="<?php echo esc_attr(self::OPTION_API_KEY); ?>" type="password" id="reseller-sync-api-key" value="<?php echo esc_attr($apiKey); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Bayi paneli profil ekranından oluşturduğunuz API anahtarını girin.', 'reseller-sync'); ?></p>
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

    private function register_webhook_with_api($apiKey)
    {
        $response = wp_remote_post($this->build_endpoint('token-webhook.php'), array(
            'headers' => $this->build_headers($apiKey),
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

        $response = wp_remote_post($this->build_endpoint('orders.php'), array(
            'headers' => $this->build_headers($apiKey),
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
                $remoteOrderIds = $this->sanitize_remote_order_ids($decoded['data']['orders']);
                $order->update_meta_data('_reseller_sync_pushed', 'yes');
                if ($remoteOrderIds) {
                    $order->update_meta_data('_reseller_sync_remote_orders', $remoteOrderIds);
                    $order->update_meta_data('_reseller_sync_remote_order_ids', ',' . implode(',', $remoteOrderIds) . ',');
                    $order->save_meta_data();
                    $order->add_order_note(sprintf(__('Reseller Sync: Sipariş sisteme aktarıldı (ID: %s).', 'reseller-sync'), implode(', ', $remoteOrderIds)));
                } else {
                    $order->save_meta_data();
                    $order->add_order_note(__('Reseller Sync: Sipariş sisteme aktarıldı ancak dönen ID listesi boştu.', 'reseller-sync'));
                }
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

        $externalReference = isset($payload['external_reference']) ? $payload['external_reference'] : '';
        if (!$externalReference && isset($payload['woocommerce_order_id'])) {
            $externalReference = $payload['woocommerce_order_id'];
        }

        $remoteOrderId = isset($payload['remote_order_id']) ? $payload['remote_order_id'] : (isset($payload['order_id']) ? $payload['order_id'] : '');
        $orderKey = isset($payload['woocommerce_order_key']) ? $payload['woocommerce_order_key'] : '';

        $order = $this->locate_order($externalReference, $remoteOrderId, $orderKey);
        if (!$order) {
            return new \WP_REST_Response(array('success' => false, 'error' => 'WooCommerce siparişi bulunamadı.'), 404);
        }

        $adminNote = isset($payload['admin_note']) ? $payload['admin_note'] : null;
        $adminNote = is_string($adminNote) ? trim($adminNote) : '';
        $existingAdminNote = $order->get_meta('_reseller_sync_admin_note', true);

        if ($adminNote !== '') {
            if (function_exists('sanitize_textarea_field')) {
                $adminNote = sanitize_textarea_field(wp_unslash($adminNote));
            } else {
                $adminNote = sanitize_text_field(wp_unslash($adminNote));
            }

            if ($existingAdminNote !== $adminNote) {
                $order->update_meta_data('_reseller_sync_admin_note', $adminNote);
                $order->add_order_note(sprintf(__('Reseller Sync yönetici notu: %s', 'reseller-sync'), $adminNote), true);
            }
        } elseif ($existingAdminNote !== '') {
            $order->delete_meta_data('_reseller_sync_admin_note');
        }

        $status = isset($payload['status']) ? $payload['status'] : '';
        if ($status === 'cancelled') {
            $this->process_cancelled_order($order);
        } elseif ($status === 'completed') {
            $this->process_completed_order($order);
        } elseif ($status === 'processing') {
            $this->process_processing_order($order);
        } elseif ($status === 'pending') {
            $this->process_pending_order($order);
        }

        $order->update_meta_data('_reseller_sync_remote_status', $status);
        $order->save_meta_data();

        return new \WP_REST_Response(array('success' => true), 200);
    }

    private function locate_order($externalReference, $remoteOrderId, $orderKey)
    {
        $reference = is_scalar($externalReference) ? trim((string)$externalReference) : '';
        $remote = is_scalar($remoteOrderId) ? trim((string)$remoteOrderId) : '';
        $key = is_scalar($orderKey) ? trim((string)$orderKey) : '';

        if ($reference !== '') {
            $order = wc_get_order($reference);
            if (!$order) {
                $byKey = wc_get_order_id_by_order_key($reference);
                if ($byKey) {
                    $order = wc_get_order($byKey);
                }
            }

            if (!$order && $key !== '') {
                $byProvidedKey = wc_get_order_id_by_order_key($key);
                if ($byProvidedKey) {
                    $order = wc_get_order($byProvidedKey);
                }
            }

            if ($order) {
                if ($remote !== '') {
                    $this->maybe_index_remote_order_id($order, $remote);
                }
                return $order;
            }
        }

        if ($remote !== '') {
            $orderId = $this->find_order_id_by_remote_reference($remote);
            if ($orderId) {
                $order = wc_get_order($orderId);
                if ($order) {
                    if ($reference !== '') {
                        $this->maybe_index_remote_order_id($order, $remote);
                    }
                    return $order;
                }
            }
        }

        if ($key !== '') {
            $byKey = wc_get_order_id_by_order_key($key);
            if ($byKey) {
                $order = wc_get_order($byKey);
                if ($order) {
                    if ($remote !== '') {
                        $this->maybe_index_remote_order_id($order, $remote);
                    }
                    return $order;
                }
            }
        }

        return null;
    }

    private function find_order_id_by_remote_reference($remoteOrderId)
    {
        global $wpdb;

        $remoteId = is_scalar($remoteOrderId) ? trim((string)$remoteOrderId) : '';
        if ($remoteId === '') {
            return 0;
        }

        $like = '%,' . $wpdb->esc_like($remoteId) . ',%';
        $postId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 1",
            '_reseller_sync_remote_order_ids',
            $like
        ));

        if ($postId) {
            return (int)$postId;
        }

        $jsonEncoded = '%"' . $wpdb->esc_like($remoteId) . '"%';
        $postId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 1",
            '_reseller_sync_remote_orders',
            $jsonEncoded
        ));

        if ($postId) {
            $this->backfill_remote_index((int)$postId);
            return (int)$postId;
        }

        $serializedPattern = '%i:' . $wpdb->esc_like((string)absint($remoteId)) . ';%';
        $postId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 1",
            '_reseller_sync_remote_orders',
            $serializedPattern
        ));

        if ($postId) {
            $this->backfill_remote_index((int)$postId);
            return (int)$postId;
        }

        return 0;
    }

    private function backfill_remote_index($orderId)
    {
        $raw = get_post_meta($orderId, '_reseller_sync_remote_orders', true);
        $ids = $this->normalize_remote_order_ids_from_meta($raw);
        if ($ids) {
            update_post_meta($orderId, '_reseller_sync_remote_order_ids', ',' . implode(',', $ids) . ',');
        }
    }

    private function maybe_index_remote_order_id(\WC_Order $order, $remoteOrderId)
    {
        $remote = is_scalar($remoteOrderId) ? trim((string)$remoteOrderId) : '';
        if ($remote === '') {
            return;
        }

        $existingPrimary = $this->normalize_remote_order_ids_from_meta($order->get_meta('_reseller_sync_remote_orders'));
        if (!in_array($remote, $existingPrimary, true)) {
            $existingPrimary[] = $remote;
            $order->update_meta_data('_reseller_sync_remote_orders', $existingPrimary);
        }

        $indexed = $this->normalize_remote_order_ids_from_meta($order->get_meta('_reseller_sync_remote_order_ids'));
        if (!in_array($remote, $indexed, true)) {
            $indexed[] = $remote;
            $order->update_meta_data('_reseller_sync_remote_order_ids', ',' . implode(',', $indexed) . ',');
        }

        $order->save_meta_data();
    }

    private function sanitize_remote_order_ids($orderIds)
    {
        $list = array();
        if (!is_array($orderIds)) {
            $orderIds = array($orderIds);
        }

        foreach ($orderIds as $id) {
            if (is_object($id)) {
                continue;
            }

            if (is_string($id)) {
                $id = trim($id);
            }

            if ($id === '' || $id === null) {
                continue;
            }

            if (!is_numeric($id)) {
                continue;
            }

            $sanitized = (string)absint($id);
            if ($sanitized === '0') {
                continue;
            }

            if (!in_array($sanitized, $list, true)) {
                $list[] = $sanitized;
            }
        }

        return $list;
    }

    private function normalize_remote_order_ids_from_meta($value)
    {
        if (is_array($value)) {
            return $this->sanitize_remote_order_ids($value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return array();
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return $this->sanitize_remote_order_ids($decoded);
            }

            $maybe = maybe_unserialize($value);
            if (is_array($maybe)) {
                return $this->sanitize_remote_order_ids($maybe);
            }

            if (strpos($trimmed, ',') !== false) {
                return $this->sanitize_remote_order_ids(explode(',', $trimmed));
            }

            if (preg_match_all('/\d+/', $trimmed, $matches)) {
                return $this->sanitize_remote_order_ids($matches[0]);
            }
        }

        return array();
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

    private function process_completed_order(\WC_Order $order)
    {
        if ($order->has_status('completed')) {
            $order->add_order_note(__('Reseller Sync: Sipariş tamamlandı bildirimi alındı.', 'reseller-sync'));
            return;
        }

        $order->update_status('completed', __('Reseller Sync tarafından tamamlandı.', 'reseller-sync'));
    }

    private function process_processing_order(\WC_Order $order)
    {
        if ($order->has_status('processing')) {
            $order->add_order_note(__('Reseller Sync: Sipariş işleme alındı bildirimi alındı.', 'reseller-sync'));
            return;
        }

        $order->update_status('processing', __('Reseller Sync tarafından işleme alındı.', 'reseller-sync'));
    }

    private function process_pending_order(\WC_Order $order)
    {
        if ($order->has_status('pending')) {
            $order->add_order_note(__('Reseller Sync: Sipariş beklemede bildirimi alındı.', 'reseller-sync'));
            return;
        }

        $order->update_status('pending', __('Reseller Sync tarafından beklemeye alındı.', 'reseller-sync'));
    }
}

new Reseller_Sync_Connector();
