<?php declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Database;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization, X-Requested-With');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!function_exists('api_json_response')) {
    /**
     * @param int   $status
     * @param array $payload
     * @return never
     */
    function api_json_response(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param int         $status
     * @param string      $message
     * @param string|null $errorCode
     * @return never
     */
    function api_error(int $status, string $message, ?string $errorCode = null): void
    {
        $response = array(
            'success' => false,
            'status' => 'failed',
            'message' => $message,
        );

        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        api_json_response($status, $response);
    }

    /**
     * @param array $data
     * @param string $message
     * @param int $status
     * @return never
     */
    function api_success(array $data, string $message = '', int $status = 200): void
    {
        $response = array(
            'success' => true,
            'data' => $data,
        );

        if ($message !== '') {
            $response['message'] = $message;
        }

        api_json_response($status, $response);
    }

    /**
     * @return string|null
     */
    function api_extract_key(): ?string
    {
        $key = null;

        if (isset($_GET['apikey'])) {
            $key = (string) $_GET['apikey'];
        } elseif (isset($_GET['api_key'])) {
            $key = (string) $_GET['api_key'];
        } elseif (!empty($_SERVER['HTTP_X_API_KEY'])) {
            $key = (string) $_SERVER['HTTP_X_API_KEY'];
        } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = (string) $_SERVER['HTTP_AUTHORIZATION'];
            if (stripos($authHeader, 'Bearer ') === 0) {
                $key = trim(substr($authHeader, 7));
            }
        }

        $key = $key !== null ? trim($key) : null;

        return $key !== '' ? $key : null;
    }

    /**
     * @return array<string,mixed>
     */
    function api_authenticated_user(): array
    {
        static $cachedUser = null;
        if ($cachedUser !== null) {
            return $cachedUser;
        }

        $apiKey = api_extract_key();
        if ($apiKey === null) {
            api_error(401, 'Yetkisiz erişim.', 'UNAUTHORIZED');
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT id, name, email, balance, locale, currency, api_key FROM users WHERE api_key = :api_key AND status = :status LIMIT 1');
            $stmt->execute(array('api_key' => $apiKey, 'status' => 'active'));
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $exception) {
            error_log('[API] Kullanıcı doğrulaması başarısız: ' . $exception->getMessage());
            api_error(500, 'Sunucu hatası.', 'INTERNAL_ERROR');
        }

        if (!$user) {
            api_error(401, 'Geçersiz API anahtarı.', 'INVALID_KEY');
        }

        $cachedUser = $user;

        return $cachedUser;
    }

    /**
     * @return array<string,mixed>
     */
    function api_decode_json(): array
    {
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower((string) $_SERVER['CONTENT_TYPE']) : '';
        if (strpos($contentType, 'application/json') !== 0) {
            api_error(400, 'İşlem hatalı.', 'INVALID_CONTENT_TYPE');
        }

        $raw = file_get_contents('php://input');
        $payload = json_decode((string) $raw, true);

        if (!is_array($payload)) {
            api_error(400, 'İşlem hatalı.', 'INVALID_JSON');
        }

        return $payload;
    }
}
