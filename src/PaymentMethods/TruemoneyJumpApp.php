<?php

declare(strict_types=1);

namespace Omise\PaymentMethods;

use Omise\Api\Source;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * TrueMoney App Redirection (Jump App) payment method implementation.
 *
 * TrueMoney Jump App redirects customers from your website or mobile app to
 * the TrueMoney app to authorize and confirm payment. Payment expires if not
 * completed within 3 minutes.
 *
 * Flow: app_redirect
 * - Create source with type 'truemoney_jumpapp'
 * - Create charge with return_uri
 * - Redirect customer to authorize_uri
 * - Customer opens TrueMoney app to authorize
 * - Receive webhook notification on completion
 *
 * @see https://docs.omise.co/truemoney-jumpapp
 */
class TruemoneyJumpApp extends AbstractPaymentMethod
{
    protected string $type = Source::TYPE_TRUEMONEY_JUMPAPP;
    protected string $name = 'TrueMoney App';
    protected string $flow = Source::FLOW_APP_REDIRECT;
    protected array $supportedCurrencies = ['THB'];

    /**
     * TrueMoney Jump App limits:
     * - Minimum: ฿100.00 (10,000 satang)
     * - Maximum: ฿50,000.00 (5,000,000 satang)
     */
    protected array $minimumAmounts = [
        'THB' => 10000, // 100 THB in satang
    ];

    protected array $maximumAmounts = [
        'THB' => 5000000, // 50,000 THB in satang
    ];

    /**
     * Required parameters for TrueMoney Jump App.
     */
    protected array $requiredParameters = ['return_uri'];

    /**
     * Failure codes specific to TrueMoney Jump App.
     */
    public const string FAILURE_PROCESSING = 'failed_processing';
    public const string FAILURE_INSUFFICIENT_BALANCE = 'insufficient_balance';
    public const string FAILURE_CANCELLED = 'payment_cancelled';
    public const string FAILURE_TIMEOUT = 'timeout';
    public const string FAILURE_EXPIRED = 'expired';

    /**
     * Create a TrueMoney Jump App charge.
     *
     * @param  int $amount  Amount in satang (smallest unit)
     * @param  string $currency  Currency code (must be 'THB')
     * @param  array $options  Charge options:
     *   - return_uri (required): URL to redirect after payment
     *   - webhook_endpoints: Array of webhook URLs
     *   - description: Charge description
     *   - metadata: Additional metadata
     *
     * @return Response The charge response containing authorize_uri for redirect
     * @throws ApiException
     */
    public function charge(int $amount, string $currency, array $options = []): Response
    {
        return parent::charge($amount, $currency, $options);
    }

    /**
     * Create a TrueMoney Jump App charge with simplified parameters.
     *
     * @param  float $amount  Amount in THB (will be converted to satang)
     * @param  string $returnUri  URL to redirect customer after payment
     * @param  array $webhookEndpoints  Optional webhook URLs
     * @throws ApiException
     */
    public function pay(
        float $amount,
        string $returnUri,
        array $webhookEndpoints = []
    ): Response {
        $options = [
            'return_uri' => $returnUri,
        ];

        if (! empty($webhookEndpoints)) {
            $options['webhook_endpoints'] = $webhookEndpoints;
        }

        // Convert THB to satang
        $amountInSatang = (int) round($amount * 100);

        return $this->charge($amountInSatang, 'THB', $options);
    }

    /**
     * Get the authorize URI from a charge response.
     *
     * This is the URL to redirect the customer to for payment authorization.
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
     * Check if the charge has expired (3-minute timeout).
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
                self::FAILURE_INSUFFICIENT_BALANCE => 'ยอดเงินไม่เพียงพอ',
                self::FAILURE_CANCELLED => 'ยกเลิกการชำระเงิน',
                self::FAILURE_TIMEOUT => 'หมดเวลาในการชำระเงิน (3 นาที)',
                self::FAILURE_EXPIRED => 'การชำระเงินหมดอายุ',
                default => $charge->get('failure_message'),
            };
        }

        return match ($code) {
            self::FAILURE_PROCESSING => 'Payment processing failed',
            self::FAILURE_INSUFFICIENT_BALANCE => 'Insufficient balance',
            self::FAILURE_CANCELLED => 'Payment was cancelled',
            self::FAILURE_TIMEOUT => 'Payment timed out (3 minutes)',
            self::FAILURE_EXPIRED => 'Payment expired',
            default => $charge->get('failure_message'),
        };
    }

    /**
     * Check if the charge can be voided (same-day only).
     */
    public function canVoid(Response $charge): bool
    {
        if (! $this->isSuccessful($charge)) {
            return false;
        }

        $createdAt = $charge->get('created_at');
        if ($createdAt === null) {
            return false;
        }

        // Same-day void only
        $createdDate = date('Y-m-d', strtotime($createdAt));
        $today = date('Y-m-d');

        return $createdDate === $today;
    }

    /**
     * Check if the charge can be refunded (within 30 days).
     * Note: Partial refunds only for wallet and bank account payments.
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
        $thirtyDaysInSeconds = 30 * 24 * 60 * 60;

        return ($now - $createdTime) <= $thirtyDaysInSeconds;
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
        $deadline = $createdTime + (30 * 24 * 60 * 60);

        return date('c', $deadline);
    }

    /**
     * Convert THB amount to satang.
     */
    public static function toSatang(float $thb): int
    {
        return (int) round($thb * 100);
    }

    /**
     * Convert satang amount to THB.
     */
    public static function toThb(int $satang): float
    {
        return $satang / 100;
    }

    /**
     * Validate amount in THB.
     */
    public function validateThbAmount(float $amount): bool
    {
        $satang = self::toSatang($amount);

        return $this->validateAmount($satang, 'THB');
    }

    /**
     * Get minimum amount in THB.
     */
    public function getMinimumThb(): float
    {
        return self::toThb($this->getMinimumAmount('THB'));
    }

    /**
     * Get maximum amount in THB.
     */
    public function getMaximumThb(): float
    {
        return self::toThb($this->getMaximumAmount('THB'));
    }
}
