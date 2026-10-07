<?php

declare(strict_types=1);

namespace Omise\PaymentMethods;

use InvalidArgumentException;
use Omise\Api\Charge;
use Omise\Api\Source;
use Omise\Currency;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Mobile Banking payment method implementation.
 *
 * Mobile Banking enables customers to pay through their bank's mobile application.
 * Customers are redirected to authorize payment in their banking app.
 *
 * Supported banks:
 * - Bangkok Bank (BBL) - Bualuang mBanking
 * - KBank - K PLUS
 * - Krungthai Bank (KTB) - KTB NEXT
 * - Bank of Ayudhya (BAY) - KMA (Krungsri Mobile App)
 * - Siam Commercial Bank (SCB) - SCB Easy
 * - OCBC Digital (Singapore)
 *
 * Flow: app_redirect
 * - Create source with bank type and optional platform_type
 * - Create charge with return_uri
 * - Redirect customer to authorize_uri (deep link to bank app)
 * - Customer authorizes in bank app
 * - Receive webhook notification on completion
 *
 * @see https://docs.omise.co/mobile-banking
 */
class MobileBanking
{
    protected Charge $chargeApi;
    protected Source $sourceApi;

    /**
     * Bank type constants.
     */
    public const string BANK_BBL = 'mobile_banking_bbl';    // Bangkok Bank (Bualuang mBanking)
    public const string BANK_KBANK = 'mobile_banking_kbank'; // KBank (K PLUS)
    public const string BANK_KTB = 'mobile_banking_ktb';     // Krungthai Bank (KTB NEXT)
    public const string BANK_BAY = 'mobile_banking_bay';     // Bank of Ayudhya (KMA)
    public const string BANK_SCB = 'mobile_banking_scb';     // Siam Commercial Bank (SCB Easy)
    public const string BANK_OCBC = 'mobile_banking_ocbc';   // OCBC Digital (Singapore)

    /**
     * Platform type constants.
     */
    public const string PLATFORM_IOS = 'IOS';
    public const string PLATFORM_ANDROID = 'ANDROID';

    /**
     * Bank display names.
     */
    private const array BANK_NAMES = [
        self::BANK_BBL => 'Bangkok Bank (Bualuang mBanking)',
        self::BANK_KBANK => 'KBank (K PLUS)',
        self::BANK_KTB => 'Krungthai Bank (KTB NEXT)',
        self::BANK_BAY => 'Bank of Ayudhya (KMA)',
        self::BANK_SCB => 'Siam Commercial Bank (SCB Easy)',
        self::BANK_OCBC => 'OCBC Digital',
    ];

    /**
     * Bank short codes.
     */
    private const array BANK_CODES = [
        self::BANK_BBL => 'BBL',
        self::BANK_KBANK => 'KBANK',
        self::BANK_KTB => 'KTB',
        self::BANK_BAY => 'BAY',
        self::BANK_SCB => 'SCB',
        self::BANK_OCBC => 'OCBC',
    ];

    /**
     * Bank supported currencies.
     */
    private const array BANK_CURRENCIES = [
        self::BANK_BBL => 'THB',
        self::BANK_KBANK => 'THB',
        self::BANK_KTB => 'THB',
        self::BANK_BAY => 'THB',
        self::BANK_SCB => 'THB',
        self::BANK_OCBC => 'SGD',
    ];

    /**
     * Bank supported countries.
     */
    private const array BANK_COUNTRIES = [
        self::BANK_BBL => 'Thailand',
        self::BANK_KBANK => 'Thailand',
        self::BANK_KTB => 'Thailand',
        self::BANK_BAY => 'Thailand',
        self::BANK_SCB => 'Thailand',
        self::BANK_OCBC => 'Singapore',
    ];

    /**
     * Bank minimum amounts (in smallest currency unit).
     */
    private const array BANK_MIN_AMOUNTS = [
        self::BANK_BBL => 2000,      // 20 THB
        self::BANK_KBANK => 2000,    // 20 THB
        self::BANK_KTB => 2000,      // 20 THB
        self::BANK_BAY => 2000,      // 20 THB
        self::BANK_SCB => 2000,      // 20 THB
        self::BANK_OCBC => 100,      // 1 SGD
    ];

