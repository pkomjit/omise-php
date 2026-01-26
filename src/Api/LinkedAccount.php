<?php

declare(strict_types=1);

namespace Omise\Api;

use InvalidArgumentException;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * LinkedAccount API for managing linked bank accounts for Direct Debit.
 *
 * Linked accounts allow customers to link their bank accounts for recurring
 * Direct Debit payments without re-authentication.
 *
 * @see https://docs.omise.co/direct-debit
 */
class LinkedAccount extends ApiResource
{
    protected string $endpoint = 'linked_accounts';

    /**
     * Linked account status constants.
     */
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_SUCCESSFUL = 'successful';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_DELETED = 'deleted';

    /**
     * Direct Debit bank type constants.
     */
    public const string TYPE_BAY = 'direct_debit_bay';    // Bank of Ayudhya
    public const string TYPE_KBANK = 'direct_debit_kbank'; // Kasikorn Bank
    public const string TYPE_KTB = 'direct_debit_ktb';     // Krungthai Bank
    public const string TYPE_SCB = 'direct_debit_scb';     // Siam Commercial Bank

    /**
     * Bank display names.
     */
    private const array BANK_NAMES = [
        self::TYPE_BAY => 'Bank of Ayudhya (Krungsri)',
        self::TYPE_KBANK => 'Kasikorn Bank',
        self::TYPE_KTB => 'Krungthai Bank',
        self::TYPE_SCB => 'Siam Commercial Bank',
    ];

    /**
     * Create a new linked account.
     *
     * @param array $params Linked account parameters:
     *   - type (required): Bank type (direct_debit_bay, direct_debit_kbank, etc.)
     *   - return_uri (required): URL to redirect after bank registration
     *   - citizen_id (optional): Customer's citizen ID for pre-validation
     *   - metadata (optional): Custom metadata
     *
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
     * Create a linked account with simplified parameters.
     *
     * @param string $bankType Bank type constant (e.g., TYPE_KBANK)
     * @param string $returnUri URL to redirect after registration
     * @param string|null $citizenId Optional citizen ID for pre-validation
     * @throws ApiException
     */
    public function createForBank(
        string $bankType,
        string $returnUri,
        ?string $citizenId = null
    ): Response {
        $params = [
            'type' => $bankType,
            'return_uri' => $returnUri,
        ];

        if ($citizenId !== null) {
            $params['citizen_id'] = $citizenId;
        }

        return $this->create($params);
    }

    /**
     * Retrieve a linked account by ID.
     *
     * @throws ApiException
     */
    public function retrieve(string $linkedAccountId): Response
    {
        return parent::retrieve($linkedAccountId);
    }

    /**
     * List all linked accounts with optional filters.
     *
     * @param array $params Filter parameters:
     *   - offset: Starting offset
     *   - limit: Number of records (max 100)
     *   - from: Start date (ISO 8601)
     *   - to: End date (ISO 8601)
     *   - order: Sort order
     * @throws ApiException
     */
    public function all(array $params = []): Response
    {
        return parent::all($this->normalizePaginationParams($params));
    }

    /**
     * Delete a linked account.
     *
     * Note: The account becomes unusable for future charges.
     * For Krungthai Bank, customers must also contact their bank separately.
     *
     * @throws ApiException
     */
    public function destroy(string $linkedAccountId): Response
    {
        $data = $this->client->delete(
            $this->buildEndpoint($linkedAccountId),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Get the registration URI from a linked account response.
     *
     * This is the URL to redirect the customer to for bank authentication.
     */
    public function getRegistrationUri(Response $linkedAccount): ?string
    {
        return $linkedAccount->get('registration_uri');
    }

    /**
     * Get the bank type from a linked account response.
     */
    public function getType(Response $linkedAccount): ?string
    {
        return $linkedAccount->get('type');
    }

    /**
     * Get the status from a linked account response.
     */
    public function getStatus(Response $linkedAccount): ?string
    {
        return $linkedAccount->get('status');
    }

    /**
     * Check if the linked account registration is pending.
     */
    public function isPending(Response $linkedAccount): bool
    {
        return $this->getStatus($linkedAccount) === self::STATUS_PENDING;
    }

    /**
     * Check if the linked account registration is successful.
     */
    public function isSuccessful(Response $linkedAccount): bool
    {
        return $this->getStatus($linkedAccount) === self::STATUS_SUCCESSFUL;
    }

    /**
     * Check if the linked account registration has failed.
     */
    public function isFailed(Response $linkedAccount): bool
    {
        return $this->getStatus($linkedAccount) === self::STATUS_FAILED;
    }

    /**
     * Check if the linked account has been deleted.
     */
    public function isDeleted(Response $linkedAccount): bool
    {
        return $this->getStatus($linkedAccount) === self::STATUS_DELETED;
    }

    /**
     * Get the bank name for a type.
     */
    public function getBankName(string $type): string
    {
        return self::BANK_NAMES[$type] ?? $type;
    }

    /**
     * Get all supported bank types.
     *
     * @return string[]
     */
    public function getSupportedBanks(): array
    {
        return [
            self::TYPE_BAY,
            self::TYPE_KBANK,
            self::TYPE_KTB,
            self::TYPE_SCB,
        ];
    }

    /**
     * Check if a bank type is supported.
     */
    public function isSupportedBank(string $type): bool
    {
        return in_array($type, $this->getSupportedBanks(), true);
    }

    /**
     * Validate required parameters for linked account creation.
     *
     * @throws InvalidArgumentException
     */
    private function validateCreateParams(array $params): void
    {
        if (empty($params['type'])) {
            throw new InvalidArgumentException('type is required');
        }

        if (! $this->isSupportedBank($params['type'])) {
            throw new InvalidArgumentException(
                "Invalid bank type: {$params['type']}. Supported: " . implode(', ', $this->getSupportedBanks())
            );
        }

        if (empty($params['return_uri'])) {
            throw new InvalidArgumentException('return_uri is required');
        }
    }
}
