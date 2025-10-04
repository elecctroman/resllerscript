<?php
header('Content-Type: application/json');

$autoloader = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => 'Konfigürasyon dosyası bulunamadı. Lütfen config/config.php dosyasını oluşturun.',
    ));
    exit;
}

require $configPath;

if (class_exists(App\Migrations\Schema::class)) {
    try {
        App\Migrations\Schema::ensure();
    } catch (\Throwable $exception) {
        error_log('[API] Schema ensure failed: ' . $exception->getMessage());
    }
}

try {
    App\Database::initialize(array(
        'host' => DB_HOST,
        'name' => DB_NAME,
        'user' => DB_USER,
        'password' => DB_PASSWORD,
    ));
} catch (\PDOException $exception) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => 'Veritabanı bağlantısı kurulamadı: ' . $exception->getMessage(),
    ));
    exit;
}

App\Notifications\PreferenceManager::ensureUserColumns();

function api_client_ip(): string
{
    $candidates = array(
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    );

    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', (string)$_SERVER[$key]);
                return trim($parts[0]);
            }
            return trim((string)$_SERVER[$key]);
        }
    }

    return '0.0.0.0';
}

if (!App\FeatureToggle::isEnabled('api')) {
    http_response_code(503);
    echo json_encode(array(
        'success' => false,
        'error' => 'API erişimi geçici olarak devre dışı bırakıldı.',
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_response($data, $statusCode = 200)
{
    global $__apiSecurityContext;
    if (isset($__apiSecurityContext)) {
        $__apiSecurityContext['status'] = $statusCode;
    }
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body()
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return array();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        json_response(array('success' => false, 'error' => 'Geçersiz JSON yükü gönderildi.'), 400);
    }

    return $decoded;
}

function authenticate_token()
{
    $token = '';

    $authHeader = '';

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = (string)$_SERVER['HTTP_AUTHORIZATION'];
    }

    if ($authHeader === '' && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if ($authHeader === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $authHeader = (string)$value;
                    break;
                }
            }
        }
    }

    if ($authHeader !== '') {
        $authHeader = trim($authHeader);
        if (stripos($authHeader, 'Bearer ') === 0) {
            $token = trim(substr($authHeader, 7));
        }
    }

    if ($token === '' && !empty($_SERVER['HTTP_X_API_KEY'])) {
        $token = trim((string)$_SERVER['HTTP_X_API_KEY']);
    }

    if ($token === '' && isset($_GET['api_key'])) {
        $token = trim((string)$_GET['api_key']);
    }

    if ($token === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['api_key'])) {
            $token = trim((string)$_POST['api_key']);
        } elseif (!empty($_POST['token'])) {
            $token = trim((string)$_POST['token']);
        }
    }

    if ($token === '') {
        $ip = api_client_ip();
        $endpoint = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        App\Services\ApiSecurityService::logRequest(null, $ip, $_SERVER['REQUEST_METHOD'] ?? 'GET', $endpoint, 401);
        json_response(array('success' => false, 'error' => 'API anahtarı bulunamadı.'), 401);
    }

    $email = '';

    if (!empty($_SERVER['HTTP_X_RESELLER_EMAIL'])) {
        $email = trim((string)$_SERVER['HTTP_X_RESELLER_EMAIL']);
    } elseif (!empty($_SERVER['HTTP_X_USER_EMAIL'])) {
        $email = trim((string)$_SERVER['HTTP_X_USER_EMAIL']);
    } elseif (!empty($_SERVER['HTTP_X_EMAIL'])) {
        $email = trim((string)$_SERVER['HTTP_X_EMAIL']);
    }

    if ($email === '' && isset($_GET['email'])) {
        $email = trim((string)$_GET['email']);
    }

    if ($email === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['email'])) {
            $email = trim((string)$_POST['email']);
        }
    }

    $tokenRow = null;
    if ($email !== '') {
        $tokenRow = App\ApiToken::findActiveToken($token, $email);
    }

    if (!$tokenRow) {
        $tokenRow = App\ApiToken::findActiveToken($token);
    }

    if (!$tokenRow) {
        global $__apiSecurityContext;
        App\Services\ApiSecurityService::logRequest(null, $__apiSecurityContext['ip'], $__apiSecurityContext['method'], $__apiSecurityContext['endpoint'], 401);
        json_response(array('success' => false, 'error' => 'API anahtarı doğrulanamadı.'), 401);
    }

    global $__apiSecurityContext;
    $__apiSecurityContext['token'] = $tokenRow;

    try {
        App\Services\ApiSecurityService::guard($tokenRow, $__apiSecurityContext['ip'], $__apiSecurityContext['method'], $__apiSecurityContext['endpoint']);
    } catch (\RuntimeException $securityException) {
        App\Services\ApiSecurityService::logRequest($tokenRow, $__apiSecurityContext['ip'], $__apiSecurityContext['method'], $__apiSecurityContext['endpoint'], 429);
        json_response(array('success' => false, 'error' => $securityException->getMessage()), 429);
    }

    return $tokenRow;
}

function require_scope(array $tokenRow, $scope)
{
    global $__apiSecurityContext;

    try {
        App\Services\ApiSecurityService::enforceScope($tokenRow, (string)$scope);
    } catch (\RuntimeException $exception) {
        App\Services\ApiSecurityService::logRequest($tokenRow, $__apiSecurityContext['ip'], $__apiSecurityContext['method'], $__apiSecurityContext['endpoint'], 403);
        json_response(array('success' => false, 'error' => $exception->getMessage()), 403);
    }
}

$__apiSecurityContext = array(
    'token' => null,
    'ip' => api_client_ip(),
    'endpoint' => (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'status' => 200,
);

register_shutdown_function(function () use (&$__apiSecurityContext) {
    try {
        App\Services\ApiSecurityService::logRequest(
            is_array($__apiSecurityContext['token']) ? $__apiSecurityContext['token'] : null,
            (string)$__apiSecurityContext['ip'],
            (string)$__apiSecurityContext['method'],
            (string)$__apiSecurityContext['endpoint'],
            (int)$__apiSecurityContext['status']
        );
    } catch (\Throwable $exception) {
        error_log('[API] Request log yazılamadı: ' . $exception->getMessage());
    }
});
