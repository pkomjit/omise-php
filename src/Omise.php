<?php

declare(strict_types=1);

namespace Omise;

use Omise\Api\Charge;
use Omise\Api\Customer;
use Omise\Api\Event;
use Omise\Api\LinkedAccount;
use Omise\Api\Source;
use Omise\Api\Token;
use Omise\Exceptions\ApiException;
use Omise\Exceptions\ConfigurationException;
use Omise\Http\HttpClient;
use Omise\Http\Response;
use Omise\PaymentMethods\CreditCard;
use Omise\PaymentMethods\DirectDebit;
use Omise\PaymentMethods\MobileBanking;
use Omise\PaymentMethods\PromptPay;
use Omise\PaymentMethods\RabbitLinePay;
use Omise\PaymentMethods\TruemoneyJumpApp;
use Omise\PaymentMethods\TruemoneyQR;
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
    private ?Token $tokenApi = null;
    private ?Customer $customerApi = null;
    private ?LinkedAccount $linkedAccountApi = null;

    // Payment method instances (lazy-loaded)
    private ?PromptPay $promptPay = null;
    private ?RabbitLinePay $rabbitLinePay = null;
    private ?CreditCard $creditCard = null;
    private ?DirectDebit $directDebit = null;
    private ?TruemoneyQR $truemoneyQR = null;
    private ?TruemoneyJumpApp $truemoneyJumpApp = null;
    private ?MobileBanking $mobileBanking = null;

    // Webhook handler instance
    private ?WebhookHandler $webhookHandler = null;

    /**
     * Create a new Omise SDK instance.
     *
     * @param  array $config  Configuration options:
     *   - public_key (required): Your Omise public key
     *   - secret_key (required): Your Omise secret key
     *   - api_url (optional): API base URL (default: https://api.omise.co)
     *   - api_version (optional): API version (default: 2019-05-29)
     *   - mode (optional): 'live' or 'test' (default: 'live')
     *   - webhook_secret (optional): Webhook signing secret
     *   - timeout (optional): Request timeout in seconds (default: 30)
     *   - ssl_verify (optional): Verify SSL certificates (default: true)
     * @param  LoggerInterface|null $logger  Optional PSR-3 logger
     * @throws ConfigurationException
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

    /**
     * Get the Token API.
     */
    public function tokens(): Token
    {
        if ($this->tokenApi === null) {
            $this->tokenApi = new Token($this->httpClient);
        }

        return $this->tokenApi;
    }

    /**
     * Get the Customer API.
     */
    public function customers(): Customer
    {
        if ($this->customerApi === null) {
            $this->customerApi = new Customer($this->httpClient);
        }

        return $this->customerApi;
    }

    /**
     * Get the LinkedAccount API.
     */
    public function linkedAccounts(): LinkedAccount
    {
        if ($this->linkedAccountApi === null) {
            $this->linkedAccountApi = new LinkedAccount($this->httpClient);
        }

        return $this->linkedAccountApi;
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

    /**
     * Get the Rabbit LINE Pay payment method.
     */
    public function rabbitLinePay(): RabbitLinePay
    {
        if ($this->rabbitLinePay === null) {
            $this->rabbitLinePay = new RabbitLinePay($this->charges(), $this->sources());
        }

        return $this->rabbitLinePay;
    }

    /**
     * Get the Credit Card payment method.
     */
    public function creditCard(): CreditCard
    {
        if ($this->creditCard === null) {
            $this->creditCard = new CreditCard($this->charges(), $this->tokens(), $this->customers());
        }

        return $this->creditCard;
    }

    /**
     * Get the Direct Debit payment method.
     */
    public function directDebit(): DirectDebit
    {
        if ($this->directDebit === null) {
            $this->directDebit = new DirectDebit($this->charges(), $this->customers(), $this->linkedAccounts());
        }

        return $this->directDebit;
    }

    /**
     * Get the TrueMoney QR payment method.
     */
    public function truemoneyQR(): TruemoneyQR
    {
        if ($this->truemoneyQR === null) {
            $this->truemoneyQR = new TruemoneyQR($this->charges(), $this->sources());
        }

        return $this->truemoneyQR;
    }

    /**
     * Get the TrueMoney Jump App payment method.
     */
    public function truemoneyJumpApp(): TruemoneyJumpApp
    {
        if ($this->truemoneyJumpApp === null) {
            $this->truemoneyJumpApp = new TruemoneyJumpApp($this->charges(), $this->sources());
        }

        return $this->truemoneyJumpApp;
    }

    /**
     * Get the Mobile Banking payment method.
     */
    public function mobileBanking(): MobileBanking
    {
        if ($this->mobileBanking === null) {
            $this->mobileBanking = new MobileBanking($this->charges(), $this->sources());
        }

        return $this->mobileBanking;
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
     * @param  float $amount  Amount in THB
     * @param  array $webhookEndpoints  Optional webhook URLs
     * @return Response The charge response
     * @throws ApiException
     */
    public function payWithPromptPay(float $amount, array $webhookEndpoints = []): Response
    {
        return $this->promptPay()->pay($amount, $webhookEndpoints);
    }

    /**
     * Create a Rabbit LINE Pay charge with a simple interface.
     *
     * @param  float $amount  Amount in THB
     * @param  string $returnUri  URL to redirect after payment
     * @param  array $webhookEndpoints  Optional webhook URLs
     * @return Response The charge response with authorize_uri for redirect
     * @throws ApiException
     */
    public function payWithRabbitLinePay(float $amount, string $returnUri, array $webhookEndpoints = []): Response
    {
        return $this->rabbitLinePay()->pay($amount, $returnUri, $webhookEndpoints);
    }

    /**
     * Create a credit card charge with a simple interface.
     *
     * @param  string $tokenId  Card token ID
     * @param  float $amount  Amount in main currency unit (e.g., 100.00 THB)
     * @param  string $currency  Currency code (default: THB)
     * @param  string|null $returnUri  URL for 3D Secure redirect
     * @return Response The charge response
     * @throws ApiException
     */
    public function payWithCard(
        string $tokenId,
        float $amount,
        string $currency = 'THB',
        ?string $returnUri = null
    ): Response {
        return $this->creditCard()->pay($tokenId, $amount, $currency, $returnUri);
    }

    /**
     * Create a TrueMoney QR charge with a simple interface.
     *
     * @param  float $amount  Amount in THB
     * @param  array $webhookEndpoints  Optional webhook URLs
     * @return Response The charge response with QR code
     * @throws ApiException
     */
    public function payWithTruemoneyQR(float $amount, array $webhookEndpoints = []): Response
    {
        return $this->truemoneyQR()->pay($amount, $webhookEndpoints);
    }

    /**
     * Create a TrueMoney Jump App charge with a simple interface.
     *
     * @param  float $amount  Amount in THB
     * @param  string $returnUri  URL to redirect after payment
     * @param  array $webhookEndpoints  Optional webhook URLs
     * @return Response The charge response with authorize_uri for redirect
     * @throws ApiException
     */
    public function payWithTruemoneyJumpApp(float $amount, string $returnUri, array $webhookEndpoints = []): Response
    {
        return $this->truemoneyJumpApp()->pay($amount, $returnUri, $webhookEndpoints);
    }

    /**
     * Create a Mobile Banking charge with a simple interface.
     *
     * @param  string $bankType  Bank type constant (e.g., MobileBanking::BANK_KBANK)
     * @param  float $amount  Amount in main currency unit (THB or SGD)
     * @param  string $returnUri  URL to redirect after payment
     * @param  array $options  Additional options (platform_type, webhook_endpoints, etc.)
     * @return Response The charge response with authorize_uri for redirect
     * @throws ApiException
     */
    public function payWithMobileBanking(
        string $bankType,
        float $amount,
        string $returnUri,
        array $options = []
    ): Response {
        return $this->mobileBanking()->pay($bankType, $amount, $returnUri, $options);
    }

    /**
     * Get a charge by ID.
     */
    public function getCharge(string $chargeId): Response
    {
        return $this->charges()->retrieve($chargeId);
    }

    /**
     * Get an event by ID.
     */
    public function getEvent(string $eventId): Response
    {
        return $this->events()->retrieve($eventId);
    }

    /**
     * Get a customer by ID.
     * @throws ApiException
     */
    public function getCustomer(string $customerId): Response
    {
        return $this->customers()->retrieve($customerId);
    }

    /**
     * Get the default currency.
     */
    public function getDefaultCurrency(): string
    {
        return $this->config->getDefaultCurrency();
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
