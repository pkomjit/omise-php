<?php

declare(strict_types=1);

namespace Omise\Api;

use InvalidArgumentException;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Source API for creating payment sources.
 *
 * Sources represent payment method details that can be used to create charges.
 *
 * @see https://docs.omise.co/api-sources
 */
class Source extends ApiResource
{
    protected string $endpoint = 'sources';
    protected bool $usePublicKey = true; // Sources use public key

    /**
     * Source type constants.
     */
    public const string TYPE_PROMPTPAY = 'promptpay';
    public const string TYPE_TRUEMONEY = 'truemoney';
    public const string TYPE_ALIPAY = 'alipay';
    public const string TYPE_ALIPAY_CN = 'alipay_cn';
    public const string TYPE_ALIPAY_HK = 'alipay_hk';
    public const string TYPE_GRABPAY = 'grabpay';
    public const string TYPE_SHOPEEPAY = 'shopeepay';
    public const string TYPE_RABBIT_LINEPAY = 'rabbit_linepay';
    public const string TYPE_INTERNET_BANKING_BAY = 'internet_banking_bay';
    public const string TYPE_INTERNET_BANKING_BBL = 'internet_banking_bbl';
    public const string TYPE_INTERNET_BANKING_KTB = 'internet_banking_ktb';
    public const string TYPE_INTERNET_BANKING_SCB = 'internet_banking_scb';
    public const string TYPE_MOBILE_BANKING_BAY = 'mobile_banking_bay';
    public const string TYPE_MOBILE_BANKING_BBL = 'mobile_banking_bbl';
    public const string TYPE_MOBILE_BANKING_KTB = 'mobile_banking_ktb';
    public const string TYPE_MOBILE_BANKING_SCB = 'mobile_banking_scb';
    public const string TYPE_MOBILE_BANKING_KBANK = 'mobile_banking_kbank';

    /**
     * Source flow constants.
     */
    public const string FLOW_REDIRECT = 'redirect';
    public const string FLOW_OFFLINE = 'offline';
    public const string FLOW_APP_REDIRECT = 'app_redirect';

    /**
     * Create a new source.
     *
     * @param  array $params  Source parameters:
     *   - type (required): Source type (e.g., 'promptpay', 'truemoney')
     *   - amount (required): Amount in the smallest currency unit
     *   - currency (required): 3-letter ISO currency code
     *   - phone_number (optional): Required for truemoney
     *   - email (optional): Customer email
     *   - name (optional): Customer name
     *   - installment_term (optional): Installment term for installment payments
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
     * Create a PromptPay source.
     *
     * @param  int $amount  Amount in satang (smallest unit)
     * @param  string $currency  Currency code (usually 'THB')
     * @throws ApiException
     */
    public function createPromptPay(int $amount, string $currency = 'THB'): Response
    {
        return $this->create([
            'type' => self::TYPE_PROMPTPAY,
            'amount' => $amount,
            'currency' => $currency,
        ]);
    }

    /**
     * Create a TrueMoney source.
     *
     * @param  int $amount  Amount in satang
     * @param  string $phoneNumber  Customer's TrueMoney phone number
     * @param  string $currency  Currency code (usually 'THB')
     * @throws ApiException
     */
    public function createTrueMoney(int $amount, string $phoneNumber, string $currency = 'THB'): Response
    {
        return $this->create([
            'type' => self::TYPE_TRUEMONEY,
            'amount' => $amount,
            'currency' => $currency,
            'phone_number' => $phoneNumber,
        ]);
    }

    /**
     * Create an internet banking source.
     *
     * @param  string $bank  Bank identifier (bay, bbl, ktb, scb)
     * @param  int $amount  Amount in satang
     * @param  string $currency  Currency code
     * @throws ApiException
     */
    public function createInternetBanking(string $bank, int $amount, string $currency = 'THB'): Response
    {
        $type = "internet_banking_{$bank}";

        return $this->create([
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
        ]);
    }

    /**
     * Create a mobile banking source.
     *
     * @param  string $bank  Bank identifier (bay, bbl, ktb, scb, kbank)
     * @param  int $amount  Amount in satang
     * @param  string $currency  Currency code
     * @throws ApiException
     */
    public function createMobileBanking(string $bank, int $amount, string $currency = 'THB'): Response
    {
        $type = "mobile_banking_{$bank}";

        return $this->create([
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
        ]);
    }

    /**
     * Retrieve a source by ID.
     * @throws ApiException
     */
    public function retrieve(string $sourceId): Response
    {
        return parent::retrieve($sourceId);
    }

    /**
     * Get the flow type for a source type.
     */
    public function getFlowForType(string $sourceType): string
    {
        $offlineTypes = [
            self::TYPE_PROMPTPAY,
        ];

        $appRedirectTypes = [
            self::TYPE_TRUEMONEY,
            self::TYPE_SHOPEEPAY,
            self::TYPE_GRABPAY,
            self::TYPE_RABBIT_LINEPAY,
            self::TYPE_MOBILE_BANKING_BAY,
            self::TYPE_MOBILE_BANKING_BBL,
            self::TYPE_MOBILE_BANKING_KTB,
            self::TYPE_MOBILE_BANKING_SCB,
            self::TYPE_MOBILE_BANKING_KBANK,
        ];

        if (in_array($sourceType, $offlineTypes, true)) {
            return self::FLOW_OFFLINE;
        }

        if (in_array($sourceType, $appRedirectTypes, true)) {
            return self::FLOW_APP_REDIRECT;
        }

        return self::FLOW_REDIRECT;
    }

    /**
     * Check if a source type requires a phone number.
     */
    public function requiresPhoneNumber(string $sourceType): bool
    {
        return $sourceType === self::TYPE_TRUEMONEY;
    }

    /**
     * Get available source types for a currency.
     */
    public function getAvailableTypesForCurrency(string $currency): array
    {
        $thbTypes = [
            self::TYPE_PROMPTPAY,
            self::TYPE_TRUEMONEY,
            self::TYPE_SHOPEEPAY,
            self::TYPE_GRABPAY,
            self::TYPE_RABBIT_LINEPAY,
            self::TYPE_INTERNET_BANKING_BAY,
            self::TYPE_INTERNET_BANKING_BBL,
            self::TYPE_INTERNET_BANKING_KTB,
            self::TYPE_INTERNET_BANKING_SCB,
            self::TYPE_MOBILE_BANKING_BAY,
            self::TYPE_MOBILE_BANKING_BBL,
            self::TYPE_MOBILE_BANKING_KTB,
            self::TYPE_MOBILE_BANKING_SCB,
            self::TYPE_MOBILE_BANKING_KBANK,
        ];

        return match (strtoupper($currency)) {
            'THB' => $thbTypes,
            default => [],
        };
    }

    /**
     * Validate required parameters for source creation.
     *
     * @throws InvalidArgumentException
     */
    private function validateCreateParams(array $params): void
    {
        if (empty($params['type'])) {
            throw new InvalidArgumentException('type is required');
        }

        if (empty($params['amount'])) {
            throw new InvalidArgumentException('amount is required');
        }

        if (empty($params['currency'])) {
            throw new InvalidArgumentException('currency is required');
        }

        // Validate phone number for truemoney
        if ($params['type'] === self::TYPE_TRUEMONEY && empty($params['phone_number'])) {
            throw new InvalidArgumentException('phone_number is required for TrueMoney');
        }
    }
}
