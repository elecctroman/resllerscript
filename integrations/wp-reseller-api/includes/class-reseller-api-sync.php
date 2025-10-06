<?php

namespace Reseller_Api_Connector;

use WC_Product_Simple;
use WC_Order_Item_Product;

class Sync
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function hooks(): void
    {
        add_action('reseller_api_sync_products', array($this, 'sync_products'));
        add_action('woocommerce_order_status_processing', array($this, 'push_order'));
    }

    public function sync_products(): void
    {
        if (!$this->client->is_configured()) {
            return;
        }
        $response = $this->client->request('GET', '/products');
        if (is_wp_error($response)) {
            return;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['success'])) {
            return;
        }
        foreach ($body['data'] as $product) {
            $sku = $product['sku'] ?: 'reseller-' . $product['id'];
            $product_id = wc_get_product_id_by_sku($sku);
            if ($product_id) {
                $wc_product = wc_get_product($product_id);
            } else {
                $wc_product = new WC_Product_Simple();
                $wc_product->set_sku($sku);
            }
            if (!$wc_product) {
                continue;
            }
            $wc_product->set_name($product['name']);
            $wc_product->set_regular_price((string) $product['price']);
            $wc_product->set_description($product['description']);
            $wc_product->set_catalog_visibility('visible');
            $wc_product->save();
            update_post_meta($wc_product->get_id(), '_reseller_product_id', $product['id']);
        }
    }

    public function push_order(int $order_id): void
    {
        if (!$this->client->is_configured()) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        foreach ($order->get_items() as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            $product_id = $item->get_product_id();
            $remote_id = (int) get_post_meta($product_id, '_reseller_product_id', true);
            if ($remote_id <= 0) {
                continue;
            }
            $qty = (int) $item->get_quantity();
            for ($i = 0; $i < $qty; $i++) {
                $payload = array(
                    'product_id' => $remote_id,
                    'note' => sprintf('WooCommerce order #%d', $order_id),
                    'external_reference' => $order->get_order_number(),
                );
                $this->client->request('POST', '/order/create', $payload);
            }
        }
    }
}
