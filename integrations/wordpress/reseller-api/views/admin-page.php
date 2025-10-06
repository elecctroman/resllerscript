<?php
/** @var string $apiUrl */
/** @var string $apiKey */
/** @var array  $profile */
?>
<div class="wrap reseller-api-wrap">
    <h1><?php esc_html_e('Reseller API Ayarları', 'reseller-panel-api'); ?></h1>

    <div id="reseller-api-notice" style="display:none;"></div>

    <form method="post" action="options.php">
        <?php settings_fields('reseller_api_settings'); ?>
        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row"><label for="reseller_api_url"><?php esc_html_e('API URL', 'reseller-panel-api'); ?></label></th>
                <td>
                    <input type="text" name="<?php echo esc_attr(Reseller_API_Client::OPTION_URL); ?>" id="reseller_api_url" class="regular-text" value="<?php echo esc_attr($apiUrl); ?>" placeholder="https://example.com/api" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="reseller_api_key"><?php esc_html_e('API Key', 'reseller-panel-api'); ?></label></th>
                <td>
                    <input type="text" name="<?php echo esc_attr(Reseller_API_Client::OPTION_KEY); ?>" id="reseller_api_key" class="regular-text" value="<?php echo esc_attr($apiKey); ?>" />
                </td>
            </tr>
            </tbody>
        </table>
        <?php submit_button(); ?>
    </form>

    <div class="reseller-api-actions">
        <button class="button button-primary" id="reseller-api-test"><?php esc_html_e('Bağlantıyı Test Et', 'reseller-panel-api'); ?></button>
        <button class="button" id="reseller-api-sync"><?php esc_html_e('Ürünleri Senkronize Et', 'reseller-panel-api'); ?></button>
        <button class="button" id="reseller-api-orders"><?php esc_html_e('API Siparişlerini Getir', 'reseller-panel-api'); ?></button>
    </div>

    <div class="reseller-api-cards">
        <div class="reseller-api-card">
            <h3><?php esc_html_e('Profil Bilgileri', 'reseller-panel-api'); ?></h3>
            <?php if (!empty($profile)) : ?>
                <p><strong><?php esc_html_e('Ad', 'reseller-panel-api'); ?>:</strong> <?php echo esc_html($profile['name'] ?? '-'); ?></p>
                <p><strong><?php esc_html_e('E-posta', 'reseller-panel-api'); ?>:</strong> <?php echo esc_html($profile['email'] ?? '-'); ?></p>
                <p><strong><?php esc_html_e('Bakiye', 'reseller-panel-api'); ?>:</strong> <?php echo esc_html($profile['credit'] ?? '-'); ?></p>
            <?php else : ?>
                <p><?php esc_html_e('API bağlantısı doğrulanamadı.', 'reseller-panel-api'); ?></p>
            <?php endif; ?>
        </div>
        <div class="reseller-api-card">
            <h3><?php esc_html_e('Hızlı Bilgi', 'reseller-panel-api'); ?></h3>
            <p><?php esc_html_e('WooCommerce ürünleriniz, API ürün kimliği ile eşleştirilir.', 'reseller-panel-api'); ?></p>
            <p><?php esc_html_e('Siparişler ödeme sonrası otomatik olarak API&#39;ye iletilir.', 'reseller-panel-api'); ?></p>
            <p><?php esc_html_e('Her ürün için `_reseller_api_product_id` meta alanı kullanılır.', 'reseller-panel-api'); ?></p>
        </div>
    </div>

    <div class="reseller-api-orders">
        <table id="reseller-api-orders-table">
            <thead>
            <tr>
                <th><?php esc_html_e('ID', 'reseller-panel-api'); ?></th>
                <th><?php esc_html_e('Ürün', 'reseller-panel-api'); ?></th>
                <th><?php esc_html_e('Durum', 'reseller-panel-api'); ?></th>
                <th><?php esc_html_e('Tarih', 'reseller-panel-api'); ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td colspan="4"><?php esc_html_e('Henüz veri alınmadı.', 'reseller-panel-api'); ?></td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

