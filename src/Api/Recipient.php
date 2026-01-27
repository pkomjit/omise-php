<?php

declare(strict_types=1);

namespace Omise\Api;

use InvalidArgumentException;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Recipient API for managing transfer recipients.
 *
 * Recipients are bank accounts that can receive transfers from your Omise account.
 *
 * @see https://docs.omise.co/recipients-api
 */
class Recipient extends ApiResource
{
    protected string $endpoint = 'recipients';

    /**
     * Recipient type constants.
     */
    public const string TYPE_INDIVIDUAL = 'individual';
    public const string TYPE_CORPORATION = 'corporation';

    /**
     * Bank account brand constants for Thailand.
     */
    public const string BANK_BBL = 'bbl';   // Bangkok Bank
    public const string BANK_KBANK = 'kbank'; // Kasikorn Bank
    public const string BANK_KTB = 'ktb';   // Krungthai Bank
    public const string BANK_SCB = 'scb';   // Siam Commercial Bank
    public const string BANK_BAY = 'bay';   // Bank of Ayudhya (Krungsri)
    public const string BANK_TMB = 'tmb';   // TMB Bank (now ttb)
    public const string BANK_GSB = 'gsb';   // Government Savings Bank
    public const string BANK_CIMB = 'cimb'; // CIMB Thai
    public const string BANK_UOB = 'uob';   // UOB Thailand
    public const string BANK_TISCO = 'tisco'; // TISCO Bank
    public const string BANK_LHBANK = 'lhbank'; // LH Bank

    /**
     * Create a new recipient.
     *
     * @param  array $params  Recipient parameters:
     *   - name (required): Recipient name
     *   - email (optional): Recipient email
     *   - description (optional): Recipient description
     *   - type (required): 'individual' or 'corporation'
     *   - tax_id (optional): Tax ID for corporations
     *   - bank_account (required): Bank account details:
     *     - brand: Bank brand (e.g., 'kbank', 'scb')
     *     - number: Account number
     *     - name: Account holder name
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
     * Create an individual recipient.
     *
     * @param  string $name  Recipient name
     * @param  string $bankBrand  Bank brand
     * @param  string $accountNumber  Bank account number
     * @param  string $accountName  Bank account holder name
     * @param  array $options  Additional options
     * @throws ApiException
     */
    public function createIndividual(
        string $name,
        string $bankBrand,
        string $accountNumber,
        string $accountName,
        array $options = []
    ): Response {
        return $this->create(array_merge($options, [
            'name' => $name,
            'type' => self::TYPE_INDIVIDUAL,
            'bank_account' => [
                'brand' => $bankBrand,
                'number' => $accountNumber,
                'name' => $accountName,
            ],
        ]));
    }

    /**
     * Create a corporation recipient.
     *
     * @param  string $name  Company name
     * @param  string $bankBrand  Bank brand
     * @param  string $accountNumber  Bank account number
     * @param  string $accountName  Bank account holder name
     * @param  string|null $taxId  Tax ID
     * @param  array $options  Additional options
     * @throws ApiException
     */
    public function createCorporation(
        string $name,
        string $bankBrand,
        string $accountNumber,
        string $accountName,
        ?string $taxId = null,
        array $options = []
    ): Response {
        $params = array_merge($options, [
            'name' => $name,
            'type' => self::TYPE_CORPORATION,
            'bank_account' => [
                'brand' => $bankBrand,
                'number' => $accountNumber,
                'name' => $accountName,
            ],
        ]);

        if ($taxId !== null) {
            $params['tax_id'] = $taxId;
        }

        return $this->create($params);
    }

    /**
     * Retrieve a recipient by ID.
     *
     * @param  string $id  Recipient ID
     * @throws ApiException
     */
    public function retrieve(string $id): Response
    {
        return parent::retrieve($id);
    }

    /**
     * List all recipients.
     *
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function all(array $params = []): Response
    {
        return parent::all($this->normalizePaginationParams($params));
    }

    /**
     * Update a recipient.
     *
     * @param  string $recipientId  Recipient ID
     * @param  array $params  Parameters to update
     * @throws ApiException
     */
    public function update(string $recipientId, array $params): Response
    {
        $data = $this->client->patch(
            $this->buildEndpoint($recipientId),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Delete a recipient.
     *
     * @param  string $recipientId  Recipient ID
     * @throws ApiException
     */
    public function destroy(string $recipientId): Response
    {
        $data = $this->client->delete(
            $this->buildEndpoint($recipientId),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Search recipients.
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
     * List recipient's transfer schedules.
     *
     * @param  string $recipientId  Recipient ID
     * @param  array $params  List parameters
     * @throws ApiException
     */
    public function schedules(string $recipientId, array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint("{$recipientId}/schedules"),
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Check if the recipient is verified.
     */
    public function isVerified(Response $recipient): bool
    {
        return (bool) $recipient->get('verified', false);
    }

    /**
     * Check if the recipient is active.
     */
    public function isActive(Response $recipient): bool
    {
        return (bool) $recipient->get('active', false);
    }

    /**
     * Check if the recipient is an individual.
     */
    public function isIndividual(Response $recipient): bool
    {
        return $recipient->get('type') === self::TYPE_INDIVIDUAL;
    }

    /**
     * Check if the recipient is a corporation.
     */
    public function isCorporation(Response $recipient): bool
    {
        return $recipient->get('type') === self::TYPE_CORPORATION;
    }

    /**
     * Get the recipient name.
     */
    public function getName(Response $recipient): ?string
    {
        return $recipient->get('name');
    }

    /**
     * Get the recipient email.
     */
    public function getEmail(Response $recipient): ?string
    {
        return $recipient->get('email');
    }

    /**
     * Get the bank account details.
     */
    public function getBankAccount(Response $recipient): ?array
    {
        return $recipient->get('bank_account');
    }

    /**
     * Get the bank account brand.
     */
    public function getBankBrand(Response $recipient): ?string
    {
        $account = $this->getBankAccount($recipient);
        return $account['brand'] ?? null;
    }

    /**
     * Get the last 4 digits of the bank account number.
     */
    public function getBankAccountLastDigits(Response $recipient): ?string
    {
        $account = $this->getBankAccount($recipient);
        return $account['last_digits'] ?? null;
    }

    /**
     * Get the failure code.
     */
    public function getFailureCode(Response $recipient): ?string
    {
        return $recipient->get('failure_code');
    }

    /**
     * Validate required parameters for recipient creation.
     *
     * @throws InvalidArgumentException
     */
    private function validateCreateParams(array $params): void
    {
        if (empty($params['name'])) {
            throw new InvalidArgumentException('name is required');
        }

        if (empty($params['type'])) {
            throw new InvalidArgumentException('type is required');
        }

        if (!in_array($params['type'], [self::TYPE_INDIVIDUAL, self::TYPE_CORPORATION], true)) {
            throw new InvalidArgumentException('type must be "individual" or "corporation"');
        }

        if (empty($params['bank_account'])) {
            throw new InvalidArgumentException('bank_account is required');
        }

        if (empty($params['bank_account']['brand'])) {
            throw new InvalidArgumentException('bank_account.brand is required');
        }

        if (empty($params['bank_account']['number'])) {
            throw new InvalidArgumentException('bank_account.number is required');
        }

        if (empty($params['bank_account']['name'])) {
            throw new InvalidArgumentException('bank_account.name is required');
        }
    }
}
