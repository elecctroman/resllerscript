<?php declare(strict_types=1);

namespace App\Api\Exceptions;

/**
 * Yetki yetersizliği durumlarını temsil eder.
 */
final class AuthorizationException extends ApiException
{
    public function __construct(string $message = 'Bu işleme yetkiniz bulunmuyor.')
    {
        parent::__construct($message, 403);
    }
}
