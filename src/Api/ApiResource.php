<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Http\HttpClient;
use Omise\Http\Response;

/**
 * Base class for API resources.
 */
abstract class ApiResource
{
    protected HttpClient $client;

    /**
     * The API endpoint for this resource.
     */
    protected string $endpoint;

    /**
     * Whether to use public key for requests (default: false = use secret key).
     */
    protected bool $usePublicKey = false;

    public function __construct(HttpClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get the full endpoint URL for a specific path.
     */
    protected function buildEndpoint(?string $path = null): string
    {
        $endpoint = '/' . ltrim($this->endpoint, '/');

        if ($path !== null) {
            $endpoint .= '/' . ltrim($path, '/');
        }

        return $endpoint;
    }

    /**
     * Create a response object from API data.
     */
    protected function createResponse(array $data, int $statusCode = 200): Response
    {
        return new Response($data, $statusCode);
    }

    /**
     * Retrieve a resource by ID.
     */
    public function retrieve(string $id): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint($id),
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * List resources with optional filters.
     */
    public function all(array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint(),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Check if pagination parameters are valid.
     */
    protected function normalizePaginationParams(array $params): array
    {
        $allowed = ['offset', 'limit', 'from', 'to', 'order'];

        foreach ($params as $key => $value) {
            if (in_array($key, $allowed, true)) {
                if (in_array($key, ['offset', 'limit'], true)) {
                    $params[$key] = (int) $value;
                }
            }
        }

        return $params;
    }
}