    /**
     * Bank maximum amounts (in smallest currency unit).
     */
    private const array BANK_MAX_AMOUNTS = [
        self::BANK_BBL => 15000000,   // 150,000 THB
        self::BANK_KBANK => 15000000, // 150,000 THB
        self::BANK_KTB => 15000000,   // 150,000 THB
        self::BANK_BAY => 15000000,   // 150,000 THB
        self::BANK_SCB => 15000000,   // 150,000 THB
        self::BANK_OCBC => 2000000,   // 20,000 SGD
    ];

    /**
     * Bank charge expiration times (description).
     */
    private const array BANK_EXPIRATIONS = [
        self::BANK_BBL => '15 minutes',
        self::BANK_KBANK => '10 minutes',
        self::BANK_KTB => '30 minutes',
        self::BANK_BAY => '15 minutes',
        self::BANK_SCB => '7 days',
        self::BANK_OCBC => 'varies',
    ];

    /**
     * Banks that support refunds.
     */
    private const array REFUNDABLE_BANKS = [
        self::BANK_OCBC,
    ];

    /**
     * Failure codes.
     */
    public const string FAILURE_PROCESSING = 'failed_processing';
    public const string FAILURE_INSUFFICIENT_FUND = 'insufficient_fund';
    public const string FAILURE_INSUFFICIENT_BALANCE = 'insufficient_balance';
    public const string FAILURE_INVALID_ACCOUNT = 'invalid_account';
    public const string FAILURE_PAYMENT_REJECTED = 'payment_rejected';
    public const string FAILURE_PAYMENT_EXPIRED = 'payment_expired';
    public const string FAILURE_PAYMENT_CANCELLED = 'payment_cancelled';
    public const string FAILURE_TIMEOUT = 'timeout';

    public function __construct(Charge $chargeApi, Source $sourceApi)
    {
        $this->chargeApi = $chargeApi;
        $this->sourceApi = $sourceApi;
    }

    /**
     * Create a mobile banking charge.
     *
     * @param  string $bankType  Bank type constant (e.g., BANK_KBANK)
     * @param  int $amount  Amount in smallest currency unit
     * @param  string $returnUri  URL to redirect after payment
     * @param  array $options  Additional options:
     *   - platform_type: 'IOS' or 'ANDROID'
     *   - webhook_endpoints: Array of webhook URLs
     *   - description: Charge description
     *   - metadata: Custom metadata
     * @throws ApiException
     * @throws InvalidArgumentException
     */
    public function charge(
        string $bankType,
        int $amount,
        string $returnUri,
        array $options = []
    ): Response {
        $this->validateBank($bankType);
        $this->validateAmount($bankType, $amount);

        $currency = $this->getCurrency($bankType);

        // Build source parameters
        $sourceParams = [
            'type' => $bankType,
            'amount' => $amount,
            'currency' => $currency,
        ];

        if (isset($options['platform_type'])) {
            $sourceParams['platform_type'] = $options['platform_type'];
        }

        // Create source
        $source = $this->sourceApi->create($sourceParams);

        // Build charge parameters
        $chargeParams = [
            'amount' => $amount,
            'currency' => $currency,
            'source' => $source->getId(),
            'return_uri' => $returnUri,
        ];

        if (isset($options['webhook_endpoints'])) {
            $chargeParams['webhook_endpoints'] = $options['webhook_endpoints'];
        }

        if (isset($options['description'])) {
            $chargeParams['description'] = $options['description'];
        }

        if (isset($options['metadata'])) {
            $chargeParams['metadata'] = $options['metadata'];
        }

        return $this->chargeApi->create($chargeParams);
    }

    /**
     * Create a mobile banking charge with amount in main currency unit.
     *
     * @param  string $bankType  Bank type constant
     * @param  float $amount  Amount in main currency unit (e.g., THB or SGD)
     * @param  string $returnUri  URL to redirect after payment
     * @param  array $options  Additional options
     * @throws ApiException
     */
    public function pay(
        string $bankType,
        float $amount,
        string $returnUri,
        array $options = []
    ): Response {
        $currency = $this->getCurrency($bankType);
        $amountInSmallestUnit = Currency::toSmallestUnit($amount, $currency);

        return $this->charge($bankType, $amountInSmallestUnit, $returnUri, $options);
    }

