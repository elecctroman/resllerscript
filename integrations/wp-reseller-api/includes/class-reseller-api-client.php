<?php

namespace Reseller_Api_Connector;

class Client
{
    private string $optionUrl = 'reseller_api_url';
    private string $optionKey = 'reseller_api_key';
    private string $optionSecret = 'reseller_api_secret';
    private string $optionDomain = 'reseller_api_domain';

    public function get_options(): array
    {
        return array(
            'url' => rtrim((string) get_option($this->optionUrl, ''), '/'),
            'key' => (string) get_option($this->optionKey, ''),
            'secret' => (string) get_option($this->optionSecret, ''),
            'domain' => (string) get_option($this->optionDomain, ''),
        );
    }

    public function is_configured(): bool
    {
        $opts = $this->get_options();
        return $opts['url'] !== '' && $opts['key'] !== '' && $opts['secret'] !== '';
    }

    public function test_connection(): array
    {
        if (!$this->is_configured()) {
            return array('success' => false, 'message' => __('Lütfen API URL ve anahtar bilgilerini kaydedin.', 'reseller-api'));
        }

        $response = $this->request('GET', '/products');
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return array('success' => false, 'message' => sprintf(__('API isteği başarısız: HTTP %d', 'reseller-api'), $code));
        }

        return array('success' => true, 'message' => __('Bağlantı başarıyla doğrulandı.', 'reseller-api'));
    }

    public function request(string $method, string $path, array $body = array())
    {
        $options = $this->get_options();
        $url = $options['url'] . '/v1' . $path;
        $headers = array(
            'X-API-KEY' => $options['key'],
            'X-API-SECRET' => $options['secret'],
            'Accept' => 'application/json',
        );
        if ($options['domain'] !== '') {
            $headers['X-CLIENT-DOMAIN'] = $options['domain'];
        }

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 20,
        );
        if (!empty($body)) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }

        return wp_remote_request($url, $args);
    }

    public function save_options(array $values): void
    {
        update_option($this->optionUrl, isset($values['url']) ? esc_url_raw($values['url']) : '');
        update_option($this->optionKey, isset($values['key']) ? sanitize_text_field($values['key']) : '');
        update_option($this->optionSecret, isset($values['secret']) ? sanitize_text_field($values['secret']) : '');
        update_option($this->optionDomain, isset($values['domain']) ? sanitize_text_field($values['domain']) : '');
    }
}
