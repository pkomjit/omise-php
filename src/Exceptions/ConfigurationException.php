<?php

declare(strict_types=1);

namespace Omise\Exceptions;

/**
 * Exception for configuration errors.
 */
class ConfigurationException extends OmiseException
{
    /**
     * Create exception for missing configuration key.
     */
    public static function missingKey(string $key): self
    {
        return new self("Missing required configuration: {$key}");
    }

    /**
     * Create exception for invalid configuration value.
     */
    public static function invalidValue(string $key, string $reason): self
    {
        return new self("Invalid configuration value for '{$key}': {$reason}");
    }

    /**
     * Create exception for invalid API key format.
     */
    public static function invalidApiKey(string $type): self
    {
        return new self("Invalid {$type} key format. Keys should start with 'pkey_' (public) or 'skey_' (secret)");
    }
}
