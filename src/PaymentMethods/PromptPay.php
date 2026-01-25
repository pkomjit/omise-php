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
    public const string FAILURE_PROCESSING = 'failed_processing';
    public const string FAILURE_INSUFFICIENT_BALANCE = 'insufficient_balance';
    public const string FAILURE_CANCELLED = 'payment_cancelled';

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
     * @param  Response $charge  The charge response
     * @return array{content: string, mime_type: string}|null The QR code content and MIME type
     */
    public function getQrCodeContent(Response $charge): ?array
    {
        $url = $this->getQrCodeUrl($charge);

        if ($url === null) {
            return null;
        }

        $content = @file_get_contents($url);

        if ($content === false) {
            return null;
        }

        $mimeType = $this->detectMimeType($content, $url);

        return [
            'content' => $content,
            'mime_type' => $mimeType,
        ];
    }

    /**
     * Detect MIME type from content or URL.
     */
    private function detectMimeType(string $content, string $url): string
    {
        // Check for SVG (starts with < and contains <svg)
        $trimmedContent = ltrim($content);
        if (str_starts_with($trimmedContent, '<') && str_contains($content, '<svg')) {
            return 'image/svg+xml';
        }

        // Check for PNG magic bytes
        if (str_starts_with($content, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }

        // Check for JPEG magic bytes
        if (str_starts_with($content, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        // Check for GIF magic bytes
        if (str_starts_with($content, 'GIF87a') || str_starts_with($content, 'GIF89a')) {
            return 'image/gif';
        }

        // Fallback: check URL extension
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        return match ($extension) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'image/png', // Default fallback
        };
    }

    /**
     * Get the QR code as base64-encoded string.
     *
     * @param  Response $charge  The charge response
     * @return array{base64: string, mime_type: string}|null The base64-encoded content and MIME type
     */
    public function getQrCodeBase64(Response $charge): ?array
    {
        $result = $this->getQrCodeContent($charge);

        if ($result === null) {
            return null;
        }

        return [
            'base64' => base64_encode($result['content']),
            'mime_type' => $result['mime_type'],
        ];
    }

    /**
     * Get the QR code as a data URI for HTML img src.
     *
     * @param  Response $charge  The charge response
     * @return string|null The data URI string (supports SVG, PNG, JPEG, GIF)
     */
    public function getQrCodeDataUri(Response $charge): ?string
    {
        $result = $this->getQrCodeBase64($charge);

        if ($result === null) {
            return null;
        }

        return "data:{$result['mime_type']};base64,{$result['base64']}";
    }

    /**
     * Get raw QR code content as string.
     *
     * @param  Response $charge  The charge response
     * @return string|null The raw content (useful for SVG)
     */
    public function getQrCodeRaw(Response $charge): ?string
    {
        $result = $this->getQrCodeContent($charge);

        return $result['content'] ?? null;
    }

    /**
     * Get the QR code MIME type.
     *
     * @param  Response $charge  The charge response
     * @return string|null The MIME type (e.g., 'image/svg+xml', 'image/png')
     */
    public function getQrCodeMimeType(Response $charge): ?string
    {
        $result = $this->getQrCodeContent($charge);

        return $result['mime_type'] ?? null;
    }

    /**
     * Check if the QR code is SVG format.
     */
    public function isQrCodeSvg(Response $charge): bool
    {
        return $this->getQrCodeMimeType($charge) === 'image/svg+xml';
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
                self::FAILURE_INSUFFICIENT_BALANCE => 'วงเงินคงเหลือไม่เพียงพอ',
                self::FAILURE_CANCELLED => 'ผู้ซื้อยกเลิกการชำระเงิน',
                default => $charge->get('failure_message'),
            };
        }

        return match ($code) {
            self::FAILURE_PROCESSING => 'Payment processing failed',
            self::FAILURE_INSUFFICIENT_BALANCE => 'Insufficient balance',
            self::FAILURE_CANCELLED => 'Payment was cancelled by customer',
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
