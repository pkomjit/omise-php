<?php

declare(strict_types=1);

namespace Omise\Exceptions;

/**
 * Exception for webhook handling errors.
 */
class WebhookException extends OmiseException
{
    /**
     * Create exception for invalid signature.
     */
    public static function invalidSignature(): self
    {
        return new self('Webhook signature verification failed');
    }

    /**
     * Create exception for missing signature header.
     */
    public static function missingSignature(): self
    {
        return new self('Missing Omise-Signature header');
    }

    /**
     * Create exception for missing timestamp header.
     */
    public static function missingTimestamp(): self
    {
        return new self('Missing Omise-Signature-Timestamp header');
    }

    /**
     * Create exception for expired timestamp.
     */
    public static function expiredTimestamp(int $tolerance): self
    {
        return new self("Webhook timestamp is outside the tolerance of {$tolerance} seconds");
    }

    /**
     * Create exception for invalid payload.
     */
    public static function invalidPayload(): self
    {
        return new self('Invalid webhook payload');
    }

    /**
     * Create exception for missing webhook secret.
     */
    public static function missingSecret(): self
    {
        return new self('Webhook secret is not configured');
    }
}