    /**
     * Get the authorize URI to redirect customer for payment authorization.
     */
    public function getAuthorizeUri(Response $charge): ?string
    {
        return $charge->get('authorize_uri');
    }

    /**
     * Get the return URI from a charge.
     */
    public function getReturnUri(Response $charge): ?string
    {
        return $charge->get('return_uri');
    }

    /**
     * Check if the charge is awaiting customer redirect.
     */
    public function isPendingRedirect(Response $charge): bool
    {
        return $charge->get('status') === 'pending'
            && $this->getAuthorizeUri($charge) !== null;
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
     * Get the failure code from a failed charge.
     */
    public function getFailureCode(Response $charge): ?string
    {
        return $charge->get('failure_code');
    }

    /**
     * Get a human-readable failure message.
     *
     * @param  Response $charge  The charge response
     * @param  string $locale  Locale for message ('en' or 'th')
     */
    public function getFailureMessage(Response $charge, string $locale = 'th'): ?string
    {
        $code = $this->getFailureCode($charge);

        if ($locale === 'th') {
            return match ($code) {
                self::FAILURE_PROCESSING => 'ระบบทำรายการไม่สำเร็จ',
                self::FAILURE_INSUFFICIENT_FUND,
                self::FAILURE_INSUFFICIENT_BALANCE => 'ยอดเงินไม่เพียงพอ',
                self::FAILURE_INVALID_ACCOUNT => 'บัญชีไม่ถูกต้องหรือไม่พบ',
                self::FAILURE_PAYMENT_REJECTED => 'ธนาคารปฏิเสธการชำระเงิน',
                self::FAILURE_PAYMENT_EXPIRED => 'หมดเวลาในการชำระเงิน',
                self::FAILURE_PAYMENT_CANCELLED => 'ผู้ซื้อยกเลิกการชำระเงิน',
                self::FAILURE_TIMEOUT => 'หมดเวลาในการชำระเงิน',
                default => $charge->get('failure_message'),
            };
        }

        return match ($code) {
            self::FAILURE_PROCESSING => 'Payment processing failed',
            self::FAILURE_INSUFFICIENT_FUND,
            self::FAILURE_INSUFFICIENT_BALANCE => 'Insufficient funds or balance',
            self::FAILURE_INVALID_ACCOUNT => 'Invalid or not found account',
            self::FAILURE_PAYMENT_REJECTED => 'Payment rejected by bank',
            self::FAILURE_PAYMENT_EXPIRED => 'Payment authorization expired',
            self::FAILURE_PAYMENT_CANCELLED => 'Payment cancelled by customer',
            self::FAILURE_TIMEOUT => 'Payment timed out',
            default => $charge->get('failure_message'),
        };
    }

    /**
     * Get all supported banks.
     *
     * @return array<string, string> Bank type => Bank name
     */
    public function getSupportedBanks(): array
    {
        return self::BANK_NAMES;
    }

    /**
     * Get Thailand banks only.
     *
     * @return array<string, string> Bank type => Bank name
     */
    public function getThailandBanks(): array
    {
        return array_filter(
            self::BANK_NAMES,
            fn (string $bankType) => self::BANK_COUNTRIES[$bankType] === 'Thailand',
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Get Singapore banks only.
     *
     * @return array<string, string> Bank type => Bank name
     */
    public function getSingaporeBanks(): array
    {
        return array_filter(
            self::BANK_NAMES,
            fn (string $bankType) => self::BANK_COUNTRIES[$bankType] === 'Singapore',
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Get bank name for a type.
     */
    public function getBankName(string $bankType): string
    {
        return self::BANK_NAMES[$bankType] ?? $bankType;
    }

    /**
     * Get bank short code for a type.
     */
    public function getBankCode(string $bankType): string
    {
        return self::BANK_CODES[$bankType] ?? $bankType;
    }

    /**
     * Get the currency for a bank.
     */
    public function getCurrency(string $bankType): string
    {
        return self::BANK_CURRENCIES[$bankType] ?? 'THB';
    }

    /**
     * Get the country for a bank.
     */
    public function getCountry(string $bankType): string
    {
        return self::BANK_COUNTRIES[$bankType] ?? 'Thailand';
    }

    /**
     * Get the charge expiration time description for a bank.
     */
    public function getExpirationTime(string $bankType): string
    {
        return self::BANK_EXPIRATIONS[$bankType] ?? 'varies';
    }

    /**
     * Check if a bank type is supported.
     */
    public function isSupportedBank(string $bankType): bool
    {
        return isset(self::BANK_NAMES[$bankType]);
    }

    /**
     * Get minimum amount for a bank (in smallest currency unit).
     */
    public function getMinimumAmount(string $bankType): int
    {
        return self::BANK_MIN_AMOUNTS[$bankType] ?? 0;
    }

    /**
     * Get maximum amount for a bank (in smallest currency unit).
     */
    public function getMaximumAmount(string $bankType): int
    {
        return self::BANK_MAX_AMOUNTS[$bankType] ?? PHP_INT_MAX;
    }

    /**
     * Get minimum amount in main currency unit.
     */
    public function getMinimumInMainUnit(string $bankType): float
    {
        $currency = $this->getCurrency($bankType);

        return Currency::toMainUnit($this->getMinimumAmount($bankType), $currency);
    }

    /**
     * Get maximum amount in main currency unit.
     */
    public function getMaximumInMainUnit(string $bankType): float
    {
        $currency = $this->getCurrency($bankType);

        return Currency::toMainUnit($this->getMaximumAmount($bankType), $currency);
    }

    /**
     * Validate bank type is supported.
     *
     * @throws InvalidArgumentException
     */
    public function validateBank(string $bankType): void
    {
        if (! $this->isSupportedBank($bankType)) {
            throw new InvalidArgumentException("Unsupported bank type: {$bankType}");
        }
    }

    /**
     * Validate amount is within limits for the bank.
     *
     * @throws InvalidArgumentException
     */
    public function validateAmount(string $bankType, int $amount): bool
    {
        $min = $this->getMinimumAmount($bankType);
        $max = $this->getMaximumAmount($bankType);
        $currency = $this->getCurrency($bankType);

        if ($amount < $min) {
            $minInMain = Currency::toMainUnit($min, $currency);

            throw new InvalidArgumentException(
                "Amount must be at least {$min} ({$minInMain} {$currency})"
            );
        }

        if ($amount > $max) {
            $maxInMain = Currency::toMainUnit($max, $currency);

            throw new InvalidArgumentException(
                "Amount must not exceed {$max} ({$maxInMain} {$currency})"
            );
        }

        return true;
    }

    /**
     * Check if a bank supports refunds.
     * Note: Most mobile banking charges cannot be refunded through Omise.
     */
    public function canRefund(string $bankType): bool
    {
        return in_array($bankType, self::REFUNDABLE_BANKS, true);
    }

    /**
     * Check if a charge from a specific bank can be refunded.
     * OCBC supports refunds within 180 days.
     */
    public function canRefundCharge(Response $charge): bool
    {
        if (! $this->isSuccessful($charge)) {
            return false;
        }

        // Get the source type from the charge
        $source = $charge->get('source');
        $bankType = is_array($source) ? ($source['type'] ?? null) : null;

        if ($bankType === null || ! $this->canRefund($bankType)) {
            return false;
        }

        // OCBC has 180-day refund window
        if ($bankType === self::BANK_OCBC) {
            $createdAt = $charge->get('created_at');
            if ($createdAt === null) {
                return false;
            }

            $createdTime = strtotime($createdAt);
            $now = time();
            $refundWindowSeconds = 180 * 24 * 60 * 60;

            return ($now - $createdTime) <= $refundWindowSeconds;
        }

        return false;
    }

    /**
     * Convert amount to smallest currency unit for a bank.
     */
    public function toSmallestUnit(string $bankType, float $amount): int
    {
        $currency = $this->getCurrency($bankType);

        return Currency::toSmallestUnit($amount, $currency);
    }

    /**
     * Convert amount from smallest currency unit to main unit for a bank.
     */
    public function toMainUnit(string $bankType, int $amount): float
    {
        $currency = $this->getCurrency($bankType);

        return Currency::toMainUnit($amount, $currency);
    }
}
