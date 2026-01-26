<?php

declare(strict_types=1);

namespace Omise\PaymentMethods;

use Omise\Api\Charge;
use Omise\Api\Customer;
use Omise\Api\Token;
use Omise\Currency;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Credit Card payment method implementation.
 *
 * Supports:
 * - One-time charges with card tokens
 * - Customer-based charging (saved cards)
 * - 3D Secure authentication
 * - Authorization and capture (multi-capture)
 * - Multi-currency payments
 *
 * @see https://docs.omise.co/charging-cards
 */
class CreditCard
{
    protected Charge $chargeApi;
    protected Token $tokenApi;
    protected Customer $customerApi;

    /**
     * Supported card brands.
     */
    public const string BRAND_VISA = 'Visa';
    public const string BRAND_MASTERCARD = 'MasterCard';
    public const string BRAND_JCB = 'JCB';
    public const string BRAND_AMEX = 'American Express';

    /**
     * Failure codes.
     */
    public const string FAILURE_INVALID_CARD = 'invalid_card';
    public const string FAILURE_INVALID_SECURITY_CODE = 'invalid_security_code';
    public const string FAILURE_FAILED_FRAUD_CHECK = 'failed_fraud_check';
    public const string FAILURE_FAILED_PROCESSING = 'failed_processing';
    public const string FAILURE_INSUFFICIENT_FUND = 'insufficient_fund';
    public const string FAILURE_STOLEN_OR_LOST_CARD = 'stolen_or_lost_card';
    public const string FAILURE_PAYMENT_REJECTED = 'payment_rejected';
    public const string FAILURE_INVALID_ACCOUNT_NUMBER = 'invalid_account_number';

    public function __construct(Charge $chargeApi, Token $tokenApi, Customer $customerApi)
    {
        $this->chargeApi = $chargeApi;
        $this->tokenApi = $tokenApi;
        $this->customerApi = $customerApi;
    }

    /**
     * Create a card token from card details.
     *
     * @param string $name Cardholder name
     * @param string $number Card number
     * @param int $expirationMonth Expiration month (1-12)
     * @param int $expirationYear Expiration year (YYYY or YY)
     * @param string|null $securityCode CVV/CVC code
     * @param array $billingAddress Optional billing address fields
     * @throws ApiException
     */
    public function createToken(
        string $name,
        string $number,
        int $expirationMonth,
        int $expirationYear,
        ?string $securityCode = null,
        array $billingAddress = []
    ): Response {
        $card = array_merge([
            'name' => $name,
            'number' => $number,
            'expiration_month' => $expirationMonth,
            'expiration_year' => $expirationYear,
        ], $billingAddress);

        if ($securityCode !== null) {
            $card['security_code'] = $securityCode;
        }

        return $this->tokenApi->create($card);
    }

    /**
     * Charge a card using a token (one-time payment).
     *
     * @param string $tokenId Token ID from createToken()
     * @param int $amount Amount in smallest currency unit
     * @param string $currency Currency code (e.g., 'THB', 'USD')
     * @param array $options Additional options:
     *   - capture (bool): Auto-capture (default: true)
     *   - return_uri (string): URL for 3D Secure redirect
     *   - description (string): Charge description
     *   - metadata (array): Custom metadata
     *   - webhook_endpoints (array): Webhook URLs
     * @throws ApiException
     */
    public function chargeToken(
        string $tokenId,
        int $amount,
        string $currency,
        array $options = []
    ): Response {
        $params = array_merge($options, [
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'card' => $tokenId,
        ]);

        return $this->chargeApi->create($params);
    }

    /**
     * Charge a card with a simplified interface (amount in main currency unit).
     *
     * @param string $tokenId Token ID
     * @param float $amount Amount in main unit (e.g., 100.00 THB)
     * @param string $currency Currency code
     * @param string|null $returnUri URL for 3D Secure (required for 3DS)
     * @param bool $capture Whether to auto-capture (default: true)
     * @throws ApiException
     */
    public function pay(
        string $tokenId,
        float $amount,
        string $currency = 'THB',
        ?string $returnUri = null,
        bool $capture = true
    ): Response {
        $amountInSmallestUnit = Currency::toSmallestUnit($amount, $currency);

        $options = [
            'capture' => $capture,
        ];

        if ($returnUri !== null) {
            $options['return_uri'] = $returnUri;
        }

        return $this->chargeToken($tokenId, $amountInSmallestUnit, $currency, $options);
    }

