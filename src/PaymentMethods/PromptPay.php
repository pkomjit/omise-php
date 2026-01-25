<?php

declare(strict_types=1);

namespace Omise\PaymentMethods;

use Omise\Api\Source;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * PromptPay payment method implementation.
 *
 * PromptPay is Thailand's national e-payment system using QR codes.
 *
 * @see https://docs.omise.co/promptpay
 */
class PromptPay extends AbstractPaymentMethod
{
    protected string $type = Source::TYPE_PROMPTPAY;
    protected string $name = 'PromptPay';
    protected string $flow = Source::FLOW_OFFLINE;
    protected array $supportedCurrencies = ['THB'];

    /**
     * PromptPay limits:
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
     * Default QR code expiration in seconds (24 hours).
     */
    private const int DEFAULT_EXPIRATION = 86400;

    /**
     * Create a PromptPay charge and return the QR code.
     *
     * @param  int $amount  Amount in satang (smallest unit)
     * @param  string $currency  Currency code (must be 'THB')
     * @param  array $options  Additional options:
     *   - webhook_endpoints: Array of webhook URLs
     *   - expires_at: Expiration time (ISO 8601)
     *   - description: Charge description
     *   - metadata: Additional metadata
     *
     * @return Response The charge response containing QR code URL
     * @throws ApiException
     */
    public function charge(int $amount, string $currency, array $options = []): Response
    {
        return parent::charge($amount, $currency, $options);
    }

    /**
     * Create a PromptPay charge with simplified parameters.
     *
     * @param  float $amount  Amount in THB (will be converted to satang)
     * @param  array $webhookEndpoints  Optional webhook URLs
     * @param  string|null $expiresAt  Optional expiration time (ISO 8601)
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

        if (! is_array($source)) {
            return null;
        }

        return $source['scannable_code']['image']['download_uri'] ?? null;
    }

    /**
     * Download the QR code image content.
     *
     * @param Response $charge The charge response
     * @return string|null The QR code image content (PNG)
     */
    public function getQrCodeContent(Response $charge): ?string
    {
        $url = $this->getQrCodeUrl($charge);

        if ($url === null) {
            return null;
        }

        $content = @file_get_contents($url);

        return $content !== false ? $content : null;
    }

    /**
     * Get the QR code as base64-encoded string.
     *
     * @param Response $charge The charge response
     * @return string|null The base64-encoded QR code image
     */
    public function getQrCodeBase64(Response $charge): ?string
    {
        $content = $this->getQrCodeContent($charge);

        if ($content === null) {
            return null;
        }

        return base64_encode($content);
    }

    /**
     * Get the QR code as a data URI for HTML img src.
     *
     * @param Response $charge The charge response
     * @return string|null The data URI string
     */
    public function getQrCodeDataUri(Response $charge): ?string
    {
        $base64 = $this->getQrCodeBase64($charge);

        if ($base64 === null) {
            return null;
        }

        return "data:image/png;base64,{$base64}";
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
     * Get a human-readable failure message in Thai.
     */
    public function getFailureMessage(Response $charge): ?string
    {
        $code = $this->getFailureCode($charge);

        return match ($code) {
            'failed_processing' => 'ระบบทำรายการไม่สำเร็จ',
            'insufficient_balance' => 'วงเงินคงเหลือไม่เพียงพอ',
            'payment_cancelled' => 'ผู้ซื้อยกเลิกการชำระเงิน',
            default => $charge->get('failure_message'),
        };
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
