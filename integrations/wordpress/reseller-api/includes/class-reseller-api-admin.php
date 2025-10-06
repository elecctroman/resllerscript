<?php
if (!defined('ABSPATH')) {
    exit;
}

class Reseller_API_Admin
{
    /**
     * @var Reseller_API_WooCommerce_Plugin
     */
    protected $plugin;

    /**
     * @var Reseller_API_Client
     */
    protected $client;

    public function __construct(Reseller_API_WooCommerce_Plugin $plugin, Reseller_API_Client $client)
    {
        $this->plugin = $plugin;
        $this->client = $client;
    }

    public function register_hooks()
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function register_menu()
    {
        $capability = $this->get_capability();
        $slug = $this->plugin->get_menu_slug();

        add_menu_page(
            __('Reseller API', 'reseller-api'),
            __('Reseller API', 'reseller-api'),
            $capability,
            $slug,
            array($this, 'render_page'),
            'dashicons-update-alt',
            56
        );

        add_submenu_page(
            $slug,
            __('Bridge Dashboard', 'reseller-api'),
            __('Bridge Dashboard', 'reseller-api'),
            $capability,
            $slug,
            array($this, 'render_page')
        );
    }

    protected function get_capability()
    {
        return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
    }

    public function register_settings()
    {
        $page = $this->plugin->get_menu_slug();

        register_setting('reseller_api_settings_group', Reseller_API_WooCommerce_Plugin::OPTION_KEY, array($this, 'sanitize_settings'));

        add_settings_section(
            'reseller_api_connection_section',
            __('Connection', 'reseller-api'),
            '__return_false',
            $page
        );

        add_settings_field(
            'reseller_api_url',
            __('API URL', 'reseller-api'),
            array($this, 'render_field_url'),
            $page,
            'reseller_api_connection_section'
        );

        add_settings_field(
            'reseller_api_key',
            __('API Key', 'reseller-api'),
            array($this, 'render_field_key'),
            $page,
            'reseller_api_connection_section'
        );

        add_settings_field(
            'reseller_api_secret',
            __('API Secret', 'reseller-api'),
            array($this, 'render_field_secret'),
            $page,
            'reseller_api_connection_section'
        );

        add_settings_field(
            'reseller_api_domain',
            __('Client Domain Override', 'reseller-api'),
            array($this, 'render_field_domain'),
            $page,
            'reseller_api_connection_section'
        );

        add_settings_section(
            'reseller_api_sync_section',
            __('Synchronisation', 'reseller-api'),
            '__return_false',
            $page
        );

        add_settings_field(
            'reseller_api_auto_sync',
            __('Automatic Sync', 'reseller-api'),
            array($this, 'render_field_auto_sync'),
            $page,
            'reseller_api_sync_section'
        );

        add_settings_field(
            'reseller_api_default_status',
            __('Default Product Status', 'reseller-api'),
            array($this, 'render_field_status'),
            $page,
            'reseller_api_sync_section'
        );

        add_settings_field(
            'reseller_api_price_markup',
            __('Price Mark-up (%)', 'reseller-api'),
            array($this, 'render_field_markup'),
            $page,
            'reseller_api_sync_section'
        );
    }

    public function sanitize_settings($settings)
    {
        $settings = is_array($settings) ? $settings : array();

        $rawUrl = isset($settings['api_url']) ? $settings['api_url'] : '';
        $normalizedUrl = $this->client->normalise_base_url($rawUrl);
        if (is_wp_error($normalizedUrl)) {
            add_settings_error('reseller_api_url', 'reseller_api_url', $normalizedUrl->get_error_message(), 'error');
            $settings['api_url'] = esc_url_raw($rawUrl);
        } else {
            $settings['api_url'] = esc_url_raw($normalizedUrl);
        }
        $settings['api_key'] = isset($settings['api_key']) ? sanitize_text_field($settings['api_key']) : '';
        $settings['api_secret'] = isset($settings['api_secret']) ? sanitize_text_field($settings['api_secret']) : '';
        $settings['client_domain'] = isset($settings['client_domain']) ? sanitize_text_field($settings['client_domain']) : '';
        $settings['auto_sync'] = isset($settings['auto_sync']) ? sanitize_text_field($settings['auto_sync']) : 'hourly';
        $settings['default_status'] = isset($settings['default_status']) ? sanitize_text_field($settings['default_status']) : 'publish';
        $settings['price_markup'] = isset($settings['price_markup']) ? sanitize_text_field($settings['price_markup']) : '0';

        $this->plugin->update_settings($settings);

        return $this->plugin->get_settings();
    }