    /**
     * Charge a customer's default card.
     *
     * @param string $customerId Customer ID
     * @param int $amount Amount in smallest currency unit
     * @param string $currency Currency code
     * @param array $options Additional options
     * @throws ApiException
     */
    public function chargeCustomer(
        string $customerId,
        int $amount,
        string $currency,
        array $options = []
    ): Response {
        $params = array_merge($options, [
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'customer' => $customerId,
        ]);

        return $this->chargeApi->create($params);
    }

    /**
     * Charge a specific card for a customer.
     *
     * @param string $customerId Customer ID
     * @param string $cardId Card ID (not token ID)
     * @param int $amount Amount in smallest currency unit
     * @param string $currency Currency code
     * @param array $options Additional options
     * @throws ApiException
     */
    public function chargeCustomerCard(
        string $customerId,
        string $cardId,
        int $amount,
        string $currency,
        array $options = []
    ): Response {
        $params = array_merge($options, [
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'customer' => $customerId,
            'card' => $cardId,
        ]);

        return $this->chargeApi->create($params);
    }

    /**
     * Authorize a card without capturing (for later capture).
     *
     * @param string $tokenId Token ID
     * @param int $amount Amount in smallest currency unit
     * @param string $currency Currency code
     * @param string|null $returnUri URL for 3D Secure
     * @throws ApiException
     */
    public function authorize(
        string $tokenId,
        int $amount,
        string $currency,
        ?string $returnUri = null
    ): Response {
        $options = [
            'capture' => false,
        ];

        if ($returnUri !== null) {
            $options['return_uri'] = $returnUri;
        }

        return $this->chargeToken($tokenId, $amount, $currency, $options);
    }

    /**
     * Capture an authorized charge.
     *
     * @param string $chargeId Charge ID
     * @param int|null $amount Amount to capture (null = full amount)
     * @throws ApiException
     */
    public function capture(string $chargeId, ?int $amount = null): Response
    {
        return $this->chargeApi->capture($chargeId, $amount);
    }

    /**
     * Reverse (void) an authorized but uncaptured charge.
     *
     * @param string $chargeId Charge ID
     * @throws ApiException
     */
    public function reverse(string $chargeId): Response
    {
        return $this->chargeApi->reverse($chargeId);
    }

    /**
     * Get the authorize URI for 3D Secure authentication.
     */
    public function getAuthorizeUri(Response $charge): ?string
    {
        return $charge->get('authorize_uri');
    }

    /**
     * Check if the charge requires 3D Secure authentication.
     */
    public function requires3DSecure(Response $charge): bool
    {
        return $charge->get('status') === 'pending'
            && $this->getAuthorizeUri($charge) !== null;
    }

    /**
     * Check if the charge is authorized (but not captured).
     */
    public function isAuthorized(Response $charge): bool
    {
        return $charge->get('authorized') === true
            && $charge->get('captured') === false;
    }

    /**
     * Check if the charge can be captured.
     */
    public function isCapturable(Response $charge): bool
    {
        return $charge->get('capturable') === true;
    }

    /**
     * Check if the charge is pending.
     */
    public function isPending(Response $charge): bool
    {
        return $charge->get('status') === 'pending';
    }

    /**
     * Check if the charge is successful.
     */
    public function isSuccessful(Response $charge): bool
    {
        return $charge->get('status') === 'successful';
    }

    /**
     * Check if the charge has failed.
     */
    public function isFailed(Response $charge): bool
    {
        return $charge->get('status') === 'failed';
    }

    /**
     * Check if the charge has expired.
     */
    public function isExpired(Response $charge): bool
    {
        return $charge->get('status') === 'expired';
    }

    /**
     * Check if the charge has been reversed.
     */
    public function isReversed(Response $charge): bool
    {
        return $charge->get('reversed') === true
            || $charge->get('status') === 'reversed';
    }

    /**
     * Check if the charge is refundable.
     */
    public function isRefundable(Response $charge): bool
    {
        return $charge->get('refundable') === true;
    }

