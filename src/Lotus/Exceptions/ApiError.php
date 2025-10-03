<?php

declare(strict_types=1);

namespace Lotus\Exceptions;

class ApiError extends \RuntimeException
{
    public int $httpStatus;
    public ?string $apiCode;
    public array $details;
    public ?string $requestId;

    public function __construct(
        string $message,
        int $httpStatus = 0,
        ?string $code = null,
        array $details = [],
        ?string $requestId = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->httpStatus = $httpStatus;
        $this->apiCode = $code;
        $this->details = $details;
        $this->requestId = $requestId;
    }

    public function getApiCode(): ?string
    {
        return $this->apiCode;
    }

    public function __get(string $name): mixed
    {
        if ($name === 'code') {
            return $this->apiCode;
        }

        trigger_error(sprintf('Undefined property: %s::$%s', static::class, $name), E_USER_NOTICE);

        return null;
    }
}
