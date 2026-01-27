<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Chain API for managing merchant chains.
 *
 * Chains allow a platform (master account) to connect with multiple
 * sub-merchants and process payments on their behalf.
 *
 * @see https://docs.omise.co/chains-api
 */
class Chain extends ApiResource
{
    protected string $endpoint = 'chains';

    /**
     * Retrieve a chain by ID.
     *
     * @param  string $id  Chain ID
     * @throws ApiException
     */
    public function retrieve(string $id): Response
    {
        return parent::retrieve($id);
    }

    /**
     * List all chains with optional filters.
     *
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function all(array $params = []): Response
    {
        return parent::all($this->normalizePaginationParams($params));
    }

    /**
     * List API keys for a chain.
     *
     * @param  string $chainId  Chain ID
     * @param  array $params  List parameters
     * @throws ApiException
     */
    public function keys(string $chainId, array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint("{$chainId}/keys"),
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Get the account for a chain.
     *
     * @param  string $chainId  Chain ID
     * @throws ApiException
     */
    public function account(string $chainId): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint("{$chainId}/account"),
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Revoke/delete a chain.
     *
     * @param  string $chainId  Chain ID
     * @throws ApiException
     */
    public function revoke(string $chainId): Response
    {
        $data = $this->client->post(
            $this->buildEndpoint("{$chainId}/revoke"),
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Get the chain email.
     */
    public function getEmail(Response $chain): ?string
    {
        return $chain->get('email');
    }

    /**
     * Get the location (country).
     */
    public function getLocation(Response $chain): ?string
    {
        return $chain->get('location');
    }

    /**
     * Check if the chain is revoked.
     */
    public function isRevoked(Response $chain): bool
    {
        return (bool) $chain->get('revoked', false);
    }

    /**
     * Check if the chain is a sub-merchant.
     */
    public function isSubMerchant(Response $chain): bool
    {
        return $chain->get('key') !== null;
    }

    /**
     * Get the public key for the chain.
     */
    public function getPublicKey(Response $chain): ?string
    {
        return $chain->get('key');
    }

    /**
     * Get the creation date.
     */
    public function getCreatedAt(Response $chain): ?string
    {
        return $chain->get('created_at');
    }
}
