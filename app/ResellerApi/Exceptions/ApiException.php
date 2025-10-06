<?php

declare(strict_types=1);

namespace App\ResellerApi\Exceptions;

use RuntimeException;

final class ApiException extends RuntimeException
{
    private string $errorCode;
    private int $statusCode;
    private array $context;

    public function __construct(string $errorCode, string $message, int $statusCode = 400, array $context = array())
    {
        parent::__construct($message, $statusCode);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
        $this->context = $context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string,mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
