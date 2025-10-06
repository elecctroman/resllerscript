<?php declare(strict_types=1);

namespace App\Api\Exceptions;

/**
 * Kimlik doğrulama hatalarını temsil eder.
 */
final class AuthenticationException extends ApiException
{
    public function __construct(string $message = 'Kimlik doğrulaması başarısız oldu.')
    {
        parent::__construct($message, 401);
    }
}
