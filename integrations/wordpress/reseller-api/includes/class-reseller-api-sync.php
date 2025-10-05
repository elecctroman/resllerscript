<?php
if (!defined('ABSPATH')) {
    exit;
}

class Reseller_API_Sync
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

    /**
     * @return array|WP_Error
     */
    public function sync_products()
    {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('reseller_api_missing_wc', __('WooCommerce must be active to sync products.', 'reseller-api'));
        }

        $response = $this->client->get_products(array('per_page' => 250));
        if (is_wp_error($response)) {
            return $response;
        }

        $items = array();
        if (isset($response['data']['products']) && is_array($response['data']['products'])) {
            $items = $response['data']['products'];
        } elseif (isset($response['data']) && is_array($response['data'])) {
            $items = $response['data'];
        }

        if (!$items) {
            return array(
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'message' => __('No products were returned by the API.', 'reseller-api'),
            );
        }

        $settings = $this->plugin->get_settings();
        $markup = isset($settings['price_markup']) ? (float) $settings['price_markup'] : 0.0;
        $status = isset($settings['default_status']) ? $settings['default_status'] : 'publish';

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $sku = isset($item['sku']) && $item['sku'] !== '' ? sanitize_text_field($item['sku']) : 'reseller-' . (isset($item['id']) ? $item['id'] : wp_generate_uuid4());
            $name = isset($item['name']) ? sanitize_text_field($item['name']) : (isset($item['title']) ? sanitize_text_field($item['title']) : __('Unnamed Product', 'reseller-api'));
            $description = isset($item['description']) ? wp_kses_post($item['description']) : (isset($item['content']) ? wp_kses_post($item['content']) : '');
            $price = isset($item['price']) ? (float) $item['price'] : (isset($item['amount']) ? (float) $item['amount'] : 0.0);
            $categoryName = isset($item['category_name']) ? sanitize_text_field($item['category_name']) : '';
            $stock = isset($item['stock']) ? (int) $item['stock'] : 0;

            if ($markup > 0) {
                $price = $price + ($price * ($markup / 100));
            }

            $productId = wc_get_product_id_by_sku($sku);
            $isNew = false;

            $postArr = array(
                'post_title'   => $name,
                'post_status'  => $status,
                'post_content' => $description,
                'post_type'    => 'product',
            );

            if ($productId) {
                $postArr['ID'] = $productId;
                $result = wp_update_post($postArr, true);
                if (is_wp_error($result)) {
                    $skipped++;
                    continue;
                }
            } else {
                $productId = wp_insert_post($postArr, true);
                if (is_wp_error($productId)) {
                    $skipped++;
                    continue;
                }
                $isNew = true;
                update_post_meta($productId, '_sku', $sku);
            }

            $product = wc_get_product($productId);
            if (!$product) {
                $skipped++;
                continue;
            }

            $product->set_regular_price($price);
            $product->set_price($price);
            $product->set_catalog_visibility('visible');
            $product->set_manage_stock(false);
            $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
            $product->save();

            if ($categoryName !== '') {
                $term = term_exists($categoryName, 'product_cat');
                if (!$term) {
                    $term = wp_insert_term($categoryName, 'product_cat');
                }
                if (!is_wp_error($term) && isset($term['term_id'])) {
                    wp_set_object_terms($productId, (int) $term['term_id'], 'product_cat', false);
                }
            }

            update_post_meta($productId, '_reseller_remote_id', isset($item['id']) ? $item['id'] : '');
            update_post_meta($productId, '_reseller_provider', isset($item['provider']) ? sanitize_text_field($item['provider']) : '');
            update_post_meta($productId, '_reseller_stock', $stock);

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }
        }

        return array(
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'message' => sprintf(__('Sync finished: %1$d created, %2$d updated, %3$d skipped.', 'reseller-api'), $created, $updated, $skipped),
        );
    }
}

