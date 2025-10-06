<?php declare(strict_types=1);

namespace App\Api\Exceptions;

use RuntimeException;

/**
 * API katmanı için temel istisna sınıfı.
 */
class ApiException extends RuntimeException
{
    protected int $statusCode;
    /** @var array<int|string,mixed> */
    protected array $errors;

    /**
     * @param array<int|string,mixed> $errors
     */
    public function __construct(string $message, int $statusCode = 400, array $errors = array())
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<int|string,mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
