<?php declare(strict_types=1);

namespace App\Api\Http;

use App\Api\Exceptions\BadRequestException;

/**
 * HTTP istek verilerini temsil eden yardımcı sınıf.
 */
final class Request
{
    private string $method;
    private string $path;
    private string $basePath;
    private string $relativePath;
    /** @var array<string,mixed> */
    private array $query;
    /** @var array<string,string> */
    private array $headers;
    private string $rawBody;
    /** @var array<string,mixed>|null */
    private ?array $jsonBody = null;
    private bool $jsonParsed = false;
    private string $ipAddress;
    /** @var array<string,mixed>|null */
    private ?array $token = null;

    /**
     * @param array<string,mixed> $query
     * @param array<string,string> $headers
     */
    private function __construct(
        string $method,
        string $path,
        string $basePath,
        string $relativePath,
        array $query,
        array $headers,
        string $rawBody,
        string $ipAddress
    ) {
        $this->method = strtoupper($method);
        $this->path = $path !== '' ? $path : '/';
        $this->basePath = $basePath;
        $this->relativePath = $relativePath !== '' ? $relativePath : '/';
        $this->query = $query;
        $this->headers = $headers;
        $this->rawBody = $rawBody;
        $this->ipAddress = $ipAddress;
    }

    public static function fromGlobals(string $basePath = ''): self
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $relative = $path;

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $relative = substr($path, strlen($basePath));
            if ($relative === false || $relative === '') {
                $relative = '/';
            }
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET';
        $query = $_GET ?? array();

        $headers = array();
        if (function_exists('getallheaders')) {
            $serverHeaders = getallheaders();
            if (is_array($serverHeaders)) {
                foreach ($serverHeaders as $name => $value) {
                    $headers[strtolower((string) $name)] = (string) $value;
                }
            }
        }

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                if (!isset($headers[$name])) {
                    $headers[$name] = (string) $value;
                }
            }
        }

        $rawBody = '';
        if (function_exists('api_raw_body')) {
            $rawBody = (string) call_user_func('api_raw_body');
        } else {
            $rawBody = file_get_contents('php://input') ?: '';
        }

        $ip = '0.0.0.0';
        if (function_exists('api_client_ip')) {
            $ip = (string) call_user_func('api_client_ip');
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = (string) $_SERVER['REMOTE_ADDR'];
        }

        return new self($method, $path, $basePath, $relative, $query, $headers, $rawBody, $ip);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->relativePath;
    }

    public function getFullPath(): string
    {
        return $this->path;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @return array<string,mixed>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    public function hasQuery(string $key): bool
    {
        return array_key_exists($key, $this->query);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? $default;
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array<string,mixed>
     */
    public function getJsonBody(): array
    {
        if ($this->jsonParsed) {
            return $this->jsonBody ?? array();
        }

        $this->jsonParsed = true;

        if ($this->rawBody === '') {
            $this->jsonBody = array();
            return $this->jsonBody;
        }

        $decoded = json_decode($this->rawBody, true);
        if (!is_array($decoded)) {
            throw new BadRequestException('Geçersiz JSON içeriği.');
        }

        $this->jsonBody = $decoded;
        return $decoded;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    /**
     * @param array<string,mixed> $token
     */
    public function setToken(array $token): void
    {
        $this->token = $token;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getToken(): ?array
    {
        return $this->token;
    }
}
