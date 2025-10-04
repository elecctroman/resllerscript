<?php
require __DIR__ . '/../../bootstrap.php';

use App\Customers\CustomerRepository;
use App\Database;
use App\Services\ApiSecurityService;
use App\Settings;

if (!function_exists('customerApiHeaders')) {
    function customerApiHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            return is_array($headers) ? $headers : array();
        }
        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }
}

if (!function_exists('customerApiToken')) {
    function customerApiToken(): ?string
    {
        $headers = customerApiHeaders();
        if (isset($headers['X-API-Key']) && trim($headers['X-API-Key']) !== '') {
            return trim($headers['X-API-Key']);
        }
        if (isset($_GET['api_key']) && trim((string)$_GET['api_key']) !== '') {
            return trim((string)$_GET['api_key']);
        }
        if (!empty($_POST['api_key'])) {
            return trim((string)$_POST['api_key']);
        }
        return null;
    }
}

if (!function_exists('customerApiLog')) {
    function customerApiLog(?int $customerId, string $endpoint, int $status): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('INSERT INTO customer_api_logs (customer_id, ip_address, method, endpoint, status_code, created_at) VALUES (:customer, :ip, :method, :endpoint, :status, NOW())');
            $stmt->execute(array(
                ':customer' => $customerId,
                ':ip' => $ip,
                ':method' => $method,
                ':endpoint' => $endpoint,
                ':status' => $status,
            ));
        } catch (\Throwable $exception) {
            error_log('customer api log error: ' . $exception->getMessage());
        }

        try {
            ApiSecurityService::logRequest(null, $ip, $method, $endpoint, $status);
        } catch (\Throwable $exception) {
            error_log('api request log error: ' . $exception->getMessage());
        }
    }
}

if (!function_exists('customerApiUnauthorizedLimitExceeded')) {
    function customerApiUnauthorizedLimitExceeded(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $pdo = Database::connection();
        $limit = (int)Settings::get('customer_api_unauthorized_limit');
        if ($limit <= 0) {
            $limit = 10;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM customer_api_logs WHERE customer_id IS NULL AND ip_address = :ip AND status_code IN (401,403) AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)');
        $stmt->execute(array(':ip' => $ip));
        return ((int)$stmt->fetchColumn()) >= $limit;
    }
}

if (!function_exists('customerApiGuard')) {
    /**
     * @param array<string,mixed> $customer
     * @param string $endpoint
     * @return void
     */
    function customerApiGuard(array $customer, string $endpoint): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $tokenRow = array(
            'token_status' => $customer['api_status'] ?? 'active',
            'scopes' => $customer['api_scopes'] ?? 'full',
            'ip_whitelist' => $customer['api_ip_whitelist'] ?? '',
            'otp_secret' => $customer['api_otp_secret'] ?? '',
            'token_id' => null,
        );

        try {
            ApiSecurityService::guard($tokenRow, $ip, $method, $endpoint);
            $GLOBALS['customer_api_token_row'] = $tokenRow;
        } catch (\RuntimeException $guardException) {
            http_response_code(403);
            echo json_encode(array('success' => false, 'message' => $guardException->getMessage()));
            customerApiLog((int)$customer['id'], $endpoint, 403);
            exit;
        } catch (\Throwable $exception) {
            http_response_code(500);
            echo json_encode(array('success' => false, 'message' => 'API güvenlik doğrulaması başarısız.'));
            customerApiLog((int)$customer['id'], $endpoint, 500);
            exit;
        }

        $limitPerMinute = (int)Settings::get('customer_api_rate_limit_per_minute');
        if ($limitPerMinute <= 0) {
            $limitPerMinute = 60;
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM customer_api_logs WHERE customer_id = :customer AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
            $stmt->execute(array(':customer' => $customer['id']));
            $customerHits = (int)$stmt->fetchColumn();

            $ipStmt = $pdo->prepare('SELECT COUNT(*) FROM customer_api_logs WHERE ip_address = :ip AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
            $ipStmt->execute(array(':ip' => $ip));
            $ipHits = (int)$ipStmt->fetchColumn();

            if ($customerHits >= $limitPerMinute || $ipHits >= $limitPerMinute * 2) {
                http_response_code(429);
                echo json_encode(array('success' => false, 'message' => 'Çok fazla istek gönderdiniz. Lütfen kısa süre sonra tekrar deneyin.'));
                customerApiLog((int)$customer['id'], $endpoint, 429);
                exit;
            }
        } catch (\Throwable $exception) {
            error_log('customer api rate limit kontrolü başarısız: ' . $exception->getMessage());
        }
    }
}

$apiToken = customerApiToken();
$customer = null;
$statusCode = 200;

if ($apiToken !== null) {
    $customer = CustomerRepository::findByToken($apiToken);
}

if (!$customer) {
    if (customerApiUnauthorizedLimitExceeded()) {
        header('Content-Type: application/json');
        http_response_code(429);
        echo json_encode(array('success' => false, 'message' => 'API erişiminiz geçici olarak sınırlandırıldı. Lütfen birkaç dakika sonra tekrar deneyin.'));
        customerApiLog(null, $_SERVER['REQUEST_URI'] ?? '/api', 429);
        exit;
    }
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'API anahtarı doğrulanamadı.'));
    customerApiLog(null, $_SERVER['REQUEST_URI'] ?? '/api', 401);
    exit;
}

$GLOBALS['customer'] = $customer;

customerApiGuard($customer, $_SERVER['REQUEST_URI'] ?? '/api');

if (!function_exists('customerApiRequireScope')) {
    function customerApiRequireScope(string $scope): void
    {
        $tokenRow = $GLOBALS['customer_api_token_row'] ?? null;
        if (!is_array($tokenRow)) {
            return;
        }

        try {
            ApiSecurityService::enforceScope($tokenRow, $scope);
        } catch (\RuntimeException $scopeException) {
            http_response_code(403);
            echo json_encode(array('success' => false, 'message' => $scopeException->getMessage()));
            customerApiLog((int)$GLOBALS['customer']['id'], $_SERVER['REQUEST_URI'] ?? '/api', 403);
            exit;
        }
    }
}
