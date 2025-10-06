<?php
class Reseller_API_Client
{
    const OPTION_URL = 'reseller_api_url';
    const OPTION_KEY = 'reseller_api_key';

    /**
     * @return string
     */
    public static function get_api_url(): string
    {
        $url = trim((string) get_option(self::OPTION_URL, ''));
        if ($url === '') {
            return '';
        }

        $url = rtrim($url, '/');
        if (substr($url, -3) !== '/v1') {
            $url .= '/v1';
        }

        return $url;
    }

    /**
     * @return string
     */
    public static function get_api_key(): string
    {
        return trim((string) get_option(self::OPTION_KEY, ''));
    }

    /**
     * @return bool
     */
    public static function is_configured(): bool
    {
        return self::get_api_url() !== '' && self::get_api_key() !== '';
    }

    /**
     * @param string $path
     * @param array  $args
     * @param string $method
     *
     * @return array{success:bool,data:mixed,message:string}|WP_Error
     */
    public static function request(string $path, array $args = array(), string $method = 'GET')
    {
        if (!self::is_configured()) {
            return new WP_Error('reseller_api_not_configured', __('API bilgileri eksik.', 'reseller-panel-api'));
        }

        $url = self::get_api_url() . '/' . ltrim($path, '/');

        $requestArgs = array(
            'method'  => $method,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key'    => self::get_api_key(),
            ),
            'timeout' => 15,
        );

        if ($method === 'GET' && !empty($args)) {
            $url = add_query_arg($args, $url);
        } else {
            $requestArgs['body'] = wp_json_encode($args);
        }

        $response = wp_remote_request($url, $requestArgs);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return new WP_Error('reseller_api_invalid_body', __('Geçersiz veya boş yanıt alındı.', 'reseller-panel-api'), array('raw' => $body));
        }

        if ($code >= 200 && $code < 300 && !empty($decoded['success'])) {
            return array(
                'success' => true,
                'data'    => $decoded['data'] ?? array(),
                'message' => $decoded['message'] ?? '',
            );
        }

        $message = $decoded['message'] ?? __('Bilinmeyen bir hata oluştu.', 'reseller-panel-api');
        return new WP_Error('reseller_api_http_error', $message, array('body' => $decoded, 'code' => $code));
    }

    /**
     * @return array|WP_Error
     */
    public static function fetch_profile()
    {
        return self::request('user');
    }

    /**
     * @return array|WP_Error
     */
    public static function fetch_products()
    {
        return self::request('products');
    }

    /**
     * @return array|WP_Error
     */
    public static function fetch_orders()
    {
        return self::request('orders');
    }

    /**
     * @param int         $productId
     * @param string|null $note
     *
     * @return array|WP_Error
     */
    public static function create_order(int $productId, ?string $note = null)
    {
        $payload = array('product_id' => $productId);
        if ($note !== null && $note !== '') {
            $payload['note'] = $note;
        }

        return self::request('orders', $payload, 'POST');
    }
}

