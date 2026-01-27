<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Customer API for managing customer accounts and their saved cards.
 *
 * Customers allow you to save card information for recurring charges
 * without requiring customers to re-enter payment details.
 *
 * @see https://docs.omise.co/customers-api
 */
class Customer extends ApiResource
{
    protected string $endpoint = 'customers';

    /**
     * Create a new customer.
     *
     * @param array $params Customer parameters:
     *   - email (recommended): Customer email for fraud analysis
     *   - description (recommended): Customer description/name
     *   - card (optional): Token ID to create a card
     *   - metadata (optional): Custom metadata (max 15,000 characters)
     *
     * @throws ApiException
     */
    public function create(array $params = []): Response
    {
        $data = $this->client->post(
            $this->buildEndpoint(),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Create a customer with a card token.
     *
     * @param string $email Customer email
     * @param string $tokenId Card token ID
     * @param string|null $description Optional description
     * @throws ApiException
     */
    public function createWithCard(string $email, string $tokenId, ?string $description = null): Response
    {
        $params = [
            'email' => $email,
            'card' => $tokenId,
        ];

        if ($description !== null) {
            $params['description'] = $description;
        }

        return $this->create($params);
    }

    /**
     * Retrieve a customer by ID.
     *
     * @throws ApiException
     */
    public function retrieve(string $id): Response
    {
        return parent::retrieve($id);
    }

    /**
     * List all customers with optional filters.
     *
     * @param array $params Filter parameters:
     *   - offset: Starting offset
     *   - limit: Number of records (max 100)
     *   - from: Start date (ISO 8601)
     *   - to: End date (ISO 8601)
     *   - order: Sort order ('chronological' or 'reverse_chronological')
     * @throws ApiException
     */
    public function all(array $params = []): Response
    {
        return parent::all($this->normalizePaginationParams($params));
    }

    /**
     * Update a customer.
     *
     * @param string $customerId Customer ID
     * @param array $params Parameters to update:
     *   - email: New email
     *   - description: New description
     *   - card: Token ID to add a new card
     *   - default_card: Card ID to set as default
     *   - metadata: New metadata
     * @throws ApiException
     */
    public function update(string $customerId, array $params): Response
    {
        $data = $this->client->patch(
            $this->buildEndpoint($customerId),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Delete a customer.
     *
     * @throws ApiException
     */
    public function destroy(string $customerId): Response
    {
        $data = $this->client->delete(
            $this->buildEndpoint($customerId),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Add a card to a customer using a token.
     *
     * @param string $customerId Customer ID
     * @param string $tokenId Card token ID
     * @throws ApiException
     */
    public function addCard(string $customerId, string $tokenId): Response
    {
        return $this->update($customerId, ['card' => $tokenId]);
    }

    /**
     * Set a card as the default card for a customer.
     *
     * @param string $customerId Customer ID
     * @param string $cardId Card ID (not token ID)
     * @throws ApiException
     */
    public function setDefaultCard(string $customerId, string $cardId): Response
    {
        return $this->update($customerId, ['default_card' => $cardId]);
    }

    /**
     * List all cards for a customer.
     *
     * @param string $customerId Customer ID
     * @param array $params Pagination parameters
     * @throws ApiException
     */
    public function listCards(string $customerId, array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint("{$customerId}/cards"),
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Retrieve a specific card for a customer.
     *
     * @param string $customerId Customer ID
     * @param string $cardId Card ID
     * @throws ApiException
     */
    public function retrieveCard(string $customerId, string $cardId): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint("{$customerId}/cards/{$cardId}"),
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Update a card for a customer.
     *
     * @param string $customerId Customer ID
     * @param string $cardId Card ID
     * @param array $params Parameters to update:
     *   - name: Cardholder name
     *   - expiration_month: New expiration month
     *   - expiration_year: New expiration year
     *   - postal_code: New postal code
     *   - city: New city
     * @throws ApiException
     */
    public function updateCard(string $customerId, string $cardId, array $params): Response
    {
        $data = $this->client->patch(
            $this->buildEndpoint("{$customerId}/cards/{$cardId}"),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Delete a card from a customer.
     *
     * @param string $customerId Customer ID
     * @param string $cardId Card ID
     * @throws ApiException
     */
    public function destroyCard(string $customerId, string $cardId): Response
    {
        $data = $this->client->delete(
            $this->buildEndpoint("{$customerId}/cards/{$cardId}"),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Get the default card ID from a customer response.
     */
    public function getDefaultCardId(Response $customer): ?string
    {
        return $customer->get('default_card');
    }

    /**
     * Get the email from a customer response.
     */
    public function getEmail(Response $customer): ?string
    {
        return $customer->get('email');
    }

    /**
     * Get all cards from a customer response.
     */
    public function getCards(Response $customer): array
    {
        $cards = $customer->get('cards');

        if (! is_array($cards) || ! isset($cards['data'])) {
            return [];
        }

        return $cards['data'];
    }

    /**
     * Get the total number of cards for a customer.
     */
    public function getCardCount(Response $customer): int
    {
        $cards = $customer->get('cards');

        if (! is_array($cards)) {
            return 0;
        }

        return $cards['total'] ?? 0;
    }

    /**
     * Check if a customer has any cards.
     */
    public function hasCards(Response $customer): bool
    {
        return $this->getCardCount($customer) > 0;
    }

    /**
     * List charges for a customer.
     *
     * @param string $customerId Customer ID
     * @param array $params Pagination parameters
     * @throws ApiException
     */
    public function listCharges(string $customerId, array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint("{$customerId}/charges"),
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }
}
