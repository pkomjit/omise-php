<?php

declare(strict_types=1);

namespace Omise\Api;

use InvalidArgumentException;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Transfer API for creating and managing transfers to bank accounts.
 *
 * @see https://docs.omise.co/transfers-api
 */
class Transfer extends ApiResource
{
    protected string $endpoint = 'transfers';

    /**
     * Transfer status constants.
     */
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_SENT = 'sent';
    public const string STATUS_PAID = 'paid';
    public const string STATUS_FAILED = 'failed';

    /**
     * Transfer failure codes.
     */
    public const string FAILURE_INSUFFICIENT_BALANCE = 'insufficient_balance';
    public const string FAILURE_INVALID_ACCOUNT = 'invalid_account';
    public const string FAILURE_BANK_REJECTED = 'bank_rejected';
    public const string FAILURE_ACCOUNT_CLOSED = 'account_closed';

    /**
     * Create a new transfer.
     *
     * @param  array $params  Transfer parameters:
     *   - amount (required): Amount in the smallest unit
     *   - recipient (optional): Recipient ID (uses default if not specified)
     *   - fail_fast (optional): Fail immediately if transfer cannot be completed
     *   - metadata (optional): Additional metadata
     *   - send_immediately (optional): Send transfer immediately
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
     * Create a transfer to the default bank account.
     *
     * @param  int $amount  Amount in the smallest unit
     * @param  array $options  Additional options
     * @throws ApiException
     */
    public function createToDefault(int $amount, array $options = []): Response
    {
        return $this->create(array_merge($options, [
            'amount' => $amount,
        ]));
    }

    /**
     * Create a transfer to a specific recipient.
     *
     * @param  int $amount  Amount in the smallest unit
     * @param  string $recipientId  Recipient ID
     * @param  array $options  Additional options
     * @throws ApiException
     */
    public function createToRecipient(int $amount, string $recipientId, array $options = []): Response
    {
        return $this->create(array_merge($options, [
            'amount' => $amount,
            'recipient' => $recipientId,
        ]));
    }

    /**
     * Retrieve a transfer by ID.
     *
     * @param  string $id  Transfer ID
     * @throws ApiException
     */
    public function retrieve(string $id): Response
    {
        return parent::retrieve($id);
    }

    /**
     * List all transfers with optional filters.
     *
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function all(array $params = []): Response
    {
        return parent::all($this->normalizePaginationParams($params));
    }

    /**
     * Update a transfer.
     *
     * @param  string $transferId  Transfer ID
     * @param  array $params  Parameters to update:
     *   - amount: New amount
     *   - metadata: New metadata
     * @throws ApiException
     */
    public function update(string $transferId, array $params): Response
    {
        $data = $this->client->patch(
            $this->buildEndpoint($transferId),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Delete/cancel a pending transfer.
     *
     * @param  string $transferId  Transfer ID
     * @throws ApiException
     */
    public function destroy(string $transferId): Response
    {
        $data = $this->client->delete(
            $this->buildEndpoint($transferId),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Search transfers.
     *
     * @param  array $params  Search parameters:
     *   - query: Search query
     *   - filters: Search filters
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
     * List transfer schedules.
     *
     * @param  array $params  List parameters
     * @throws ApiException
     */
    public function schedules(array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint('schedules'),
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Check if the transfer is pending.
     */
    public function isPending(Response $transfer): bool
    {
        return $transfer->get('status') === self::STATUS_PENDING;
    }

    /**
     * Check if the transfer has been sent.
     */
    public function isSent(Response $transfer): bool
    {
        return $transfer->get('status') === self::STATUS_SENT;
    }

    /**
     * Check if the transfer has been paid.
     */
    public function isPaid(Response $transfer): bool
    {
        return $transfer->get('status') === self::STATUS_PAID;
    }

    /**
     * Check if the transfer has failed.
     */
    public function isFailed(Response $transfer): bool
    {
        return $transfer->get('status') === self::STATUS_FAILED;
    }

    /**
     * Get the transfer amount in the smallest unit.
     */
    public function getAmount(Response $transfer): int
    {
        return (int) $transfer->get('amount', 0);
    }

    /**
     * Get the transfer fee in the smallest unit.
     */
    public function getFee(Response $transfer): int
    {
        return (int) $transfer->get('fee', 0);
    }

    /**
     * Get the transfer currency.
     */
    public function getCurrency(Response $transfer): string
    {
        return $transfer->get('currency', 'THB');
    }

    /**
     * Get the recipient ID.
     */
    public function getRecipientId(Response $transfer): ?string
    {
        return $transfer->get('recipient');
    }

    /**
     * Get the bank account info.
     */
    public function getBankAccount(Response $transfer): ?array
    {
        return $transfer->get('bank_account');
    }

    /**
     * Get the failure code.
     */
    public function getFailureCode(Response $transfer): ?string
    {
        return $transfer->get('failure_code');
    }

    /**
     * Get the failure message.
     */
    public function getFailureMessage(Response $transfer): ?string
    {
        $code = $this->getFailureCode($transfer);

        return match ($code) {
            self::FAILURE_INSUFFICIENT_BALANCE => 'Insufficient balance for transfer',
            self::FAILURE_INVALID_ACCOUNT => 'Invalid bank account',
            self::FAILURE_BANK_REJECTED => 'Transfer rejected by bank',
            self::FAILURE_ACCOUNT_CLOSED => 'Bank account has been closed',
            default => $transfer->get('failure_message'),
        };
    }

    /**
     * Validate required parameters for transfer creation.
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
    }
}
