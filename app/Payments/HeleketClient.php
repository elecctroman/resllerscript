<?php

namespace App\Payments;

use App\Settings;
use RuntimeException;

class HeleketClient
{
    /**
     * @var string
     */
    private $projectId;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @param string|null $projectId
     * @param string|null $apiKey
     * @param string|null $baseUrl
     */
    public function __construct($projectId = null, $apiKey = null, $baseUrl = null)
    {
        $settingsProject = Settings::get('heleket_project_id');
        $settingsKey = Settings::get('heleket_api_key');
        $settingsBase = Settings::get('heleket_base_url');

        $this->projectId = $projectId ?: ($settingsProject ?: '');
        $this->apiKey = $apiKey ?: ($settingsKey ?: '');
        $this->baseUrl = rtrim($baseUrl ?: ($settingsBase ?: 'https://merchant.heleket.com/api'), '/');

        if ($this->projectId === '' || $this->apiKey === '') {
            throw new RuntimeException('Heleket entegrasyonu için proje ID ve API anahtarı gereklidir.');
        }
    }

    /**
     * @param float       $amount
     * @param string      $currency
     * @param string      $orderId
     * @param string      $description
     * @param string|null $payerEmail
     * @param string|null $successUrl
     * @param string|null $failUrl
     * @param string|null $callbackUrl
     * @return array<string,mixed>
     */
    public function createInvoice($amount, $currency, $orderId, $description, $payerEmail = null, $successUrl = null, $failUrl = null, $callbackUrl = null)
    {
        $payload = [
            'project_id' => $this->projectId,
            'amount' => number_format((float)$amount, 2, '.', ''),
            'currency' => strtoupper($currency),
            'order_id' => (string)$orderId,
            'description' => $description,
        ];

        if ($payerEmail) {
            $payload['customer_email'] = $payerEmail;
        }

        if ($successUrl) {
            $payload['success_url'] = $successUrl;
        }

        if ($failUrl) {
            $payload['fail_url'] = $failUrl;
        }

        if ($callbackUrl) {
            $payload['callback_url'] = $callbackUrl;
        }

        $payload['signature'] = $this->buildSignature($payload);

        return $this->postJson('/payments/create', $payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return string
     */
    private function buildSignature(array $payload)
    {
        ksort($payload);
        $normalized = [];
        foreach ($payload as $key => $value) {
            if ($key === 'signature' || $value === null || $value === '') {
                continue;
            }
            $normalized[] = $key . '=' . $value;
        }

        $base = implode('&', $normalized);
        return hash_hmac('sha256', $base, $this->apiKey);
    }

    /**
     * @param array<string,mixed> $payload
     * @param string              $signature
     * @return bool
     */
    public function verifySignature(array $payload, $signature)
    {
        if (!is_string($signature) || $signature === '') {
            return false;
        }

        $expected = $this->buildSignature($payload);
        return hash_equals($expected, $signature);
    }

    /**
     * @param string               $endpoint
     * @param array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function postJson($endpoint, array $payload)
    {
        $url = $this->baseUrl . $endpoint;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Heleket isteği hazırlanamadı.');
        }

        $response = $this->sendRequest($url, $json);
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Heleket API geçersiz bir yanıt döndürdü.');
        }

        if (isset($decoded['status']) && strtolower((string)$decoded['status']) === 'success' && isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }

        if (isset($decoded['success']) && $decoded['success'] && isset($decoded['result']) && is_array($decoded['result'])) {
            return $decoded['result'];
        }

        $message = 'Heleket API hatası oluştu.';
        if (isset($decoded['message'])) {
            $message = (string)$decoded['message'];
        } elseif (isset($decoded['error'])) {
            $message = (string)$decoded['error'];
        }

        throw new RuntimeException($message);
    }

    /**
     * @param string $url
     * @param string $jsonPayload
     * @return string
     */
    private function sendRequest($url, $jsonPayload)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-Project-ID: ' . $this->projectId,
                    'X-Api-Key: ' . $this->apiKey,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                throw new RuntimeException('Heleket API isteği başarısız: ' . $error);
            }

            if ($status >= 400) {
                throw new RuntimeException('Heleket API hata durum kodu döndürdü: ' . $status);
            }

            return (string)$response;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-Project-ID: ' . $this->projectId,
                    'X-Api-Key: ' . $this->apiKey,
                ],
                'content' => $jsonPayload,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $errorMessage = 'Heleket API yanıt vermedi.';
            if (isset($http_response_header) && is_array($http_response_header)) {
                $errorMessage .= ' HTTP: ' . implode(' ', $http_response_header);
            }
            throw new RuntimeException($errorMessage);
        }

        return (string)$response;
    }
}
