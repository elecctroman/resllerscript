<?php

namespace Reseller_Api_Connector;

class Admin
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function hooks(): void
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_post_reseller_api_save', array($this, 'handle_save'));
        add_action('admin_post_reseller_api_test', array($this, 'handle_test'));
        add_action('admin_post_reseller_api_sync', array($this, 'handle_sync'));
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('Reseller API', 'reseller-api'),
            __('Reseller API', 'reseller-api'),
            'manage_options',
            'reseller-api',
            array($this, 'render_page'),
            'dashicons-rest-api'
        );
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Bu sayfayı görüntüleme yetkiniz yok.', 'reseller-api'));
        }

        $options = $this->client->get_options();
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Reseller API Ayarları', 'reseller-api'); ?></h1>
            <?php if ($message) : ?>
                <div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('reseller_api_save'); ?>
                <input type="hidden" name="action" value="reseller_api_save" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="reseller_api_url"><?php esc_html_e('API URL', 'reseller-api'); ?></label></th>
                        <td><input name="reseller_api_url" id="reseller_api_url" type="url" class="regular-text" value="<?php echo esc_attr($options['url']); ?>" placeholder="https://example.com/api" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="reseller_api_key"><?php esc_html_e('API Key', 'reseller-api'); ?></label></th>
                        <td><input name="reseller_api_key" id="reseller_api_key" type="text" class="regular-text" value="<?php echo esc_attr($options['key']); ?>" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="reseller_api_secret"><?php esc_html_e('API Secret', 'reseller-api'); ?></label></th>
                        <td><input name="reseller_api_secret" id="reseller_api_secret" type="password" class="regular-text" value="<?php echo esc_attr($options['secret']); ?>" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="reseller_api_domain"><?php esc_html_e('Client Domain', 'reseller-api'); ?></label></th>
                        <td><input name="reseller_api_domain" id="reseller_api_domain" type="text" class="regular-text" value="<?php echo esc_attr($options['domain']); ?>" placeholder="woocommerce-site.com" /></td>
                    </tr>
                </table>
                <?php submit_button(__('Kaydet', 'reseller-api')); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 20px;">
                <?php wp_nonce_field('reseller_api_test'); ?>
                <input type="hidden" name="action" value="reseller_api_test" />
                <?php submit_button(__('Bağlantıyı Test Et', 'reseller-api'), 'secondary'); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 20px;">
                <?php wp_nonce_field('reseller_api_sync'); ?>
                <input type="hidden" name="action" value="reseller_api_sync" />
                <?php submit_button(__('Ürünleri Senkronize Et', 'reseller-api'), 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Yetkiniz yok.', 'reseller-api'));
        }
        check_admin_referer('reseller_api_save');
        $this->client->save_options(array(
            'url' => $_POST['reseller_api_url'] ?? '',
            'key' => $_POST['reseller_api_key'] ?? '',
            'secret' => $_POST['reseller_api_secret'] ?? '',
            'domain' => $_POST['reseller_api_domain'] ?? '',
        ));
        wp_safe_redirect(add_query_arg('message', rawurlencode(__('Ayarlar kaydedildi.', 'reseller-api')), admin_url('admin.php?page=reseller-api')));
        exit;
    }

    public function handle_test(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Yetkiniz yok.', 'reseller-api'));
        }
        check_admin_referer('reseller_api_test');
        $result = $this->client->test_connection();
        $message = $result['message'];
        wp_safe_redirect(add_query_arg('message', rawurlencode($message), admin_url('admin.php?page=reseller-api')));
        exit;
    }

    public function handle_sync(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Yetkiniz yok.', 'reseller-api'));
        }
        check_admin_referer('reseller_api_sync');
        do_action('reseller_api_sync_products');
        wp_safe_redirect(add_query_arg('message', rawurlencode(__('Senkronizasyon kuyruğa alındı.', 'reseller-api')), admin_url('admin.php?page=reseller-api')));
        exit;
    }
}
