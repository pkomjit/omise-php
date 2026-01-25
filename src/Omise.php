<?php

declare(strict_types=1);

namespace Omise;

use Omise\Api\Charge;
use Omise\Api\Event;
use Omise\Api\Source;
use Omise\Http\HttpClient;
use Omise\PaymentMethods\PromptPay;
use Omise\Webhook\SignatureVerifier;
use Omise\Webhook\WebhookHandler;
use Psr\Log\LoggerInterface;

/**
 * Main entry point for the Omise SDK.
 *
 * Usage:
 * ```php
 * $omise = new Omise([
 *     'public_key' => 'pkey_...',
 *     'secret_key' => 'skey_...',
 * ]);
 *
 * // Create a PromptPay charge
 * $charge = $omise->promptPay()->pay(100.00);
 * $qrCodeUrl = $omise->promptPay()->getQrCodeUrl($charge);
 * ```
 */
class Omise
{
    private Config $config;
    private HttpClient $httpClient;
    private ?LoggerInterface $logger;

    // API instances (lazy-loaded)
    private ?Charge $chargeApi = null;
    private ?Source $sourceApi = null;
    private ?Event $eventApi = null;

    // Payment method instances (lazy-loaded)
    private ?PromptPay $promptPay = null;

    // Webhook handler instance
    private ?WebhookHandler $webhookHandler = null;

    /**
     * Create a new Omise SDK instance.
     *
     * @param array $config Configuration options:
     *   - public_key (required): Your Omise public key
     *   - secret_key (required): Your Omise secret key
     *   - api_url (optional): API base URL (default: https://api.omise.co)
     *   - api_version (optional): API version (default: 2019-05-29)
     *   - mode (optional): 'live' or 'test' (default: 'live')
     *   - webhook_secret (optional): Webhook signing secret
     *   - timeout (optional): Request timeout in seconds (default: 30)
     *   - ssl_verify (optional): Verify SSL certificates (default: true)
     * @param LoggerInterface|null $logger Optional PSR-3 logger
     */
    public function __construct(array $config, ?LoggerInterface $logger = null)
    {
        $this->config = new Config($config);
        $this->logger = $logger;
        $this->httpClient = new HttpClient($this->config, $logger);
    }

    /**
     * Create an Omise instance from environment variables.
     *
     * Expected environment variables:
     * - OMISE_PUBLIC_KEY
     * - OMISE_SECRET_KEY
     * - OMISE_WEBHOOK_SECRET (optional)
     * - OMISE_MODE (optional, default: 'live')
     */
    public static function fromEnvironment(?LoggerInterface $logger = null): self
    {
        return new self([
            'public_key' => getenv('OMISE_PUBLIC_KEY') ?: '',
            'secret_key' => getenv('OMISE_SECRET_KEY') ?: '',
            'webhook_secret' => getenv('OMISE_WEBHOOK_SECRET') ?: null,
            'mode' => getenv('OMISE_MODE') ?: Config::MODE_LIVE,
        ], $logger);
    }

    // =========================================================================
    // API Access
    // =========================================================================

    /**
     * Get the Charge API.
     */
    public function charges(): Charge
    {
        if ($this->chargeApi === null) {
            $this->chargeApi = new Charge($this->httpClient);
        }

        return $this->chargeApi;
    }

    /**
     * Get the Source API.
     */
    public function sources(): Source
    {
        if ($this->sourceApi === null) {
            $this->sourceApi = new Source($this->httpClient);
        }

        return $this->sourceApi;
    }

    /**
     * Get the Event API.
     */
    public function events(): Event
    {
        if ($this->eventApi === null) {
            $this->eventApi = new Event($this->httpClient);
        }

        return $this->eventApi;
    }

    // =========================================================================
    // Payment Methods
    // =========================================================================

    /**
     * Get the PromptPay payment method.
     */
    public function promptPay(): PromptPay
    {
        if ($this->promptPay === null) {
            $this->promptPay = new PromptPay($this->charges(), $this->sources());
        }

        return $this->promptPay;
    }

    // =========================================================================
    // Webhook Handling
    // =========================================================================

    /**
     * Get the webhook handler.
     */
    public function webhooks(): WebhookHandler
    {
        if ($this->webhookHandler === null) {
            $secret = $this->config->getWebhookSecret();

            if ($secret !== null) {
                $this->webhookHandler = WebhookHandler::withVerification($secret);
            } else {
                $this->webhookHandler = WebhookHandler::withoutVerification();
            }
        }

        return $this->webhookHandler;
    }

    /**
     * Create a signature verifier.
     */
    public function createSignatureVerifier(int $tolerance = 300): SignatureVerifier
    {
        $secret = $this->config->getWebhookSecret();

        if ($secret === null) {
            throw new \RuntimeException('Webhook secret is not configured');
        }

        return new SignatureVerifier($secret, $tolerance);
    }

    // =========================================================================
    // Convenience Methods
    // =========================================================================

    /**
     * Create a PromptPay charge with a simple interface.
     *
     * @param float $amount Amount in THB
     * @param array $webhookEndpoints Optional webhook URLs
     * @return \Omise\Http\Response The charge response
     */
    public function payWithPromptPay(float $amount, array $webhookEndpoints = []): \Omise\Http\Response
    {
        return $this->promptPay()->pay($amount, $webhookEndpoints);
    }

    /**
     * Get a charge by ID.
     */
    public function getCharge(string $chargeId): \Omise\Http\Response
    {
        return $this->charges()->retrieve($chargeId);
    }

    /**
     * Get an event by ID.
     */
    public function getEvent(string $eventId): \Omise\Http\Response
    {
        return $this->events()->retrieve($eventId);
    }

    // =========================================================================
    // Configuration Access
    // =========================================================================

    /**
     * Get the configuration.
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Check if running in test mode.
     */
    public function isTestMode(): bool
    {
        return $this->config->isTestMode();
    }

    /**
     * Check if running in live mode.
     */
    public function isLiveMode(): bool
    {
        return $this->config->isLiveMode();
    }

    /**
     * Get the HTTP client.
     */
    public function getHttpClient(): HttpClient
    {
        return $this->httpClient;
    }
}
