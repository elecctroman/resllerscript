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
        $apiUrl = isset($settings['api_url']) ? $settings['api_url'] : '';
        $apiKey = isset($settings['api_key']) ? $settings['api_key'] : '';
        $apiSecret = isset($settings['api_secret']) ? $settings['api_secret'] : '';

        if (empty($apiUrl) || empty($apiKey) || empty($apiSecret)) {
            return new WP_Error('reseller_api_missing_settings', __('API URL, API key or API secret is missing.', 'reseller-api'));
        }

        $baseUrl = $this->normalise_base_url($apiUrl);
        if (is_wp_error($baseUrl)) {
            return $baseUrl;
        }

        $url = $baseUrl . ltrim($endpoint, '/');
        $headers = array(
            'Accept'         => 'application/json',
            'X-API-KEY'      => $apiKey,
            'X-API-SECRET'   => $apiSecret,
            'X-CLIENT-DOMAIN'=> wp_parse_url(home_url(), PHP_URL_HOST),
            'User-Agent'     => 'Reseller-API-WooCommerce/' . Reseller_API_WooCommerce_Plugin::VERSION,
        );

        $requestArgs = array(
            'method'  => strtoupper($method),
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true,
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
            $message = isset($data['error']) ? $data['error'] : (isset($data['message']) ? $data['message'] : wp_remote_retrieve_response_message($response));
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
     * @param string $url
     * @return string|WP_Error
     */
    public function normalise_base_url($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return new WP_Error('reseller_api_missing_url', __('Please provide a valid API URL.', 'reseller-api'));
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $parts = wp_parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return new WP_Error('reseller_api_invalid_url', __('The API URL is not valid.', 'reseller-api'));
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
        $host   = $parts['host'];
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path   = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        if ($path === '' || $path === '/') {
            $path = '/api/v1';
        } elseif (!preg_match('#/api/(v[0-9]+)$#i', $path)) {
            if (substr($path, -4) !== '/api') {
                $path .= '/api';
            }
            $path .= '/v1';
        }

        $normalized = $scheme . '://' . $host . $port . $path;

        return trailingslashit($normalized);
    }

    /**
     * @return array|WP_Error
     */
    public function test_connection()
    {
        $result = $this->request('GET', 'data/profile');
        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'success' => !empty($result['success']),
            'data'    => isset($result['data']) && is_array($result['data']) ? $result['data'] : array(),
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

        $result = $this->request('GET', 'data/products', $params);
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

        $result = $this->request('GET', 'data/profile');
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
        return $this->request('GET', 'data/orders', $filters);
    }

    /**
     * @param array $payload
     * @return array|WP_Error
     */
    public function create_order(array $payload)
    {
        return $this->request('POST', 'data/orders', $payload);
    }
}

