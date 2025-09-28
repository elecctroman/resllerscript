<?php

namespace App\Payments;

use App\Settings;
use RuntimeException;

class CryptomusClient
{
    /**
     * @var string
     */
    private $merchantUuid;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @param string|null $merchant
     * @param string|null $apiKey
     * @param string|null $baseUrl
     */
    public function __construct($merchant = null, $apiKey = null, $baseUrl = null)
    {
        $settingsMerchant = Settings::get('cryptomus_merchant_uuid');
        $settingsApiKey = Settings::get('cryptomus_api_key');
        $settingsBaseUrl = Settings::get('cryptomus_base_url');

        $this->merchantUuid = $merchant ?: ($settingsMerchant ?: '');
        $this->apiKey = $apiKey ?: ($settingsApiKey ?: '');
        $this->baseUrl = rtrim($baseUrl ?: ($settingsBaseUrl ?: 'https://api.cryptomus.com/v1'), '/');

        if ($this->merchantUuid === '' || $this->apiKey === '') {
            throw new RuntimeException('Cryptomus entegrasyon bilgileri eksik.');
        }
    }

    /**
     * @param array $payload
     * @return array
     */
    private function send(array $payload)
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Ödeme isteği hazırlanamadı.');
        }

        $signature = $this->buildSignature($json);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'merchant: ' . $this->merchantUuid,
                    'sign: ' . $signature,
                ],
                'content' => $json,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $endpoint = $this->baseUrl . '/payment';
        $response = @file_get_contents($endpoint, false, $context);

        if ($response === false) {
            $errorMessage = 'Cryptomus API yanıt vermedi.';
            if (isset($http_response_header) && is_array($http_response_header)) {
                $errorMessage .= ' HTTP: ' . implode(' ', $http_response_header);
            }
            throw new RuntimeException($errorMessage);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Cryptomus API geçersiz bir yanıt döndürdü.');
        }

        if (isset($decoded['result']) && is_array($decoded['result'])) {
            return $decoded['result'];
        }

        $message = isset($decoded['message']) ? (string)$decoded['message'] : 'Cryptomus API hatası oluştu.';
        throw new RuntimeException($message);
    }

    /**
     * @param float  $amount
     * @param string $currency
     * @param string $orderId
     * @param string $description
     * @param string|null $payerEmail
     * @param string|null $successUrl
     * @param string|null $failUrl
     * @param string|null $callbackUrl
     * @return array
     */
    public function createInvoice($amount, $currency, $orderId, $description, $payerEmail = null, $successUrl = null, $failUrl = null, $callbackUrl = null)
    {
        $payload = [
            'amount' => number_format((float)$amount, 2, '.', ''),
            'currency' => strtoupper($currency),
            'order_id' => (string)$orderId,
            'description' => $description,
            'lifetime' => 3600,
            'is_payment_multiple' => false,
        ];

        if ($payerEmail) {
            $payload['payer_email'] = $payerEmail;
        }

        if ($successUrl) {
            $payload['url_success'] = $successUrl;
            $payload['url_return'] = $successUrl;
        }

        if ($failUrl) {
            $payload['url_fail'] = $failUrl;
        }

        if ($callbackUrl) {
            $payload['url_callback'] = $callbackUrl;
        }

        $network = Settings::get('cryptomus_network');
        if ($network) {
            $payload['network'] = $network;
        }

        return $this->send($payload);
    }

    /**
     * @param string $jsonPayload
     * @return string
     */
    private function buildSignature($jsonPayload)
    {
        $hash = hash_hmac('sha256', $jsonPayload, $this->apiKey, true);
        return base64_encode($hash);
    }

    /**
     * @param array $payload
     * @param string $signature
     * @return bool
     */
    public function verifySignature(array $payload, $signature)
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $expected = $this->buildSignature($json);
        return hash_equals($expected, (string)$signature);
    }
}
