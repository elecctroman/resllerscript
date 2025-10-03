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
        $this->baseUrl = $this->normaliseBaseUrl($baseUrl);
        $this->apiKey = trim($apiKey);
        $this->logger = $logger;
        $this->timeout = max(5, $timeout);

        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new RuntimeException('Sağlayıcı API yapılandırması eksik.');
        }
    }

    private function normaliseBaseUrl(string $baseUrl): string
    {
        $trimmed = rtrim(trim($baseUrl), '/');

        if ($trimmed === '') {
            return '';
        }

        if (preg_match('#/api$#i', $trimmed)) {
            $trimmed = rtrim(substr($trimmed, 0, -4), '/');
        }

        return $trimmed;
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

        $visited = array();
        $attempts = 0;

        do {
            if (isset($visited[$url])) {
                $this->log('error', 'Sağlayıcı API yönlendirme döngüsü algılandı.', array('url' => $url, 'base_url' => $this->baseUrl));
                throw new RuntimeException('Sağlayıcı API yönlendirme döngüsü algılandı. Lütfen temel URL ayarını kontrol edin (örnek: https://ornek.com).');
            }

            $visited[$url] = true;

            $result = $this->performRequest($url, $method, $headers, $body);
            $status = $result['status'];
            $responseBody = $result['body'];
            $responseHeaders = $result['headers'];

            if ($status >= 300 && $status < 400) {
                $location = $this->extractRedirectLocation($responseHeaders);
                if ($location === null) {
                    break;
                }

                $url = $this->resolveRedirectUrl($url, $location);
                $attempts++;

                if ($attempts >= 5) {
                    $this->log('error', 'Sağlayıcı API çok fazla yönlendirme yaptı.', array('last_url' => $url, 'location' => $location, 'base_url' => $this->baseUrl));
                    throw new RuntimeException('Sağlayıcı API çok fazla yönlendirme yaptı. Sağlayıcı adresini doğrulayın ve giriş gereksinimlerini kontrol edin.');
                }

                continue;
            }

            break;
        } while (true);

        $decoded = json_decode((string)$responseBody, true);

        if (!is_array($decoded)) {
            $context = array('url' => $url, 'status' => $status);
            if (is_string($responseBody) && stripos($responseBody, '<html') !== false) {
                $context['hint'] = 'HTML yanıt alındı; muhtemelen sağlayıcı giriş sayfasına yönlendirildi.';
                if (stripos($responseBody, 'lotus partner') !== false || stripos($responseBody, 'login') !== false) {
                    $this->log('error', 'Sağlayıcı API giriş sayfasına yönlendiriyor.', $context);
                    throw new RuntimeException('Sağlayıcı API giriş sayfasına yönlendiriyor. API anahtarınızı ve IP yetkilendirmesini doğrulayın.');
                }
            }
            $this->log('error', 'Sağlayıcı API geçersiz yanıt döndürdü.', $context);
            throw new RuntimeException('Sağlayıcı API geçersiz yanıt döndürdü. Sağlayıcı kimlik bilgilerini ve IP izinlerini kontrol edin.');
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

    /**
     * @param string            $url
     * @param string            $method
     * @param array<int,string> $headers
     * @param string|null       $body
     * @return array{status:int,body:string,headers:array<string,string>}
     */
    private function performRequest(string $url, string $method, array $headers, ?string $body): array
    {
        $status = 0;
        $responseBody = '';
        $responseHeaders = array();

        if (function_exists('curl_init')) {
            $capturedHeaders = array();

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, array('User-Agent: ResellerScript-ProviderClient/1.0')));
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($chResource, $headerLine) use (&$capturedHeaders) {
                $length = strlen($headerLine);
                $headerLine = trim($headerLine);

                if ($headerLine === '') {
                    return $length;
                }

                if (stripos($headerLine, 'HTTP/') === 0) {
                    $capturedHeaders['_status_line'] = $headerLine;
                    return $length;
                }

                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $capturedHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            });

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $responseBody = curl_exec($ch);

            if ($responseBody === false) {
                $error = curl_error($ch);
                curl_close($ch);
                $this->log('error', 'Sağlayıcı API isteği başarısız: ' . $error, array('url' => $url));
                throw new RuntimeException('Sağlayıcı API isteği başarısız: ' . $error);
            }

            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $responseHeaders = $capturedHeaders;
        } else {
            $contextOptions = array(
                'http' => array(
                    'method' => $method,
                    'header' => implode("\r\n", array_merge($headers, array('User-Agent: ResellerScript-ProviderClient/1.0'))),
                    'timeout' => $this->timeout,
                    'ignore_errors' => true,
                ),
            );

            if ($body !== null) {
                $contextOptions['http']['content'] = $body;
            }

            $context = stream_context_create($contextOptions);
            $stream = @fopen($url, 'r', false, $context);

            if ($stream === false) {
                $this->log('error', 'Sağlayıcı API isteği açılamadı.', array('url' => $url));
                throw new RuntimeException('Sağlayıcı API isteği açılamadı.');
            }

            $metadata = stream_get_meta_data($stream);
            $responseBody = stream_get_contents($stream) ?: '';
            fclose($stream);

            if (isset($metadata['wrapper_data']) && is_array($metadata['wrapper_data'])) {
                foreach ($metadata['wrapper_data'] as $headerLine) {
                    $headerLine = trim((string)$headerLine);
                    if ($headerLine === '') {
                        continue;
                    }

                    if (stripos($headerLine, 'HTTP/') === 0) {
                        $responseHeaders['_status_line'] = $headerLine;
                        $parts = explode(' ', $headerLine);
                        if (isset($parts[1])) {
                            $status = (int)$parts[1];
                        }
                        continue;
                    }

                    $parts = explode(':', $headerLine, 2);
                    if (count($parts) === 2) {
                        $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                    }
                }
            }
        }

        return array(
            'status' => $status,
            'body' => (string)$responseBody,
            'headers' => $responseHeaders,
        );
    }

    /**
     * @param array<string,string> $headers
     */
    private function extractRedirectLocation(array $headers): ?string
    {
        foreach ($headers as $key => $value) {
            if ($key === 'location') {
                return $value;
            }
        }

        return null;
    }

    private function resolveRedirectUrl(string $currentUrl, string $location): string
    {
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        $parsed = parse_url($currentUrl);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            return $location;
        }

        $scheme = $parsed['scheme'];
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $location;

        if (!str_starts_with($path, '/')) {
            $basePath = isset($parsed['path']) ? $parsed['path'] : '/';
            $dir = rtrim(dirname($basePath), '/');
            $path = $dir . '/' . $path;
        }

        return $scheme . '://' . $host . $port . $path;
    }
}
