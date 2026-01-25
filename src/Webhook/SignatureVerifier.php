<?php

declare(strict_types=1);

namespace Omise\Webhook;

use Omise\Exceptions\WebhookException;

/**
 * Verifies webhook signatures from Omise.
 *
 * @see https://docs.omise.co/api-webhooks
 */
class SignatureVerifier
{
    /**
     * Default tolerance in seconds for timestamp validation.
     */
    private const DEFAULT_TOLERANCE = 300; // 5 minutes

    private string $secret;
    private int $tolerance;

    public function __construct(string $secret, int $tolerance = self::DEFAULT_TOLERANCE)
    {
        $this->secret = $secret;
        $this->tolerance = $tolerance;
    }

    /**
     * Verify the webhook signature.
     *
     * @param string $payload The raw request body
     * @param string $signature The Omise-Signature header value
     * @param string $timestamp The Omise-Signature-Timestamp header value
     *
     * @throws WebhookException
     */
    public function verify(string $payload, string $signature, string $timestamp): bool
    {
        if (empty($this->secret)) {
            throw WebhookException::missingSecret();
        }

        if (empty($signature)) {
            throw WebhookException::missingSignature();
        }

        if (empty($timestamp)) {
            throw WebhookException::missingTimestamp();
        }

        // Validate timestamp
        $this->validateTimestamp($timestamp);

        // Build signed payload
        $signedPayload = "{$timestamp}.{$payload}";

        // Decode the base64-encoded secret
        $decodedSecret = base64_decode($this->secret, true);
        if ($decodedSecret === false) {
            // If decoding fails, use the secret as-is
            $decodedSecret = $this->secret;
        }

        // Compute expected signature
        $expectedSignature = hash_hmac('sha256', $signedPayload, $decodedSecret);

        // Compare signatures (handle multiple signatures during rotation)
        $signatures = explode(',', $signature);

        foreach ($signatures as $sig) {
            $sig = trim($sig);
            if ($this->secureCompare($sig, $expectedSignature)) {
                return true;
            }
        }

        throw WebhookException::invalidSignature();
    }

    /**
     * Validate that the timestamp is within tolerance.
     *
     * @throws WebhookException
     */
    private function validateTimestamp(string $timestamp): void
    {
        $webhookTime = (int) $timestamp;
        $currentTime = time();

        if (abs($currentTime - $webhookTime) > $this->tolerance) {
            throw WebhookException::expiredTimestamp($this->tolerance);
        }
    }

    /**
     * Constant-time string comparison to prevent timing attacks.
     */
    private function secureCompare(string $a, string $b): bool
    {
        if (strlen($a) !== strlen($b)) {
            return false;
        }

        return hash_equals($a, $b);
    }

    /**
     * Create a verifier from configuration.
     */
    public static function create(string $secret, int $tolerance = self::DEFAULT_TOLERANCE): self
    {
        return new self($secret, $tolerance);
    }

    /**
     * Get the current tolerance setting.
     */
    public function getTolerance(): int
    {
        return $this->tolerance;
    }

    /**
     * Set a new tolerance value.
     */
    public function setTolerance(int $tolerance): self
    {
        $this->tolerance = $tolerance;
        return $this;
    }
}
