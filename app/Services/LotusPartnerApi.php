<?php

namespace App\Services;

use App\Settings;
use Lotus\Client as LotusClient;
use RuntimeException;

class LotusPartnerApi
{
    public static function isEnabled(): bool
    {
        return Settings::get('lotus_api_enabled') === '1';
    }

    public static function createClient(): LotusClient
    {
        if (!self::isEnabled()) {
            throw new RuntimeException('Lotus API integration is disabled.');
        }

        $apiKey = Settings::get('lotus_api_key');
        if ($apiKey === null || $apiKey === '') {
            throw new RuntimeException('Lotus API anahtarı yapılandırılmadı.');
        }

        $baseUrl = Settings::get('lotus_base_url');
        $useQuery = Settings::get('lotus_use_query_api_key') === '1';
        $timeoutSetting = Settings::get('lotus_timeout');

        $options = [
            'apiKey' => $apiKey,
        ];

        if ($baseUrl && $baseUrl !== '') {
            $options['baseUrl'] = $baseUrl;
        }

        if ($useQuery) {
            $options['useQueryApiKey'] = true;
        }

        if ($timeoutSetting !== null && $timeoutSetting !== '') {
            $timeout = (float) $timeoutSetting;
            if ($timeout > 0) {
                $options['timeout'] = $timeout;
            }
        }

        return new LotusClient($options);
    }

    /**
     * @return array{response: array, request_id: string|null}
     */
    public static function testConnection(array $config = []): array
    {
        $apiKey = isset($config['apiKey']) ? trim((string) $config['apiKey']) : '';
        if ($apiKey === '') {
            $apiKey = (string) Settings::get('lotus_api_key');
        }

        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            throw new RuntimeException('Lotus API anahtarı girilmedi.');
        }

        $options = [
            'apiKey' => $apiKey,
        ];

        $baseUrl = isset($config['baseUrl']) ? trim((string) $config['baseUrl']) : '';
        if ($baseUrl === '') {
            $baseUrlSetting = Settings::get('lotus_base_url');
            if (is_string($baseUrlSetting) && $baseUrlSetting !== '') {
                $baseUrl = trim($baseUrlSetting);
            }
        }

        if ($baseUrl !== '') {
            $options['baseUrl'] = $baseUrl;
        }

        if (isset($config['useQueryApiKey'])) {
            $options['useQueryApiKey'] = (bool) $config['useQueryApiKey'];
        } elseif (Settings::get('lotus_use_query_api_key') === '1') {
            $options['useQueryApiKey'] = true;
        }

        if (isset($config['timeout'])) {
            $timeout = (float) $config['timeout'];
            if ($timeout > 0) {
                $options['timeout'] = $timeout;
            }
        } else {
            $timeoutSetting = Settings::get('lotus_timeout');
            if ($timeoutSetting !== null && $timeoutSetting !== '') {
                $timeout = (float) $timeoutSetting;
                if ($timeout > 0) {
                    $options['timeout'] = $timeout;
                }
            }
        }

        $client = new LotusClient($options);
        $response = $client->getUser();

