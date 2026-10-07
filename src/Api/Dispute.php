<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Dispute API for managing payment disputes (chargebacks).
 *
 * Disputes occur when a cardholder questions a charge on their account.
 * You can respond to disputes by providing evidence or accepting them.
 *
 * @see https://docs.omise.co/disputes-api
 */
class Dispute extends ApiResource
{
    protected string $endpoint = 'disputes';

    /**
     * Dispute status constants.
     */
    public const string STATUS_OPEN = 'open';
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_WON = 'won';
    public const string STATUS_LOST = 'lost';

    /**
     * Dispute reason codes.
     */
    public const string REASON_DUPLICATE = 'duplicate';
    public const string REASON_FRAUDULENT = 'fraudulent';
    public const string REASON_SUBSCRIPTION_CANCELED = 'subscription_canceled';
    public const string REASON_PRODUCT_NOT_RECEIVED = 'product_not_received';
    public const string REASON_PRODUCT_UNACCEPTABLE = 'product_unacceptable';
    public const string REASON_UNRECOGNIZED = 'unrecognized';
    public const string REASON_CREDIT_NOT_PROCESSED = 'credit_not_processed';
    public const string REASON_GENERAL = 'general';

    /**
     * Retrieve a dispute by ID.
     *
     * @param  string $id  Dispute ID
     * @throws ApiException
     */
    public function retrieve(string $id): Response
    {
        return parent::retrieve($id);
    }

    /**
     * List all disputes with optional filters.
     *
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function all(array $params = []): Response
    {
        return parent::all($this->normalizePaginationParams($params));
    }

    /**
     * List all open disputes.
     *
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function open(array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint('open'),
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * List all pending disputes.
     *
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function pending(array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint('pending'),
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * List all closed disputes (won or lost).
     *
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function closed(array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint('closed'),
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Update a dispute with a message or metadata.
     *
     * @param  string $disputeId  Dispute ID
     * @param  array $params  Parameters to update:
     *   - message: Response message to the dispute
     *   - metadata: Additional metadata
     * @throws ApiException
     */
    public function update(string $disputeId, array $params): Response
    {
        $data = $this->client->patch(
            $this->buildEndpoint($disputeId),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Close a dispute with a resolution.
     *
     * @param  string $disputeId  Dispute ID
     * @param  string $status  Resolution status ('won' or 'lost')
     * @throws ApiException
     */
    public function close(string $disputeId, string $status): Response
    {
        if (! in_array($status, [self::STATUS_WON, self::STATUS_LOST], true)) {
            throw new \InvalidArgumentException('status must be "won" or "lost"');
        }

        $data = $this->client->patch(
            $this->buildEndpoint("{$disputeId}/close"),
            ['status' => $status],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Accept a dispute (concede the dispute to the cardholder).
     *
     * @param  string $disputeId  Dispute ID
     * @throws ApiException
     */
    public function accept(string $disputeId): Response
    {
        $data = $this->client->patch(
            $this->buildEndpoint("{$disputeId}/accept"),
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Create a dispute for a charge (for testing purposes).
     *
     * @param  string $chargeId  Charge ID
     * @throws ApiException
     */
    public function createForCharge(string $chargeId): Response
    {
        $data = $this->client->post(
            "/charges/{$chargeId}/disputes",
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Search disputes.
     *
     * @param  array $params  Search parameters
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
     * List documents for a dispute.
     *
     * @param  string $disputeId  Dispute ID
     * @param  array $params  List parameters
     * @throws ApiException
     */
    public function documents(string $disputeId, array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint("{$disputeId}/documents"),
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Check if the dispute is open.
     */
    public function isOpen(Response $dispute): bool
    {
        return $dispute->get('status') === self::STATUS_OPEN;
    }

    /**
     * Check if the dispute is pending.
     */
    public function isPending(Response $dispute): bool
    {
        return $dispute->get('status') === self::STATUS_PENDING;
    }

    /**
     * Check if the dispute is won.
     */
    public function isWon(Response $dispute): bool
    {
        return $dispute->get('status') === self::STATUS_WON;
    }

    /**
     * Check if the dispute is lost.
     */
    public function isLost(Response $dispute): bool
    {
        return $dispute->get('status') === self::STATUS_LOST;
    }

    /**
     * Check if the dispute is closed (won or lost).
     */
    public function isClosed(Response $dispute): bool
    {
        return in_array($dispute->get('status'), [self::STATUS_WON, self::STATUS_LOST], true);
    }

    /**
     * Get the dispute amount in the smallest unit.
     */
    public function getAmount(Response $dispute): int
    {
        return (int) $dispute->get('amount', 0);
    }

    /**
     * Get the dispute currency.
     */
    public function getCurrency(Response $dispute): string
    {
        return $dispute->get('currency', 'THB');
    }

    /**
     * Get the charge ID associated with this dispute.
     */
    public function getChargeId(Response $dispute): ?string
    {
        return $dispute->get('charge');
    }

    /**
     * Get the reason code for the dispute.
     */
    public function getReasonCode(Response $dispute): ?string
    {
        return $dispute->get('reason_code');
    }

    /**
     * Get the reason message for the dispute.
     */
    public function getReasonMessage(Response $dispute): ?string
    {
        return $dispute->get('reason_message');
    }

    /**
     * Get the closing deadline for the dispute.
     */
    public function getClosingDate(Response $dispute): ?string
    {
        return $dispute->get('closed_at') ?? $dispute->get('closing_date');
    }

    /**
     * Get the admin message for the dispute.
     */
    public function getAdminMessage(Response $dispute): ?string
    {
        return $dispute->get('admin_message');
    }

    /**
     * Get the merchant message (response) for the dispute.
     */
    public function getMessage(Response $dispute): ?string
    {
        return $dispute->get('message');
    }
}
