<?php

declare(strict_types=1);

namespace Omise\Http;

use ArrayAccess;
use JsonSerializable;

/**
 * Wrapper for API responses with convenient access methods.
 *
 * @implements ArrayAccess<string, mixed>
 */
class Response implements ArrayAccess, JsonSerializable
{
    private array $data;
    private int $statusCode;

    public function __construct(array $data, int $statusCode = 200)
    {
        $this->data = $data;
        $this->statusCode = $statusCode;
    }

    /**
     * Get the object type from the response.
     */
    public function getObject(): ?string
    {
        return $this->data['object'] ?? null;
    }

    /**
     * Get the ID from the response.
     */
    public function getId(): ?string
    {
        return $this->data['id'] ?? null;
    }

    /**
     * Check if the response represents an error.
     */
    public function isError(): bool
    {
        return $this->getObject() === 'error';
    }

    /**
     * Check if the response is successful.
     */
    public function isSuccessful(): bool
    {
        return !$this->isError() && $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Get a value from the response by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if a key exists in the response.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Get all data from the response.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    // ArrayAccess implementation

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    // JsonSerializable implementation

    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * Convert response to JSON string.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->data, $options);
    }

    /**
     * Magic getter for convenient property access.
     */
    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Magic isset for convenient property checking.
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }
}
