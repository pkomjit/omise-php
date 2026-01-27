<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Transaction API for retrieving transaction records.
 *
 * Transactions represent money movement in your Omise account,
 * such as charges, refunds, transfers, and fees.
 *
 * @see https://docs.omise.co/transactions-api
 */
class Transaction extends ApiResource
{
    protected string $endpoint = 'transactions';

    /**
     * Transaction type constants.
     */
    public const string TYPE_CREDIT = 'credit';
    public const string TYPE_DEBIT = 'debit';

    /**
     * Transaction source type constants.
     */
    public const string SOURCE_CHARGE = 'charge';
    public const string SOURCE_REFUND = 'refund';
    public const string SOURCE_TRANSFER = 'transfer';
    public const string SOURCE_DISPUTE = 'dispute';
    public const string SOURCE_ADJUSTMENT = 'adjustment';

    /**
     * Retrieve a transaction by ID.
     *
     * @param  string $id  Transaction ID
     * @throws ApiException
     */
    public function retrieve(string $id): Response
    {
        return parent::retrieve($id);
    }

    /**
     * List all transactions with optional filters.
     *
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function all(array $params = []): Response
    {
        return parent::all($this->normalizePaginationParams($params));
    }

    /**
     * Search transactions.
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
     * Get the transaction type (credit or debit).
     */
    public function getType(Response $transaction): ?string
    {
        return $transaction->get('type');
    }

    /**
     * Check if this is a credit transaction (money in).
     */
    public function isCredit(Response $transaction): bool
    {
        return $this->getType($transaction) === self::TYPE_CREDIT;
    }

    /**
     * Check if this is a debit transaction (money out).
     */
    public function isDebit(Response $transaction): bool
    {
        return $this->getType($transaction) === self::TYPE_DEBIT;
    }

    /**
     * Get the transaction amount in the smallest unit.
     */
    public function getAmount(Response $transaction): int
    {
        return (int) $transaction->get('amount', 0);
    }

    /**
     * Get the transaction currency.
     */
    public function getCurrency(Response $transaction): string
    {
        return $transaction->get('currency', 'THB');
    }

    /**
     * Get the source ID (charge, refund, transfer, etc.).
     */
    public function getSourceId(Response $transaction): ?string
    {
        return $transaction->get('source');
    }

    /**
     * Get the source type based on the source ID prefix.
     */
    public function getSourceType(Response $transaction): ?string
    {
        $source = $this->getSourceId($transaction);

        if ($source === null) {
            return null;
        }

        return match (true) {
            str_starts_with($source, 'chrg_') => self::SOURCE_CHARGE,
            str_starts_with($source, 'rfnd_') => self::SOURCE_REFUND,
            str_starts_with($source, 'trsf_') => self::SOURCE_TRANSFER,
            str_starts_with($source, 'dspt_') => self::SOURCE_DISPUTE,
            default => null,
        };
    }

    /**
     * Check if the transaction is from a charge.
     */
    public function isFromCharge(Response $transaction): bool
    {
        return $this->getSourceType($transaction) === self::SOURCE_CHARGE;
    }

    /**
     * Check if the transaction is from a refund.
     */
    public function isFromRefund(Response $transaction): bool
    {
        return $this->getSourceType($transaction) === self::SOURCE_REFUND;
    }

    /**
     * Check if the transaction is from a transfer.
     */
    public function isFromTransfer(Response $transaction): bool
    {
        return $this->getSourceType($transaction) === self::SOURCE_TRANSFER;
    }

    /**
     * Check if the transaction is from a dispute.
     */
    public function isFromDispute(Response $transaction): bool
    {
        return $this->getSourceType($transaction) === self::SOURCE_DISPUTE;
    }

    /**
     * Get the transferable amount.
     */
    public function getTransferable(Response $transaction): int
    {
        return (int) $transaction->get('transferable', 0);
    }

    /**
     * Get the creation date.
     */
    public function getCreatedAt(Response $transaction): ?string
    {
        return $transaction->get('created_at');
    }
}
