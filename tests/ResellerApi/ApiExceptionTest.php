<?php declare(strict_types=1);

require_once __DIR__ . '/../../app/ResellerApi/Exceptions/ApiException.php';

use App\ResellerApi\Exceptions\ApiException;
use PHPUnit\Framework\TestCase;

final class ApiExceptionTest extends TestCase
{
    public function testRateLimitExceptionHasExpectedCode(): void
    {
        $exception = ApiException::rateLimited();
        $this->assertSame('RATE_LIMIT_EXCEEDED', $exception->getCodeName());
        $this->assertSame(429, $exception->getHttpStatus());
    }

    public function testValidationExceptionHasExpectedHttpStatus(): void
    {
        $exception = ApiException::validation('Hata');
        $this->assertSame('VALIDATION_ERROR', $exception->getCodeName());
        $this->assertSame(422, $exception->getHttpStatus());
        $this->assertSame('Hata', $exception->getMessage());
    }
}
