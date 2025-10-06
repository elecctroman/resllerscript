<?php declare(strict_types=1);

namespace App\Api\Http;

/**
 * Standart JSON yanıt gövdesini sarmalayan basit taşıyıcı sınıf.
 */
final class ApiResponse
{
    /** @var array<string,mixed> */
    private array $payload;
    private int $status;

    /**
     * @param array<string,mixed> $payload
     */
    public function __construct(array $payload, int $status = 200)
    {
        $this->payload = $payload;
        $this->status = $status;
    }

    /**
     * @return array<string,mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