        return [
            'response' => $response,
            'request_id' => $client->getLastRequestId(),
        ];
    }

    /**
     * @return array{
     *     user: array{response: array, request_id: string|null},
     *     products: array{response: array, request_id: string|null},
     *     orders: array{response: array, request_id: string|null}
     * }
     */
    public static function fetchSnapshot(int $productLimit = 50, int $orderLimit = 25): array
    {
        $productLimit = $productLimit > 0 ? $productLimit : 50;
        $orderLimit = $orderLimit > 0 ? $orderLimit : 25;

        $client = self::createClient();

        $userResponse = $client->getUser();
        $userRequestId = $client->getLastRequestId();

        $productsResponse = $client->listProducts([
            'per_page' => $productLimit,
        ]);
        $productsRequestId = $client->getLastRequestId();

        $ordersResponse = $client->listOrders([
            'per_page' => $orderLimit,
        ]);
        $ordersRequestId = $client->getLastRequestId();

        return [
            'user' => [
                'response' => $userResponse,
                'request_id' => $userRequestId,
            ],
            'products' => [
                'response' => $productsResponse,
                'request_id' => $productsRequestId,
            ],
            'orders' => [
                'response' => $ordersResponse,
                'request_id' => $ordersRequestId,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $product
     * @throws RuntimeException
     */
    public static function resolveRemoteProductId(array $product): int
    {
        if (!isset($product['sku']) || $product['sku'] === null || $product['sku'] === '') {
            throw new RuntimeException('Lotus ürün ID bilgisi için ürün SKU alanını doldurun.');
        }

        $sku = (string) $product['sku'];
        $normalized = trim($sku);

        if ($normalized === '' || !preg_match('/^\d+$/', $normalized)) {
            throw new RuntimeException('Lotus ürün ID\'si SKU alanında yalnızca rakamlardan oluşmalıdır.');
        }

        return (int) $normalized;
    }

    /**
     * @param array<string, mixed> $product
     * @return array{
     *     status: string,
     *     external_reference: string|null,
     *     metadata: array<string, mixed>
     * }
     */
    public static function submitProductOrder(array $product, int $quantity = 1, ?string $note = null): array
    {
        $client = self::createClient();
        $remoteProductId = self::resolveRemoteProductId($product);
        $quantity = max(1, $quantity);

        $responses = [];
        $statuses = [];
        $orderIds = [];

        for ($index = 1; $index <= $quantity; $index++) {
            $body = [
                'product_id' => $remoteProductId,
            ];

            if ($note !== null && $note !== '') {
                $body['note'] = $quantity > 1
                    ? sprintf('[%d/%d] %s', $index, $quantity, $note)
                    : $note;
            }

            $response = $client->createOrder($body);
            $responses[] = $response;

            $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
            if (isset($data['status']) && is_string($data['status'])) {
                $statuses[] = strtolower($data['status']);
            }

            if (isset($data['order_id'])) {
                $orderIds[] = (string) $data['order_id'];
            } elseif (isset($data['id'])) {
                $orderIds[] = (string) $data['id'];
            }
        }

        $localStatus = self::mapStatuses($statuses);
        $reference = self::buildReference($orderIds);

        $metadata = [
            'lotus' => [
                'product_id' => $remoteProductId,
                'orders' => $responses,
            ],
        ];

        return [
            'status' => $localStatus,
            'external_reference' => $reference,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function encodeMetadata(array $metadata): string
    {
        $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Lotus metadata JSON üretilemedi: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * @param array<int, string> $statuses
     */
    private static function mapStatuses(array $statuses): string
    {
        if (!$statuses) {
            return 'processing';
        }

        $hasCompleted = false;
        $hasPending = false;
        $hasCancelled = false;

        foreach ($statuses as $status) {
            switch ($status) {
                case 'completed':
                    $hasCompleted = true;
                    break;
                case 'cancelled':
                    $hasCancelled = true;
                    break;
                default:
                    $hasPending = true;
                    break;
            }
        }

        if ($hasCancelled && !$hasCompleted && !$hasPending) {
            return 'cancelled';
        }

        if ($hasCompleted && !$hasPending && !$hasCancelled) {
            return 'completed';
        }

        if ($hasCompleted && ($hasPending || $hasCancelled)) {
            return 'processing';
        }

        if ($hasPending) {
            return 'processing';
        }

        if ($hasCancelled) {
            return 'cancelled';
        }

        return 'processing';
    }

    /**
     * @param array<int, string> $orderIds
     */
    private static function buildReference(array $orderIds): ?string
    {
        if (!$orderIds) {
            return null;
        }

        if (count($orderIds) === 1) {
            return 'LOTUS#' . $orderIds[0];
        }

        $first = array_shift($orderIds);
        $additional = count($orderIds);

        return sprintf('LOTUS#%s +%d', $first, $additional);
    }
}
