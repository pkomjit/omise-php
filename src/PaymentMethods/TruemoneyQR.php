<?php

declare(strict_types=1);

namespace Omise\PaymentMethods;

use Omise\Api\Source;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * TrueMoney QR payment method implementation.
 *
 * TrueMoney QR allows customers to pay by scanning a QR code using the TrueMoney app.
 * Supports payment via TrueMoney Wallet balance, bank accounts, credit/debit cards,
 * Pay Next, and Pay Next Extra.
 *
 * Flow: offline (QR code)
 * - Create source with type 'truemoney_qr'
 * - Create charge
 * - Customer scans QR code with TrueMoney app
 * - Customer authorizes payment
 * - Receive webhook notification on completion
 *
 * @see https://docs.omise.co/truemoney-qr
 */
class TruemoneyQR extends AbstractPaymentMethod
{
    protected string $type = Source::TYPE_TRUEMONEY_QR;
    protected string $name = 'TrueMoney QR';
    protected string $flow = Source::FLOW_OFFLINE;
    protected array $supportedCurrencies = ['THB'];

    /**
     * TrueMoney QR limits:
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
     * Failure codes specific to TrueMoney QR.
     */
    public const string FAILURE_PROCESSING = 'failed_processing';
    public const string FAILURE_INSUFFICIENT_BALANCE = 'insufficient_balance';
    public const string FAILURE_CANCELLED = 'payment_cancelled';
    public const string FAILURE_TIMEOUT = 'timeout';
    public const string FAILURE_EXPIRED = 'expired';

    /**
     * Create a TrueMoney QR charge.
     *
     * @param  int $amount  Amount in satang (smallest unit)
     * @param  string $currency  Currency code (must be 'THB')
     * @param  array $options  Charge options:
     *   - webhook_endpoints: Array of webhook URLs
     *   - description: Charge description
     *   - metadata: Additional metadata
     *   - expires_at: ISO 8601 datetime for expiration
     *
     * @return Response The charge response containing scannable_code for QR
     * @throws ApiException
     */
    public function charge(int $amount, string $currency, array $options = []): Response
    {
        return parent::charge($amount, $currency, $options);
    }

    /**
     * Create a TrueMoney QR charge with simplified parameters.
     *
     * @param  float $amount  Amount in THB (will be converted to satang)
     * @param  array $webhookEndpoints  Optional webhook URLs
     * @param  string|null $expiresAt  Optional expiration datetime (ISO 8601)
     * @throws ApiException
     */
    public function pay(
        float $amount,
        array $webhookEndpoints = [],
        ?string $expiresAt = null
    ): Response {
        $options = [];

        if (! empty($webhookEndpoints)) {
            $options['webhook_endpoints'] = $webhookEndpoints;
        }

        if ($expiresAt !== null) {
            $options['expires_at'] = $expiresAt;
        }

        // Convert THB to satang
        $amountInSatang = (int) round($amount * 100);

        return $this->charge($amountInSatang, 'THB', $options);
    }

    /**
     * Get the QR code URL from a charge response.
     */
    public function getQrCodeUrl(Response $charge): ?string
    {
        $source = $charge->get('source');
        if (is_array($source) && isset($source['scannable_code']['image']['download_uri'])) {
            return $source['scannable_code']['image']['download_uri'];
        }

        return null;
    }

    /**
     * Get QR code as base64 encoded string (SVG or PNG).
     */
    public function getQrCodeBase64(Response $charge): ?string
    {
        $url = $this->getQrCodeUrl($charge);
        if ($url === null) {
            return null;
        }

        $content = $this->downloadQrCode($url);
        if ($content === null) {
            return null;
        }

        return base64_encode($content);
    }

    /**
     * Get QR code as a data URI for embedding in HTML.
     */
    public function getQrCodeDataUri(Response $charge): ?string
    {
        $url = $this->getQrCodeUrl($charge);
        if ($url === null) {
            return null;
        }

        $content = $this->downloadQrCode($url);
        if ($content === null) {
            return null;
        }

        $mimeType = $this->detectMimeType($content);

        return "data:{$mimeType};base64," . base64_encode($content);
    }

    /**
     * Detect MIME type from content.
     */
    private function detectMimeType(string $content): string
    {
        // Check for SVG
        if (str_contains($content, '<svg') || str_contains($content, '<?xml')) {
            return 'image/svg+xml';
        }

        // Check for PNG magic bytes
        if (str_starts_with($content, "\x89PNG")) {
            return 'image/png';
        }

        // Default to SVG
        return 'image/svg+xml';
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
                self::FAILURE_INSUFFICIENT_BALANCE => 'ยอดเงินไม่เพียงพอ',
                self::FAILURE_CANCELLED => 'ยกเลิกการชำระเงิน',
                self::FAILURE_TIMEOUT => 'หมดเวลาในการชำระเงิน',
                self::FAILURE_EXPIRED => 'QR Code หมดอายุ',
                default => $charge->get('failure_message'),
            };
        }

        return match ($code) {
            self::FAILURE_PROCESSING => 'Payment processing failed',
            self::FAILURE_INSUFFICIENT_BALANCE => 'Insufficient balance',
            self::FAILURE_CANCELLED => 'Payment was cancelled',
            self::FAILURE_TIMEOUT => 'Payment timed out',
            self::FAILURE_EXPIRED => 'QR code expired',
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
