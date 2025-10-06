<?php declare(strict_types=1);

namespace App\Api\Exceptions;

/**
 * Alan doğrulama hatalarını temsil eder.
 */
final class ValidationException extends ApiException
{
    /**
     * @param array<int|string,mixed> $errors
     */
    public function __construct(string $message, array $errors)
    {
        parent::__construct($message, 422, $errors);
    }
}
