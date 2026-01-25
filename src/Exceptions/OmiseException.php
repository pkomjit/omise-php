<?php

declare(strict_types=1);

namespace Omise\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all Omise-related errors.
 */
class OmiseException extends Exception
{
    protected ?string $omiseCode;
    protected ?string $omiseMessage;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?string $omiseCode = null,
        ?string $omiseMessage = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->omiseCode = $omiseCode;
        $this->omiseMessage = $omiseMessage;
    }

    public function getOmiseCode(): ?string
    {
        return $this->omiseCode;
    }

    public function getOmiseMessage(): ?string
    {
        return $this->omiseMessage;
    }

    /**
     * Create exception from API error response.
     */
    public static function fromApiResponse(array $response): self
    {
        $code = $response['code'] ?? 'unknown_error';
        $message = $response['message'] ?? 'An unknown error occurred';
        $httpCode = $response['http_code'] ?? 500;

        return new self(
            message: "Omise API Error [{$code}]: {$message}",
            code: $httpCode,
            omiseCode: $code,
            omiseMessage: $message
        );
    }
}