    /**
     * Get the failure code from a failed charge.
     */
    public function getFailureCode(Response $charge): ?string
    {
        return $charge->get('failure_code');
    }

    /**
     * Get a human-readable failure message.
     *
     * @param Response $charge The charge response
     * @param string $locale Locale for message ('en' or 'th')
     */
    public function getFailureMessage(Response $charge, string $locale = 'th'): ?string
    {
        $code = $this->getFailureCode($charge);

        if ($locale === 'th') {
            return match ($code) {
                self::FAILURE_INVALID_CARD => 'บัตรไม่ถูกต้อง',
                self::FAILURE_INVALID_SECURITY_CODE => 'รหัสความปลอดภัยไม่ถูกต้อง',
                self::FAILURE_FAILED_FRAUD_CHECK => 'ไม่ผ่านการตรวจสอบความปลอดภัย',
                self::FAILURE_FAILED_PROCESSING => 'ระบบทำรายการไม่สำเร็จ',
                self::FAILURE_INSUFFICIENT_FUND => 'วงเงินไม่เพียงพอ',
                self::FAILURE_STOLEN_OR_LOST_CARD => 'บัตรถูกแจ้งหาย',
                self::FAILURE_PAYMENT_REJECTED => 'การชำระเงินถูกปฏิเสธ',
                self::FAILURE_INVALID_ACCOUNT_NUMBER => 'หมายเลขบัญชีไม่ถูกต้อง',
                default => $charge->get('failure_message'),
            };
        }

        return match ($code) {
            self::FAILURE_INVALID_CARD => 'Invalid card',
            self::FAILURE_INVALID_SECURITY_CODE => 'Invalid security code',
            self::FAILURE_FAILED_FRAUD_CHECK => 'Failed fraud check',
            self::FAILURE_FAILED_PROCESSING => 'Payment processing failed',
            self::FAILURE_INSUFFICIENT_FUND => 'Insufficient funds',
            self::FAILURE_STOLEN_OR_LOST_CARD => 'Card reported as stolen or lost',
            self::FAILURE_PAYMENT_REJECTED => 'Payment rejected',
            self::FAILURE_INVALID_ACCOUNT_NUMBER => 'Invalid account number',
            default => $charge->get('failure_message'),
        };
    }

    /**
     * Get card information from a charge.
     */
    public function getCardInfo(Response $charge): ?array
    {
        return $charge->get('card');
    }

    /**
     * Get the card brand from a charge.
     */
    public function getCardBrand(Response $charge): ?string
    {
        $card = $this->getCardInfo($charge);

        return $card['brand'] ?? null;
    }

    /**
     * Get the last 4 digits of the card from a charge.
     */
    public function getCardLastDigits(Response $charge): ?string
    {
        $card = $this->getCardInfo($charge);

        return $card['last_digits'] ?? null;
    }

    /**
     * Get the funding amount (in your settlement currency).
     */
    public function getFundingAmount(Response $charge): ?int
    {
        return $charge->get('funding_amount');
    }

    /**
     * Get the funding currency (your settlement currency).
     */
    public function getFundingCurrency(Response $charge): ?string
    {
        return $charge->get('funding_currency');
    }

    /**
     * Check if this is a multi-currency charge.
     */
    public function isMultiCurrency(Response $charge): bool
    {
        $currency = $charge->get('currency');
        $fundingCurrency = $charge->get('funding_currency');

        return $currency !== null
            && $fundingCurrency !== null
            && $currency !== $fundingCurrency;
    }

    /**
     * Get the net amount after fees.
     */
    public function getNetAmount(Response $charge): ?int
    {
        return $charge->get('net');
    }

    /**
     * Get the transaction fee.
     */
    public function getFee(Response $charge): ?int
    {
        return $charge->get('fee');
    }

    /**
     * Validate amount for a specific currency.
     */
    public function validateAmount(int $amount, string $currency): bool
    {
        return Currency::validateAmount($amount, $currency);
    }

    /**
     * Get minimum amount for a currency.
     */
    public function getMinimumAmount(string $currency): int
    {
        return Currency::getMinimumAmount($currency);
    }

    /**
     * Get maximum amount for a currency.
     */
    public function getMaximumAmount(string $currency): int
    {
        return Currency::getMaximumAmount($currency);
    }
}
