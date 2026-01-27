<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Account API for retrieving and updating account information.
 *
 * @see https://docs.omise.co/account-api
 */
class Account extends ApiResource
{
    protected string $endpoint = 'account';

    /**
     * Retrieve the account information.
     *
     * @throws ApiException
     */
    public function get(): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint(),
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Update the account settings.
     *
     * @param  array $params  Account parameters:
     *   - chain_enabled: Enable/disable chain
     *   - zero_interest_installments: Enable/disable zero interest installments
     *   - chain_return_uri: URI for chain returns
     *   - webhook_uri: Webhook URI
     *   - metadata_export_keys: Keys to export in metadata
     * @throws ApiException
     */
    public function update(array $params): Response
    {
        $data = $this->client->patch(
            $this->buildEndpoint(),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Get available API versions.
     *
     * @throws ApiException
     */
    public function getApiVersions(): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint('api_versions'),
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Update the API version.
     *
     * @param  string $version  API version to use
     * @throws ApiException
     */
    public function updateApiVersion(string $version): Response
    {
        $data = $this->client->patch(
            $this->buildEndpoint('api_version'),
            ['api_version' => $version],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Check if chain is enabled.
     */
    public function isChainEnabled(Response $account): bool
    {
        return (bool) $account->get('chain_enabled', false);
    }

    /**
     * Check if zero interest installments are enabled.
     */
    public function isZeroInterestInstallmentsEnabled(Response $account): bool
    {
        return (bool) $account->get('zero_interest_installments', false);
    }

    /**
     * Get the webhook URI.
     */
    public function getWebhookUri(Response $account): ?string
    {
        return $account->get('webhook_uri');
    }

    /**
     * Get the account email.
     */
    public function getEmail(Response $account): ?string
    {
        return $account->get('email');
    }

    /**
     * Get the account currency.
     */
    public function getCurrency(Response $account): ?string
    {
        return $account->get('currency');
    }

    /**
     * Get the account team.
     */
    public function getTeam(Response $account): ?string
    {
        return $account->get('team');
    }
}
