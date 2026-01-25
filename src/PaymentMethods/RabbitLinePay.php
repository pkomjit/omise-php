<?php

declare(strict_types=1);

namespace Omise\PaymentMethods;

use Omise\Api\Source;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Rabbit LINE Pay payment method implementation.
 *
 * Rabbit LINE Pay is a mobile e-wallet service by Rabbit-LINE Pay Company
 * (a joint venture between LINE and Rabbit) that integrates with LINE Messenger.
 *
 * Flow: redirect
 * - Create source with type 'rabbit_linepay'
 * - Create charge with return_uri
 * - Redirect customer to authorize_uri
 * - Customer authorizes in LINE app
 * - Receive webhook notification on completion
 *
 * @see https://docs.omise.co/rabbit-linepay
 */
class RabbitLinePay extends AbstractPaymentMethod
{
    protected string $type = Source::TYPE_RABBIT_LINEPAY;
    protected string $name = 'Rabbit LINE Pay';
    protected string $flow = Source::FLOW_REDIRECT;
    protected array $supportedCurrencies = ['THB'];

    /**
     * Rabbit LINE Pay limits:
     * - Minimum: ฿20.00 (2,000 satang)
     * - Maximum: ฿150,000.00 (15,000,000 satang)
     */
    protected array $minimumAmounts = [
        'THB' => 2000, // 20 THB in satang
    ];

    protected array $maximumAmounts = [
        'THB' => 15000000, // 150,000 THB in satang
    ];

    /**
     * Required parameters for Rabbit LINE Pay.
     */
    protected array $requiredParameters = ['return_uri'];

    /**
     * Failure codes specific to Rabbit LINE Pay.
     */
    public const string FAILURE_PROCESSING = 'failed_processing';
    public const string FAILURE_INSUFFICIENT_BALANCE = 'insufficient_balance';
    public const string FAILURE_CANCELLED = 'payment_cancelled';
    public const string FAILURE_TIMEOUT = 'timeout';

    /**
     * Create a Rabbit LINE Pay charge.
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
     * Create a Rabbit LINE Pay charge with simplified parameters.
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
     * Check if the charge was reversed/refunded.
     */
    public function isReversed(Response $charge): bool
    {
        return $charge->get('status') === 'reversed';
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
                self::FAILURE_INSUFFICIENT_BALANCE => 'วงเงินคงเหลือไม่เพียงพอ',
                self::FAILURE_CANCELLED => 'ผู้ซื้อยกเลิกการชำระเงิน',
                self::FAILURE_TIMEOUT => 'หมดเวลาในการชำระเงิน',
                default => $charge->get('failure_message'),
            };
        }

        return match ($code) {
            self::FAILURE_PROCESSING => 'Payment processing failed',
            self::FAILURE_INSUFFICIENT_BALANCE => 'Insufficient balance',
            self::FAILURE_CANCELLED => 'Payment was cancelled by customer',
            self::FAILURE_TIMEOUT => 'Payment timed out',
            default => $charge->get('failure_message'),
        };
    }

    /**
     * Check if the charge can be refunded.
     *
     * Rabbit LINE Pay supports refunds within 60 days of the original charge.
     */
    public function canRefund(Response $charge): bool
    {
        if (! $this->isSuccessful($charge)) {
            return false;
        }

        // Check if within 60-day refund window
        $createdAt = $charge->get('created_at');

        if ($createdAt === null) {
            return false;
        }

        $createdTime = strtotime($createdAt);
        $now = time();
        $sixtyDaysInSeconds = 60 * 24 * 60 * 60;

        return ($now - $createdTime) <= $sixtyDaysInSeconds;
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
        $deadline = $createdTime + (60 * 24 * 60 * 60);

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