    public function enqueue_assets($hook)
    {
        if ('toplevel_page_' . $this->plugin->get_menu_slug() !== $hook) {
            return;
        }

        $pluginFile = dirname(__DIR__) . '/reseller-api-woocommerce.php';

        $version = Reseller_API_WooCommerce_Plugin::VERSION;
        wp_enqueue_style('reseller-api-admin', plugins_url('assets/admin.css', $pluginFile), array(), $version);
        wp_enqueue_script('reseller-api-admin', plugins_url('assets/admin.js', $pluginFile), array('jquery'), $version, true);
        wp_localize_script('reseller-api-admin', 'ResellerAPI', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('reseller_api_actions'),
            'homeDomain' => wp_parse_url(home_url(), PHP_URL_HOST),
            'pageHook' => $hook,
        ));
        wp_localize_script('reseller-api-admin', 'resellerApiStrings', array(
            'sending' => __('Contacting reseller API…', 'reseller-api'),
            'error'   => __('Unexpected API error occurred.', 'reseller-api'),
            'connection_ok' => __('Connection verified successfully.', 'reseller-api'),
            'connection_fail' => __('Connection failed. Please review the settings below.', 'reseller-api'),
            'balance_label' => __('Current Balance', 'reseller-api'),
            'orders_label' => __('Recent Orders', 'reseller-api'),
        ));
    }

    public function render_field_url()
    {
        $settings = $this->plugin->get_settings();
        printf('<input type="url" class="regular-text" name="%1$s[api_url]" value="%2$s" placeholder="https://resellers.pckeystore.com/api/v1/" />', esc_attr(Reseller_API_WooCommerce_Plugin::OPTION_KEY), esc_attr($settings['api_url']));
    }

    public function render_field_key()
    {
        $settings = $this->plugin->get_settings();
        printf('<input type="text" class="regular-text" name="%1$s[api_key]" value="%2$s" placeholder="sk_live_xxx" />', esc_attr(Reseller_API_WooCommerce_Plugin::OPTION_KEY), esc_attr($settings['api_key']));
        echo '<p class="description">' . esc_html__('Generate an API key from your reseller profile and paste it here.', 'reseller-api') . '</p>';
    }

    public function render_field_secret()
    {
        $settings = $this->plugin->get_settings();
        printf('<input type="password" class="regular-text" name="%1$s[api_secret]" value="%2$s" placeholder="secret_xxx" />', esc_attr(Reseller_API_WooCommerce_Plugin::OPTION_KEY), esc_attr($settings['api_secret']));
        echo '<p class="description">' . esc_html__('Copy the API secret generated alongside your key. It is required for HMAC validation.', 'reseller-api') . '</p>';
    }

    public function render_field_domain()
    {
        $settings = $this->plugin->get_settings();
        $defaultDomain = wp_parse_url(home_url(), PHP_URL_HOST);
        printf('<input type="text" class="regular-text" name="%1$s[client_domain]" value="%2$s" placeholder="%3$s" />',
            esc_attr(Reseller_API_WooCommerce_Plugin::OPTION_KEY),
            esc_attr(isset($settings['client_domain']) ? $settings['client_domain'] : ''),
            esc_attr($defaultDomain)
        );
        echo '<p class="description">' . esc_html__('Override the X-CLIENT-DOMAIN header if your reseller account restricts requests to a specific hostname.', 'reseller-api') . '</p>';
    }

    public function render_field_auto_sync()
    {
        $settings = $this->plugin->get_settings();
        $options = array(
            'manual' => __('Manual', 'reseller-api'),
            'hourly' => __('Hourly', 'reseller-api'),
            'twicedaily' => __('Twice Daily', 'reseller-api'),
            'daily' => __('Daily', 'reseller-api'),
        );

        echo '<select name="' . esc_attr(Reseller_API_WooCommerce_Plugin::OPTION_KEY) . '[auto_sync]">';
        foreach ($options as $value => $label) {
            printf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($value), selected($settings['auto_sync'], $value, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Controls how often the plugin should refresh products from the reseller API.', 'reseller-api') . '</p>';
    }

    public function render_field_status()
    {
        $settings = $this->plugin->get_settings();
        $statuses = get_post_statuses();
        echo '<select name="' . esc_attr(Reseller_API_WooCommerce_Plugin::OPTION_KEY) . '[default_status]">';
        foreach ($statuses as $key => $label) {
            printf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($key), selected($settings['default_status'], $key, false), esc_html($label));
        }
        echo '</select>';
    }

    public function render_field_markup()
    {
        $settings = $this->plugin->get_settings();
        printf('<input type="number" step="0.01" min="0" class="small-text" name="%1$s[price_markup]" value="%2$s" /> <span class="description">%3$s</span>',
            esc_attr(Reseller_API_WooCommerce_Plugin::OPTION_KEY),
            esc_attr($settings['price_markup']),
            esc_html__('Applies a percentage increase to imported product prices.', 'reseller-api')
        );
    }

    public function render_page()
    {
        if (!current_user_can($this->get_capability())) {
            return;
        }

        $settings = $this->plugin->get_settings();
        ?>
        <div class="wrap reseller-api-wrap">
            <h1><?php esc_html_e('Reseller API Bridge', 'reseller-api'); ?></h1>
            <p class="description"><?php esc_html_e('Configure your reseller API connection and synchronise products with WooCommerce.', 'reseller-api'); ?></p>

            <div class="reseller-api-layout">
                <div class="reseller-api-column">
                    <form method="post" action="options.php" class="reseller-api-settings-form">
                        <?php
                        settings_fields('reseller_api_settings_group');
                        do_settings_sections($this->plugin->get_menu_slug());
                        submit_button();
                        ?>
                    </form>
                </div>
                <div class="reseller-api-column reseller-api-column--widgets">
                    <div class="reseller-api-card" id="reseller-api-status-card">
                        <div class="reseller-api-card__header">
                            <span class="dashicons dashicons-shield"></span>
                            <h2><?php esc_html_e('Connection Status', 'reseller-api'); ?></h2>
                        </div>
                        <p class="reseller-api-card__lead" data-reseller-api="status">—</p>
                        <p class="description" data-reseller-api="status-hint"><?php esc_html_e('Run a live connectivity check with your reseller API credentials.', 'reseller-api'); ?></p>
                        <button class="button button-primary" data-reseller-api-action="test"><?php esc_html_e('Test Connection', 'reseller-api'); ?></button>
                    </div>

                    <div class="reseller-api-card" id="reseller-api-balance-card">
                        <div class="reseller-api-card__header">
                            <span class="dashicons dashicons-vault"></span>
                            <h2><?php esc_html_e('Balance Snapshot', 'reseller-api'); ?></h2>
                        </div>
                        <p class="reseller-api-card__lead" data-reseller-api="balance">—</p>
                        <p class="description" data-reseller-api="balance-email">&nbsp;</p>
                        <button class="button" data-reseller-api-action="balance"><?php esc_html_e('Refresh Balance', 'reseller-api'); ?></button>
                    </div>

                    <div class="reseller-api-card" id="reseller-api-orders-card">
                        <div class="reseller-api-card__header">
                            <span class="dashicons dashicons-list-view"></span>
                            <h2><?php esc_html_e('Recent Orders', 'reseller-api'); ?></h2>
                        </div>
                        <table class="widefat fixed" data-reseller-api="orders-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Order ID', 'reseller-api'); ?></th>
                                    <th><?php esc_html_e('Product', 'reseller-api'); ?></th>
                                    <th><?php esc_html_e('Status', 'reseller-api'); ?></th>
                                    <th><?php esc_html_e('Total', 'reseller-api'); ?></th>
                                    <th><?php esc_html_e('Created', 'reseller-api'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="placeholder">
                                    <td colspan="5"><?php esc_html_e('No orders fetched yet.', 'reseller-api'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                        <button class="button" data-reseller-api-action="orders"><?php esc_html_e('Fetch Latest Orders', 'reseller-api'); ?></button>
                    </div>
                </div>
            </div>

            <hr />

            <h2><?php esc_html_e('On-demand Tools', 'reseller-api'); ?></h2>
            <p><?php esc_html_e('Use the actions below for manual synchronisation or debugging. All responses appear in the live console.', 'reseller-api'); ?></p>

            <div class="reseller-api-actions">
                <button class="button" data-reseller-api-action="sync"><?php esc_html_e('Sync Products Now', 'reseller-api'); ?></button>
                <button class="button" data-reseller-api-action="test"><?php esc_html_e('Test Connection', 'reseller-api'); ?></button>
                <button class="button" data-reseller-api-action="balance"><?php esc_html_e('Fetch Balance', 'reseller-api'); ?></button>
                <button class="button" data-reseller-api-action="orders"><?php esc_html_e('Fetch Latest Orders', 'reseller-api'); ?></button>
            </div>

            <div class="reseller-api-console" id="reseller-api-console"></div>
        </div>
        <?php
    }

    public function ajax_test_connection()
    {
        $this->verify_ajax();

        $result = $this->client->test_connection();
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    public function ajax_sync_products()
    {
        $this->verify_ajax();

        $sync = new Reseller_API_Sync($this->plugin, $this->client);
        $summary = $sync->sync_products();

        if (is_wp_error($summary)) {
            wp_send_json_error(array('message' => $summary->get_error_message()));
        }

        wp_send_json_success($summary);
    }

    public function ajax_fetch_balance()
    {
        $this->verify_ajax();

        $profile = $this->client->get_profile();
        if (is_wp_error($profile)) {
            wp_send_json_error(array('message' => $profile->get_error_message()));
        }

        $userData = array();
        if (isset($profile['data']['user']) && is_array($profile['data']['user'])) {
            $userData = $profile['data']['user'];
        } elseif (isset($profile['data']) && is_array($profile['data'])) {
            $userData = $profile['data'];
        }

        $balance = isset($userData['balance']) ? $userData['balance'] : (isset($profile['data']['credit']) ? $profile['data']['credit'] : 0);
        $email = isset($userData['email']) ? $userData['email'] : (isset($profile['data']['email']) ? $profile['data']['email'] : '');

        wp_send_json_success(array(
            'balance' => $balance,
            'email'   => $email,
        ));
    }

    public function ajax_fetch_orders()
    {
        $this->verify_ajax();

        $orders = $this->client->get_orders(array('per_page' => 10));
        if (is_wp_error($orders)) {
            wp_send_json_error(array('message' => $orders->get_error_message()));
        }

        wp_send_json_success($orders);
    }

    protected function verify_ajax()
    {
        check_ajax_referer('reseller_api_actions', 'nonce');

        if (!current_user_can($this->get_capability())) {
            wp_send_json_error(array('message' => __('You do not have permission to manage the reseller bridge.', 'reseller-api')));
        }
    }
}

