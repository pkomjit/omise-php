<?php

declare(strict_types=1);

namespace Omise\Api;

use InvalidArgumentException;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Link API for creating and managing payment links.
 *
 * Payment links allow you to create shareable URLs for collecting payments
 * without needing to build a checkout page.
 *
 * @see https://docs.omise.co/links-api
 */
class Link extends ApiResource
{
    protected string $endpoint = 'links';

    /**
     * Create a new payment link.
     *
     * @param  array $params  Link parameters:
     *   - amount (required): Amount in the smallest unit
     *   - currency (required): Currency code (e.g., 'THB')
     *   - title (required): Link title/description
     *   - description (optional): Detailed description
     *   - multiple (optional): Allow multiple payments (default: false)
     *   - metadata (optional): Additional metadata
     * @throws ApiException
     */
    public function create(array $params): Response
    {
        $this->validateCreateParams($params);

        $data = $this->client->post(
            $this->buildEndpoint(),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Retrieve a link by ID.
     *
     * @param  string $id  Link ID
     * @throws ApiException
     */
    public function retrieve(string $id): Response
    {
        return parent::retrieve($id);
    }

    /**
     * List all links with optional filters.
     *
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function all(array $params = []): Response
    {
        return parent::all($this->normalizePaginationParams($params));
    }

    /**
     * Delete a link.
     *
     * @param  string $linkId  Link ID
     * @throws ApiException
     */
    public function destroy(string $linkId): Response
    {
        $data = $this->client->delete(
            $this->buildEndpoint($linkId),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Search links.
     *
     * @param  array $params  Search parameters:
     *   - query: Search query
     *   - filters: Search filters (amount, currency, multiple, used, etc.)
     * @throws ApiException
     */
    public function search(array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint('search'),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * List charges for a link.
     *
     * @param  string $linkId  Link ID
     * @param  array $params  List parameters
     * @throws ApiException
     */
    public function charges(string $linkId, array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint("{$linkId}/charges"),
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Get the payment URL.
     */
    public function getPaymentUrl(Response $link): ?string
    {
        return $link->get('payment_uri');
    }

    /**
     * Check if the link has been used.
     */
    public function isUsed(Response $link): bool
    {
        return (bool) $link->get('used', false);
    }

    /**
     * Check if the link allows multiple payments.
     */
    public function isMultiple(Response $link): bool
    {
        return (bool) $link->get('multiple', false);
    }

    /**
     * Get the link amount in the smallest unit.
     */
    public function getAmount(Response $link): int
    {
        return (int) $link->get('amount', 0);
    }

    /**
     * Get the link currency.
     */
    public function getCurrency(Response $link): string
    {
        return $link->get('currency', 'THB');
    }

    /**
     * Get the link title.
     */
    public function getTitle(Response $link): ?string
    {
        return $link->get('title');
    }

    /**
     * Get the link description.
     */
    public function getDescription(Response $link): ?string
    {
        return $link->get('description');
    }

    /**
     * Get the number of charges made through this link.
     */
    public function getChargesCount(Response $link): int
    {
        $charges = $link->get('charges');
        if (is_array($charges) && isset($charges['total'])) {
            return (int) $charges['total'];
        }
        return 0;
    }

    /**
     * Get the date when the link was used.
     */
    public function getUsedAt(Response $link): ?string
    {
        return $link->get('used_at');
    }

    /**
     * Validate required parameters for link creation.
     *
     * @throws InvalidArgumentException
     */
    private function validateCreateParams(array $params): void
    {
        if (empty($params['amount'])) {
            throw new InvalidArgumentException('amount is required');
        }

        if ($params['amount'] <= 0) {
            throw new InvalidArgumentException('amount must be greater than 0');
        }

        if (empty($params['currency'])) {
            throw new InvalidArgumentException('currency is required');
        }

        if (empty($params['title'])) {
            throw new InvalidArgumentException('title is required');
        }
    }
}
