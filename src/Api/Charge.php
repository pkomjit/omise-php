<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Charge API for creating and managing payment charges.
 *
 * @see https://docs.omise.co/api-charges
 */
class Charge extends ApiResource
{
    protected string $endpoint = 'charges';

    /**
     * Charge status constants.
     */
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_SUCCESSFUL = 'successful';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_EXPIRED = 'expired';
    public const string STATUS_REVERSED = 'reversed';

    /**
     * Create a new charge.
     *
     * @param  array $params  Charge parameters:
     *   - amount (required): Amount in the smallest currency unit (e.g., satang for THB)
     *   - currency (required): 3-letter ISO currency code (e.g., 'THB')
     *   - source (optional): Source ID or inline source parameters
     *   - card (optional): Card token for card payments
     *   - customer (optional): Customer ID for saved cards
     *   - description (optional): Charge description
     *   - return_uri (optional): URL to redirect after payment
     *   - webhook_endpoints (optional): Array of webhook URLs
     *   - metadata (optional): Additional metadata
     *   - expires_at (optional): Expiration time for pending charges
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
     * Create a charge with an inline source (combined source+charge creation).
     *
     * @param  string $sourceType  The source type (e.g., 'promptpay', 'truemoney')
     * @param  int $amount  Amount in the smallest currency unit
     * @param  string $currency  3-letter ISO currency code
     * @param  array $additionalParams  Additional parameters for the charge
     * @throws ApiException
     */
    public function createWithSource(
        string $sourceType,
        int $amount,
        string $currency,
        array $additionalParams = []
    ): Response {
        $params = array_merge($additionalParams, [
            'amount' => $amount,
            'currency' => $currency,
            'source' => ['type' => $sourceType],
        ]);

        return $this->create($params);
    }

    /**
     * Retrieve a charge by ID.
     */
    public function retrieve(string $chargeId): Response
    {
        return parent::retrieve($chargeId);
    }

    /**
     * List all charges with optional filters.
     *
     * @param array $params Filter parameters:
     *   - offset: Starting offset
     *   - limit: Number of records to return (max 100)
     *   - from: Start date (ISO 8601)
     *   - to: End date (ISO 8601)
     *   - order: Sort order ('chronological' or 'reverse_chronological')
     */
    public function all(array $params = []): Response
    {
        return parent::all($this->normalizePaginationParams($params));
    }

    /**
     * Update a charge.
     *
     * @param  string $chargeId  Charge ID
     * @param  array $params  Parameters to update:
     *   - description (optional): New description
     *   - metadata (optional): New metadata
     * @throws ApiException
     */
    public function update(string $chargeId, array $params): Response
    {
        $data = $this->client->patch(
            $this->buildEndpoint($chargeId),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Capture an authorized charge (for card payments with capture=false).
     *
     * @param  string $chargeId  Charge ID
     * @param  int|null $amount  Amount to capture (optional, captures full amount if not specified)
     * @throws ApiException
     */
    public function capture(string $chargeId, ?int $amount = null): Response
    {
        $params = [];
        if ($amount !== null) {
            $params['amount'] = $amount;
        }

        $data = $this->client->post(
            $this->buildEndpoint("{$chargeId}/capture"),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Reverse an uncaptured charge.
     *
     * @param  string $chargeId  Charge ID
     * @throws ApiException
     */
    public function reverse(string $chargeId): Response
    {
        $data = $this->client->post(
            $this->buildEndpoint("{$chargeId}/reverse"),
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Expire a pending charge.
     *
     * @param  string $chargeId  Charge ID
     * @throws ApiException
     */
    public function expire(string $chargeId): Response
    {
        $data = $this->client->post(
            $this->buildEndpoint("{$chargeId}/expire"),
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Get the schedule associated with a charge.
     *
     * @param  string $chargeId  Charge ID
     * @throws ApiException
     */
    public function getSchedule(string $chargeId): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint("{$chargeId}/schedules"),
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Check if a charge is successful.
     */
    public function isSuccessful(Response $charge): bool
    {
        return $charge->get('status') === self::STATUS_SUCCESSFUL;
    }

    /**
     * Check if a charge is pending.
     */
    public function isPending(Response $charge): bool
    {
        return $charge->get('status') === self::STATUS_PENDING;
    }

    /**
     * Check if a charge has failed.
     */
    public function isFailed(Response $charge): bool
    {
        return $charge->get('status') === self::STATUS_FAILED;
    }

    /**
     * Get the QR code URL for PromptPay charges.
     */
    public function getQrCodeUrl(Response $charge): ?string
    {
        $source = $charge->get('source');

        if (! is_array($source)) {
            return null;
        }

        return $source['scannable_code']['image']['download_uri'] ?? null;
    }

    /**
     * Validate required parameters for charge creation.
     *
     * @throws \InvalidArgumentException
     */
    private function validateCreateParams(array $params): void
    {
        if (empty($params['amount'])) {
            throw new \InvalidArgumentException('amount is required');
        }

        if (empty($params['currency'])) {
            throw new \InvalidArgumentException('currency is required');
        }

        // At least one of source, card, or customer is required
        if (empty($params['source']) && empty($params['card']) && empty($params['customer'])) {
            throw new \InvalidArgumentException('One of source, card, or customer is required');
        }
    }
}
