<?php declare(strict_types=1);

namespace App\ResellerApi\Http;

final class Response
{
    private int $status;
    private array $headers;
    private string $body;

    public function __construct(int $status = 200, array $headers = array(), string $body = '')
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    public static function json(array $payload, int $status = 200): self
    {
        return new self($status, array('Content-Type' => 'application/json; charset=utf-8'), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }
}
