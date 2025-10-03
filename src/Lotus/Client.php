<?php

declare(strict_types=1);

namespace Lotus;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Lotus\Exceptions\ApiError;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;

class Client
{
    private HttpClient $httpClient;
    private ?string $apiKey;
    private string $baseUrl;
    private float $timeout;
    private bool $useQueryApiKey;
    private int $maxRetries;
    private int $retryBaseMs;
    private ?string $lastRequestId = null;

    public function __construct(array $opts = [])
    {
        $this->apiKey = $opts['apiKey'] ?? $_ENV['LOTUS_API_KEY'] ?? null;
        $this->baseUrl = rtrim($opts['baseUrl'] ?? $_ENV['LOTUS_BASE_URL'] ?? 'https://partner.lotuslisans.com.tr', '/');
        $this->timeout = (float) ($opts['timeout'] ?? 20.0);
        $this->useQueryApiKey = (bool) ($opts['useQueryApiKey'] ?? false);
        $this->maxRetries = (int) ($opts['maxRetries'] ?? 3);
        $this->retryBaseMs = (int) ($opts['retryBaseMs'] ?? 250);

        $handlerStack = HandlerStack::create();
        $handlerStack->push($this->createRetryMiddleware());

        $this->httpClient = new HttpClient([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'handler' => $handlerStack,
            'http_errors' => false,
        ]);
    }

    public function getLastRequestId(): ?string
    {
        return $this->lastRequestId;
    }

    public function getUser(): array
    {
        $response = $this->request('GET', '/api/user');

        return $response;
    }

    public function listProducts(array $params = []): array
    {
        $response = $this->request('GET', '/api/products', [
            'query' => $this->normalizeListParams($params),
        ]);

        return ResponseTypes::applyListMeta($response, $params);
    }

    public function createOrder(array $body, array $opts = []): array
    {
        $idempotencyKey = isset($opts['idempotencyKey']) && $opts['idempotencyKey'] !== ''
            ? (string) $opts['idempotencyKey']
            : null;

        $response = $this->request('POST', '/api/orders', [
            'json' => $body,
        ], $idempotencyKey);

        return $response;
    }

    public function listOrders(array $params = []): array
    {
        $response = $this->request('GET', '/api/orders', [
            'query' => $this->normalizeListParams($params),
        ]);

        return ResponseTypes::applyListMeta($response, $params);
    }

    public function getOrderById(int $id): array
    {
        $response = $this->request('GET', sprintf('/api/orders/%d', $id));

        return $response;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $path, array $options = [], ?string $idempotencyKey = null): array
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new ApiError('Lotus API key is not configured. Provide it via constructor or LOTUS_API_KEY env.');
        }

        $requestUuid = Uuid::uuid4()->toString();

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Request-Id' => $requestUuid,
        ];

        if (!$this->useQueryApiKey) {
            $headers['X-API-Key'] = $this->apiKey;
        }

        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        if (isset($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        $options['headers'] = $headers;

        $query = $options['query'] ?? [];
        if ($this->useQueryApiKey) {
            $query['apikey'] = $this->apiKey;
        }

        if (!empty($query)) {
            $options['query'] = $query;
        }

        try {
            $response = $this->httpClient->request($method, $path, $options);
        } catch (GuzzleException $exception) {
            throw new ApiError(
                sprintf('Lotus API request error: %s', $exception->getMessage()),
                0,
                null,
                [],
                $requestUuid,
                $exception
            );
        }

        $this->lastRequestId = $response->getHeaderLine('X-Request-Id') ?: $requestUuid;

        return $this->handleResponse($response);
    }

    private function handleResponse(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $requestId = $response->getHeaderLine('X-Request-Id') ?: $this->lastRequestId;

        $decoded = null;
        if ($body !== '') {
            $decoded = json_decode($body, true);
        }

        if ($decoded === null && $body !== '' && json_last_error() !== JSON_ERROR_NONE) {
            if ($status >= 400) {
                throw new ApiError(
                    'Lotus API returned a non-JSON error response.',
                    $status,
                    null,
                    ['raw' => $body],
                    $requestId
                );
            }

            return [
                'success' => true,
                'data' => $body,
                'request_id' => $requestId,
                'status' => $status,
            ];
        }

        $payload = is_array($decoded) ? $decoded : [];

        $isError = $status >= 400
            || (isset($payload['success']) && $payload['success'] === false);

        if ($isError) {
            $message = $payload['message'] ?? $response->getReasonPhrase() ?: 'Lotus API request failed.';
            $code = null;
            if (isset($payload['code']) && is_string($payload['code'])) {
                $code = $payload['code'];
            } elseif (isset($payload['status']) && is_string($payload['status'])) {
                $code = $payload['status'];
            }

            throw new ApiError(
                $message,
                $status,
                $code,
                $payload,
                $requestId
            );
        }

        $payload = ResponseTypes::normalizeNumericValues($payload);
        $payload = ResponseTypes::appendCreatedAtDateTime($payload);

        if (!isset($payload['request_id'])) {
            $payload['request_id'] = $requestId;
        }

        if (!isset($payload['status'])) {
            $payload['status'] = $status;
        }

        return $payload;
    }

    private function normalizeListParams(array $params): array
    {
        $allowed = ['page', 'per_page', 'status', 'date_from', 'date_to', 'product_id'];
        $normalized = [];
        foreach ($params as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }

            if (in_array($key, ['page', 'per_page', 'product_id'], true)) {
                $normalized[$key] = (int) $value;
                continue;
            }

            if ($key === 'status' && is_string($value)) {
                $normalized[$key] = $value;
                continue;
            }

            if (in_array($key, ['date_from', 'date_to'], true) && is_string($value)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function createRetryMiddleware(): callable
    {
        return Middleware::retry(
            function (int $retries, RequestInterface $request, ?ResponseInterface $response, ?RequestException $exception): bool {
                if ($retries >= $this->maxRetries) {
                    return false;
                }

                if ($response instanceof ResponseInterface) {
                    $status = $response->getStatusCode();
                    if ($status === 429 || ($status >= 500 && $status < 600)) {
                        return true;
                    }
                }

                if ($exception instanceof ConnectException) {
                    return true;
                }

                return false;
            },
            function (int $retries): int {
                if ($retries <= 0) {
                    return 0;
                }

                $base = $this->retryBaseMs * (2 ** ($retries - 1));
                $jitterMax = max(1, (int) floor($this->retryBaseMs / 2));
                $jitter = random_int(0, $jitterMax);

                return (int) $base + $jitter;
            }
        );
    }
}
