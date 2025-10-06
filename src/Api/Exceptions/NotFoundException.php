<?php declare(strict_types=1);

namespace App\Api\Exceptions;

/**
 * Kaynak bulunamadığında kullanılan istisna.
 */
final class NotFoundException extends ApiException
{
    public function __construct(string $message = 'Kayıt bulunamadı.')
    {
        parent::__construct($message, 404);
    }
}
