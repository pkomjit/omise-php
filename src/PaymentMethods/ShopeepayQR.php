<?php

declare(strict_types=1);

namespace Omise\PaymentMethods;

use Omise\Api\Source;
use Omise\Currency;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * ShopeePay QR payment method implementation.
 *
 * ShopeePay QR allows customers to pay by scanning a QR code using the ShopeePay app
 * or Shopee app. Customers are redirected to ShopeePay's QR page where they can scan
 * the QR code to complete the payment.
 *
 * Supported countries: Thailand, Singapore, Malaysia
 * Flow: redirect (QR code page)
 *
 * @see https://docs.omise.co/shopeepay-qr
 */
class ShopeepayQR extends AbstractPaymentMethod
{
    protected string $type = Source::TYPE_SHOPEEPAY;
    protected string $name = 'ShopeePay QR';
    protected string $flow = Source::FLOW_REDIRECT;
    protected array $supportedCurrencies = ['THB', 'SGD', 'MYR'];

    /**
     * Amount limits per currency (in smallest units).
     *
     * Thailand: 20 - 150,000 THB (2,000 - 15,000,000 satang)
     * Singapore: 1 - 20,000 SGD (100 - 2,000,000 cents)
     * Malaysia: 1 - 4,999 MYR (100 - 499,900 sen)
     */
    protected array $minimumAmounts = [
        'THB' => 2000,      // 20 THB in satang
        'SGD' => 100,       // 1 SGD in cents
        'MYR' => 100,       // 1 MYR in sen
    ];

    protected array $maximumAmounts = [
        'THB' => 15000000,  // 150,000 THB in satang
        'SGD' => 2000000,   // 20,000 SGD in cents
        'MYR' => 499900,    // 4,999 MYR in sen
    ];

    /**
     * Expiration times by country.
     */
    public const int EXPIRATION_TH_SG_MINUTES = 20;
    public const int EXPIRATION_MY_MINUTES = 60;
    public const int MAX_EXPIRATION_MINUTES = 60;

    /**
     * Failure codes specific to ShopeePay.
     */
    public const string FAILURE_PROCESSING = 'failed_processing';
    public const string FAILURE_CANCELLED = 'payment_cancelled';
    public const string FAILURE_EXPIRED = 'payment_expired';
    public const string FAILURE_REJECTED = 'payment_rejected';
    public const string FAILURE_INVALID_ACCOUNT = 'invalid_account';
    public const string FAILURE_INSUFFICIENT_FUND = 'insufficient_fund';

    /**
     * Refund window in days.
     */
    public const int REFUND_WINDOW_DAYS = 180;

    /**
     * Currency configurations.
     */
    private const array CURRENCY_COUNTRIES = [
        'THB' => 'Thailand',
        'SGD' => 'Singapore',
        'MYR' => 'Malaysia',
    ];

    /**
     * Create a ShopeePay QR charge.
     *
     * @param  int $amount  Amount in smallest unit
     * @param  string $currency  Currency code (THB, SGD, MYR)
     * @param  array $options  Charge options:
     *   - return_uri (required): URL to redirect after payment
     *   - webhook_endpoints: Array of webhook URLs
     *   - description: Charge description
     *   - metadata: Additional metadata
     *   - expires_at: ISO 8601 datetime for expiration (max 60 min)
     *
     * @return Response The charge response containing authorize_uri
     * @throws ApiException
     */
    public function charge(int $amount, string $currency, array $options = []): Response
    {
        return parent::charge($amount, $currency, $options);
    }

    /**
     * Create a ShopeePay QR charge with simplified parameters.
     *
     * @param  float $amount  Amount in main currency unit
     * @param  string $currency  Currency code (THB, SGD, MYR)
     * @param  string $returnUri  URL to redirect customer after payment
     * @param  array $webhookEndpoints  Optional webhook URLs
     * @param  string|null $expiresAt  Optional expiration datetime (ISO 8601)
     * @throws ApiException
     */
    public function pay(
        float $amount,
        string $currency,
        string $returnUri,
        array $webhookEndpoints = [],
        ?string $expiresAt = null
    ): Response {
        $options = [
            'return_uri' => $returnUri,
        ];

        if (! empty($webhookEndpoints)) {
            $options['webhook_endpoints'] = $webhookEndpoints;
        }

        if ($expiresAt !== null) {
            $options['expires_at'] = $expiresAt;
        }

        $amountInSmallestUnit = Currency::toSmallestUnit($amount, $currency);

        return $this->charge($amountInSmallestUnit, strtoupper($currency), $options);
    }

    /**
     * Create a THB charge with simplified parameters.
     *
     * @param  float $amount  Amount in THB
     * @param  string $returnUri  URL to redirect customer after payment
     * @param  array $webhookEndpoints  Optional webhook URLs
     * @throws ApiException
     */
    public function payThb(
        float $amount,
        string $returnUri,
        array $webhookEndpoints = []
    ): Response {
        return $this->pay($amount, 'THB', $returnUri, $webhookEndpoints);
    }

    /**
     * Create a SGD charge with simplified parameters.
     *
     * @param  float $amount  Amount in SGD
     * @param  string $returnUri  URL to redirect customer after payment
     * @param  array $webhookEndpoints  Optional webhook URLs
     * @throws ApiException
     */
    public function paySgd(
        float $amount,
        string $returnUri,
        array $webhookEndpoints = []
    ): Response {
        return $this->pay($amount, 'SGD', $returnUri, $webhookEndpoints);
    }

