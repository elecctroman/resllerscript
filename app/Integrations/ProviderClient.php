<?php

namespace App\Integrations;

use App\Logger;
use RuntimeException;

class ProviderClient
{
    private string $baseUrl;

    private string $apiKey;

    private ?Logger $logger;

    private int $timeout;

    public function __construct(string $baseUrl, string $apiKey, ?Logger $logger = null, int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = trim($apiKey);
        $this->logger = $logger;
        $this->timeout = max(5, $timeout);

        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new RuntimeException('Sağlayıcı API yapılandırması eksik.');
        }
    }

    /**
     * Sağlayıcı ayarlarının eksiksiz olup olmadığını döndürür.
     */
    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    /**
     * @return array<string,mixed>
     */
    public function testConnection(): array
    {
        return $this->request('GET', '/api/user');
    }

    /**
     * @return array<string,mixed>
     */
    public function fetchProducts(): array
    {
        return $this->request('GET', '/api/products');
    }

    /**
     * @return array<string,mixed>
     */
    public function fetchOrders(): array
    {
        return $this->request('GET', '/api/orders');
    }

    /**
     * @param int    $productId
     * @param string $note
     * @return array<string,mixed>
     */
    public function createOrder(int $productId, string $note = ''): array
    {
        $payload = array('product_id' => $productId);

        if ($note !== '') {
            $payload['note'] = $note;
        }

        return $this->request('POST', '/api/orders', $payload);
    }

    /**
     * @param string               $method
     * @param string               $path
     * @param array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, array $payload = array()): array
    {
        $url = $this->buildUrl($path);
        $headers = array(
            'Accept: application/json',
            'X-API-Key: ' . $this->apiKey,
        );

        $body = null;
        $method = strtoupper($method);

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }

        $responseBody = false;
        $status = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $responseBody = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($responseBody === false) {
                $error = curl_error($ch);
                curl_close($ch);
                $this->log('error', 'Sağlayıcı API isteği başarısız: ' . $error, array('url' => $url));
                throw new RuntimeException('Sağlayıcı API isteği başarısız: ' . $error);
            }

            curl_close($ch);
        } else {
            $contextOptions = array(
                'http' => array(
                    'method' => $method,
                    'header' => implode("\r\n", $headers),
                    'timeout' => $this->timeout,
                    'ignore_errors' => true,
                ),
            );

            if ($body !== null) {
                $contextOptions['http']['content'] = $body;
            }

            $context = stream_context_create($contextOptions);
            $responseBody = @file_get_contents($url, false, $context);

            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $headerLine) {
                    if (stripos($headerLine, 'HTTP/') === 0) {
                        $parts = explode(' ', $headerLine);
                        if (isset($parts[1])) {
                            $status = (int)$parts[1];
                        }
                        break;
                    }
                }
            }

            if ($responseBody === false) {
                $this->log('error', 'Sağlayıcı API isteği okunamadı.', array('url' => $url));
                throw new RuntimeException('Sağlayıcı API isteği okunamadı.');
            }
        }

        $decoded = json_decode((string)$responseBody, true);

        if (!is_array($decoded)) {
            $this->log('error', 'Sağlayıcı API geçersiz yanıt döndürdü.', array('url' => $url, 'status' => $status, 'body' => $responseBody));
            throw new RuntimeException('Sağlayıcı API geçersiz yanıt döndürdü.');
        }

        if ($status >= 400) {
            $this->log('warning', 'Sağlayıcı API hatası', array('url' => $url, 'status' => $status, 'response' => $decoded));
        }

        return $decoded;
    }

    private function buildUrl(string $path): string
    {
        $normalized = $this->baseUrl . '/' . ltrim($path, '/');
        $querySeparator = (strpos($normalized, '?') === false) ? '?' : '&';

        if (!preg_match('/[?&]apikey=/', $normalized)) {
            $normalized .= $querySeparator . 'apikey=' . rawurlencode($this->apiKey);
        }

        return $normalized;
    }

    private function log(string $level, string $message, array $context = array()): void
    {
        if ($this->logger instanceof Logger) {
            switch ($level) {
                case 'error':
                    $this->logger->error($message, $context);
                    break;
                case 'warning':
                    $this->logger->warning($message, $context);
                    break;
                default:
                    $this->logger->info($message, $context);
                    break;
            }
            return;
        }

        $line = $message;
        if ($context) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        error_log('[Provider] ' . $line);
    }
}
