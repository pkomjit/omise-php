<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Refund API for creating and managing refunds.
 *
 * @see https://docs.omise.co/refunds-api
 */
class Refund extends ApiResource
{
    protected string $endpoint = 'refunds';

    /**
     * Refund status constants.
     */
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_CLOSED = 'closed';
    public const string STATUS_REVERSED = 'reversed';

    /**
     * Create a refund for a charge.
     *
     * @param  string $chargeId  Charge ID to refund
     * @param  array $params  Refund parameters:
     *   - amount (optional): Amount to refund in the smallest unit (full refund if not specified)
     *   - metadata (optional): Additional metadata
     *   - void (optional): Whether to void instead of refund (same-day only)
     * @throws ApiException
     */
    public function create(string $chargeId, array $params = []): Response
    {
        $data = $this->client->post(
            "/charges/{$chargeId}/refunds",
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Create a full refund for a charge.
     *
     * @param  string $chargeId  Charge ID to refund
     * @throws ApiException
     */
    public function createFull(string $chargeId): Response
    {
        return $this->create($chargeId, []);
    }

    /**
     * Create a partial refund for a charge.
     *
     * @param  string $chargeId  Charge ID to refund
     * @param  int $amount  Amount to refund in the smallest unit
     * @throws ApiException
     */
    public function createPartial(string $chargeId, int $amount): Response
    {
        return $this->create($chargeId, ['amount' => $amount]);
    }

    /**
     * Void a charge (same-day refund).
     *
     * @param  string $chargeId  Charge ID to void
     * @throws ApiException
     */
    public function void(string $chargeId): Response
    {
        return $this->create($chargeId, ['void' => true]);
    }

    /**
     * Retrieve a refund for a charge.
     *
     * @param  string $chargeId  Charge ID
     * @param  string $refundId  Refund ID
     * @throws ApiException
     */
    public function retrieveForCharge(string $chargeId, string $refundId): Response
    {
        $data = $this->client->get(
            "/charges/{$chargeId}/refunds/{$refundId}",
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Retrieve a refund by ID (uses the parent all() endpoint).
     *
     * @param  string $id  Refund ID
     * @throws ApiException
     */
    public function retrieve(string $id): Response
    {
        return parent::retrieve($id);
    }

    /**
     * List all refunds for a charge.
     *
     * @param  string $chargeId  Charge ID
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function listForCharge(string $chargeId, array $params = []): Response
    {
        $data = $this->client->get(
            "/charges/{$chargeId}/refunds",
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * List all refunds across all charges.
     *
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function all(array $params = []): Response
    {
        return parent::all($this->normalizePaginationParams($params));
    }

    /**
     * Check if the refund is pending.
     */
    public function isPending(Response $refund): bool
    {
        return $refund->get('status') === self::STATUS_PENDING;
    }

    /**
     * Check if the refund is closed (completed).
     */
    public function isClosed(Response $refund): bool
    {
        return $refund->get('status') === self::STATUS_CLOSED;
    }

    /**
     * Check if the refund was reversed.
     */
    public function isReversed(Response $refund): bool
    {
        return $refund->get('status') === self::STATUS_REVERSED;
    }

    /**
     * Check if the refund is a void.
     */
    public function isVoided(Response $refund): bool
    {
        return (bool) $refund->get('voided', false);
    }

    /**
     * Get the refunded amount in the smallest unit.
     */
    public function getAmount(Response $refund): int
    {
        return (int) $refund->get('amount', 0);
    }

    /**
     * Get the refund currency.
     */
    public function getCurrency(Response $refund): string
    {
        return $refund->get('currency', 'THB');
    }

    /**
     * Get the charge ID associated with this refund.
     */
    public function getChargeId(Response $refund): ?string
    {
        return $refund->get('charge');
    }

    /**
     * Get the transaction ID associated with this refund.
     */
    public function getTransactionId(Response $refund): ?string
    {
        return $refund->get('transaction');
    }
}
