<?php

declare(strict_types=1);

namespace Omise\Exceptions;

/**
 * Exception for API communication errors.
 */
class ApiException extends OmiseException
{
    protected int $httpStatusCode;
    protected ?array $responseBody;

    public function __construct(
        string $message = '',
        int $httpStatusCode = 0,
        ?array $responseBody = null,
        ?\Throwable $previous = null
    ) {
        $omiseCode = $responseBody['code'] ?? null;
        $omiseMessage = $responseBody['message'] ?? null;

        parent::__construct(
            message: $message,
            code: $httpStatusCode,
            previous: $previous,
            omiseCode: $omiseCode,
            omiseMessage: $omiseMessage
        );

        $this->httpStatusCode = $httpStatusCode;
        $this->responseBody = $responseBody;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    /**
     * Create exception from HTTP response.
     */
    public static function fromResponse(int $statusCode, array $body): self
    {
        $code = $body['code'] ?? 'api_error';
        $message = $body['message'] ?? 'API request failed';

        return new self(
            message: "API Error [{$code}]: {$message}",
            httpStatusCode: $statusCode,
            responseBody: $body
        );
    }
}