    /**
     * Create a MYR charge with simplified parameters.
     *
     * @param  float $amount  Amount in MYR
     * @param  string $returnUri  URL to redirect customer after payment
     * @param  array $webhookEndpoints  Optional webhook URLs
     * @throws ApiException
     */
    public function payMyr(
        float $amount,
        string $returnUri,
        array $webhookEndpoints = []
    ): Response {
        return $this->pay($amount, 'MYR', $returnUri, $webhookEndpoints);
    }

    /**
     * Get the authorize URI from a charge response.
     *
     * This is the URL to redirect the customer to for QR code display.
     */
    public function getAuthorizeUri(Response $charge): ?string
    {
        return $charge->get('authorize_uri');
    }

    /**
     * Get the return URI from a charge response.
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
     * Check if the charge is pending payment.
     */
    public function isPending(Response $charge): bool
    {
        return $charge->get('status') === 'pending';
    }

    /**
     * Check if the charge payment was successful.
     */
    public function isSuccessful(Response $charge): bool
    {
        return $charge->get('status') === 'successful';
    }

    /**
     * Check if the charge has expired.
     */
    public function isExpired(Response $charge): bool
    {
        return $charge->get('status') === 'expired';
    }

    /**
     * Check if the charge has failed.
     */
    public function isFailed(Response $charge): bool
    {
        return $charge->get('status') === 'failed';
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
                self::FAILURE_CANCELLED => 'ยกเลิกการชำระเงิน',
                self::FAILURE_EXPIRED => 'การชำระเงินหมดอายุ',
                self::FAILURE_REJECTED => 'การชำระเงินถูกปฏิเสธ',
                self::FAILURE_INVALID_ACCOUNT => 'ไม่พบบัญชี ShopeePay ที่ถูกต้อง',
                self::FAILURE_INSUFFICIENT_FUND => 'ยอดเงินไม่เพียงพอหรือเกินวงเงิน',
                default => $charge->get('failure_message'),
            };
        }

        return match ($code) {
            self::FAILURE_PROCESSING => 'Payment processing failed',
            self::FAILURE_CANCELLED => 'Payment was cancelled',
            self::FAILURE_EXPIRED => 'Payment expired',
            self::FAILURE_REJECTED => 'Payment was rejected by issuer',
            self::FAILURE_INVALID_ACCOUNT => 'No valid ShopeePay account found',
            self::FAILURE_INSUFFICIENT_FUND => 'Insufficient funds or limit exceeded',
            default => $charge->get('failure_message'),
        };
    }

    /**
     * Check if the charge can be refunded (within 180 days).
     * Note: Refunds are not available for off-us (mobile banking) transactions.
     */
    public function canRefund(Response $charge): bool
    {
        if (! $this->isSuccessful($charge)) {
            return false;
        }

        $createdAt = $charge->get('created_at');
        if ($createdAt === null) {
            return false;
        }

        $createdTime = strtotime($createdAt);
        $now = time();
        $refundWindowInSeconds = self::REFUND_WINDOW_DAYS * 24 * 60 * 60;

        return ($now - $createdTime) <= $refundWindowInSeconds;
    }

    /**
     * Get refund deadline for a charge.
     *
     * @return string|null ISO 8601 datetime string
     */
    public function getRefundDeadline(Response $charge): ?string
    {
        $createdAt = $charge->get('created_at');
        if ($createdAt === null) {
            return null;
        }

        $createdTime = strtotime($createdAt);
        $deadline = $createdTime + (self::REFUND_WINDOW_DAYS * 24 * 60 * 60);

        return date('c', $deadline);
    }

    /**
     * Get the country for a currency.
     */
    public function getCountryForCurrency(string $currency): string
    {
        return self::CURRENCY_COUNTRIES[strtoupper($currency)] ?? 'Unknown';
    }

    /**
     * Get the default expiration time for a currency (in minutes).
     */
    public function getDefaultExpirationMinutes(string $currency): int
    {
        return match (strtoupper($currency)) {
            'MYR' => self::EXPIRATION_MY_MINUTES,
            default => self::EXPIRATION_TH_SG_MINUTES,
        };
    }

    /**
     * Get minimum amount in main currency unit.
     */
    public function getMinimumInMainUnit(string $currency): float
    {
        $minSmallest = $this->getMinimumAmount($currency);

        return Currency::toMainUnit($minSmallest, $currency);
    }

    /**
     * Get maximum amount in main currency unit.
     */
    public function getMaximumInMainUnit(string $currency): float
    {
        $maxSmallest = $this->getMaximumAmount($currency);

        return Currency::toMainUnit($maxSmallest, $currency);
    }

    /**
     * Convert amount to smallest unit for currency.
     */
    public function toSmallestUnit(float $amount, string $currency): int
    {
        return Currency::toSmallestUnit($amount, $currency);
    }

    /**
     * Convert amount to main unit for currency.
     */
    public function toMainUnit(int $amount, string $currency): float
    {
        return Currency::toMainUnit($amount, $currency);
    }

    /**
     * Validate amount for a specific currency.
     *
     * @throws \InvalidArgumentException
     */
    public function validateAmountForCurrency(float $amount, string $currency): bool
    {
        $currency = strtoupper($currency);

        if (! in_array($currency, $this->supportedCurrencies, true)) {
            throw new \InvalidArgumentException("Unsupported currency: {$currency}");
        }

        $amountInSmallest = $this->toSmallestUnit($amount, $currency);

        return $this->validateAmount($amountInSmallest, $currency);
    }
}
