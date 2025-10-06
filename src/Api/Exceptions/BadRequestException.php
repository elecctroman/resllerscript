<?php declare(strict_types=1);

namespace App\Api\Exceptions;

/**
 * Geçersiz veya eksik istek durumunu temsil eder.
 */
final class BadRequestException extends ApiException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 400);
    }
}
