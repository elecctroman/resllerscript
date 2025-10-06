<?php declare(strict_types=1);

namespace App\ResellerApi\Exceptions;

use RuntimeException;

final class ApiException extends RuntimeException
{
    private string $codeName;
    private int $httpStatus;

    public function __construct(string $message, string $codeName, int $httpStatus)
    {
        parent::__construct($message);
        $this->codeName = $codeName;
        $this->httpStatus = $httpStatus;
    }

    public static function unauthorized(string $message = 'Kimlik doğrulaması gerekli.'): self
    {
        return new self($message, 'UNAUTHORIZED', 401);
    }

    public static function forbidden(string $message = 'Bu işlem için yetkiniz bulunmuyor.'): self
    {
        return new self($message, 'FORBIDDEN', 403);
    }

    public static function notFound(string $message): self
    {
        return new self($message, 'NOT_FOUND', 404);
    }

    public static function rateLimited(): self
    {
        return new self('Saatlik istek limiti aşıldı.', 'RATE_LIMIT_EXCEEDED', 429);
    }

    public static function validation(string $message): self
    {
        return new self($message, 'VALIDATION_ERROR', 422);
    }

    public static function badRequest(string $message): self
    {
        return new self($message, 'BAD_REQUEST', 400);
    }

    public function getCodeName(): string
    {
        return $this->codeName;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
