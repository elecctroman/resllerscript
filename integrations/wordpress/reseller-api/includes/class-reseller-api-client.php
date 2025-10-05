<?php
if (!defined('ABSPATH')) {
    exit;
}

class Reseller_API_Client
{
    /**
     * @var Reseller_API_WooCommerce_Plugin
     */
    protected $plugin;

    public function __construct(Reseller_API_WooCommerce_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array  $args
     * @return array|
     */
    public function request($method, $endpoint, array $args = array())
    {
        $settings = $this->plugin->get_settings();
        $apiUrl = isset($settings['api_url']) ? trailingslashit($settings['api_url']) : '';
        $apiKey = isset($settings['api_key']) ? $settings['api_key'] : '';

        if (empty($apiUrl) || empty($apiKey)) {
            return new WP_Error('reseller_api_missing_settings', __('API URL or API key is missing.', 'reseller-api'));
        }

        $url = $apiUrl . ltrim($endpoint, '/');
        $headers = array(
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
        );

        $requestArgs = array(
            'method'  => strtoupper($method),
            'headers' => $headers,
            'timeout' => 30,
        );

        if (!empty($args)) {
            if ('GET' === $requestArgs['method']) {
                $url = add_query_arg($args, $url);
            } else {
                $requestArgs['headers']['Content-Type'] = 'application/json';
                $requestArgs['body'] = wp_json_encode($args);
            }
        }

        $response = wp_remote_request($url, $requestArgs);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code >= 400) {
            $message = isset($data['message']) ? $data['message'] : wp_remote_retrieve_response_message($response);
            if (!$message) {
                $message = __('Unexpected API error occurred.', 'reseller-api');
            }

            return new WP_Error('reseller_api_http_error', $message, array('status' => $code));
        }

        if (!is_array($data)) {
            return new WP_Error('reseller_api_invalid_body', __('The API did not return valid JSON.', 'reseller-api'));
        }

        return $data;
    }

    /**
     * @return array|WP_Error
     */
    public function test_connection()
    {
        $result = $this->request('GET', 'api/user');
        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'success' => !empty($result['success']),
            'data'    => isset($result['data']) ? $result['data'] : array(),
        );
    }

    /**
     * @param array $params
     * @return array|WP_Error
     */
    public function get_products(array $params = array())
    {
        $defaults = array(
            'page'     => 1,
            'per_page' => 100,
        );

        $params = wp_parse_args($params, $defaults);
        $cacheKey = 'reseller_api_products_' . md5(wp_json_encode($params));
        $cached = get_transient($cacheKey);
        if (false !== $cached) {
            return $cached;
        }

        $result = $this->request('GET', 'api/products', $params);
        if (!is_wp_error($result)) {
            set_transient($cacheKey, $result, MINUTE_IN_SECONDS * 5);
        }

        return $result;
    }

    /**
     * @return array|WP_Error
     */
    public function get_profile()
    {
        $cacheKey = 'reseller_api_profile';
        $cached = get_transient($cacheKey);
        if (false !== $cached) {
            return $cached;
        }

        $result = $this->request('GET', 'api/user');
        if (!is_wp_error($result)) {
            set_transient($cacheKey, $result, MINUTE_IN_SECONDS * 5);
        }

        return $result;
    }

    /**
     * @param array $filters
     * @return array|WP_Error
     */
    public function get_orders(array $filters = array())
    {
        $defaults = array(
            'page'     => 1,
            'per_page' => 50,
        );

        $filters = wp_parse_args($filters, $defaults);
        return $this->request('GET', 'api/orders', $filters);
    }

    /**
     * @param array $payload
     * @return array|WP_Error
     */
    public function create_order(array $payload)
    {
        return $this->request('POST', 'api/orders', $payload);
    }
}

