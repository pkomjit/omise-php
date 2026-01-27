<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Search API for searching across multiple resources.
 *
 * The Search API provides a unified way to search across charges, customers,
 * disputes, links, recipients, refunds, and transfers.
 *
 * @see https://docs.omise.co/search-api
 */
class Search extends ApiResource
{
    protected string $endpoint = 'search';

    /**
     * Search scope constants.
     */
    public const string SCOPE_CHARGE = 'charge';
    public const string SCOPE_CUSTOMER = 'customer';
    public const string SCOPE_DISPUTE = 'dispute';
    public const string SCOPE_LINK = 'link';
    public const string SCOPE_RECIPIENT = 'recipient';
    public const string SCOPE_REFUND = 'refund';
    public const string SCOPE_TRANSFER = 'transfer';

    /**
     * Perform a search.
     *
     * @param  string $scope  The type of resource to search (charge, customer, etc.)
     * @param  array $params  Search parameters:
     *   - query: Search query string
     *   - page: Page number (default: 1)
     *   - per_page: Results per page (default: 30)
     *   - order: 'chronological' or 'reverse_chronological'
     *   - filters: Search filters (varies by scope)
     * @throws ApiException
     */
    public function search(string $scope, array $params = []): Response
    {
        $params['scope'] = $scope;

        $data = $this->client->get(
            $this->buildEndpoint(),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Search charges.
     *
     * @param  array $params  Search parameters
     * @throws ApiException
     */
    public function charges(array $params = []): Response
    {
        return $this->search(self::SCOPE_CHARGE, $params);
    }

    /**
     * Search customers.
     *
     * @param  array $params  Search parameters
     * @throws ApiException
     */
    public function customers(array $params = []): Response
    {
        return $this->search(self::SCOPE_CUSTOMER, $params);
    }

    /**
     * Search disputes.
     *
     * @param  array $params  Search parameters
     * @throws ApiException
     */
    public function disputes(array $params = []): Response
    {
        return $this->search(self::SCOPE_DISPUTE, $params);
    }

    /**
     * Search links.
     *
     * @param  array $params  Search parameters
     * @throws ApiException
     */
    public function links(array $params = []): Response
    {
        return $this->search(self::SCOPE_LINK, $params);
    }

    /**
     * Search recipients.
     *
     * @param  array $params  Search parameters
     * @throws ApiException
     */
    public function recipients(array $params = []): Response
    {
        return $this->search(self::SCOPE_RECIPIENT, $params);
    }

    /**
     * Search refunds.
     *
     * @param  array $params  Search parameters
     * @throws ApiException
     */
    public function refunds(array $params = []): Response
    {
        return $this->search(self::SCOPE_REFUND, $params);
    }

    /**
     * Search transfers.
     *
     * @param  array $params  Search parameters
     * @throws ApiException
     */
    public function transfers(array $params = []): Response
    {
        return $this->search(self::SCOPE_TRANSFER, $params);
    }

    /**
     * Get the total count of results.
     */
    public function getTotal(Response $results): int
    {
        return (int) $results->get('total', 0);
    }

    /**
     * Get the current page number.
     */
    public function getPage(Response $results): int
    {
        return (int) $results->get('page', 1);
    }

    /**
     * Get the results per page.
     */
    public function getPerPage(Response $results): int
    {
        return (int) $results->get('per_page', 30);
    }

    /**
     * Get the total number of pages.
     */
    public function getTotalPages(Response $results): int
    {
        return (int) $results->get('total_pages', 1);
    }

    /**
     * Check if there are more pages.
     */
    public function hasMorePages(Response $results): bool
    {
        return $this->getPage($results) < $this->getTotalPages($results);
    }

    /**
     * Get the search results data.
     */
    public function getData(Response $results): array
    {
        return $results->get('data', []);
    }

    /**
     * Get the search query.
     */
    public function getQuery(Response $results): ?string
    {
        return $results->get('query');
    }

    /**
     * Get the search scope.
     */
    public function getScope(Response $results): ?string
    {
        return $results->get('scope');
    }

    /**
     * Get the applied filters.
     */
    public function getFilters(Response $results): array
    {
        return $results->get('filters', []);
    }
}
