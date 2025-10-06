<?php
if (!defined('ABSPATH')) {
    exit;
}

class RS_Api_Client
{
    private function get_base_url(): string
    {
        $url = (string) get_option(RS_Api_Bridge::OPTION_URL, '');
        return rtrim($url, '/');
    }

    private function get_headers(): array
    {
        $headers = array('Accept' => 'application/json');
        $apiKey = (string) get_option(RS_Api_Bridge::OPTION_KEY, '');
        if ($apiKey !== '') {
            $headers['X-API-Key'] = $apiKey;
        }

        return $headers;
    }

    public function get_products()
    {
        $url = $this->get_base_url() . '/products';
        $response = wp_remote_get($url, array(
            'headers' => $this->get_headers(),
            'timeout' => 20,
        ));

        return $this->parse_response($response);
    }

    public function create_order(array $payload)
    {
        $url = $this->get_base_url() . '/order/create';
        $response = wp_remote_post($url, array(
            'headers' => array_merge($this->get_headers(), array('Content-Type' => 'application/json')),
            'body' => wp_json_encode($payload),
            'timeout' => 20,
        ));

        return $this->parse_response($response);
    }

    public function get_order_status(array $query)
    {
        $url = $this->get_base_url() . '/order/status';
        $response = wp_remote_get(add_query_arg($query, $url), array(
            'headers' => $this->get_headers(),
            'timeout' => 20,
        ));

        return $this->parse_response($response);
    }

    private function parse_response($response)
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($status >= 400) {
            $message = isset($json['error']) ? (string) $json['error'] : __('Bilinmeyen API hatası.', 'reseller-api');
            return new WP_Error('rs_api_error', $message, $json);
        }

        if (!is_array($json)) {
            return new WP_Error('rs_api_invalid', __('API yanıtı çözümlenemedi.', 'reseller-api'));
        }

        return $json;
    }
}
