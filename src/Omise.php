<?php

declare(strict_types=1);

namespace Omise;

use Omise\Api\Account;
use Omise\Api\Balance;
use Omise\Api\Capability;
use Omise\Api\Card;
use Omise\Api\Chain;
use Omise\Api\Charge;
use Omise\Api\Customer;
use Omise\Api\Dispute;
use Omise\Api\Document;
use Omise\Api\Event;
use Omise\Api\Forex;
use Omise\Api\Link;
use Omise\Api\LinkedAccount;
use Omise\Api\Occurrence;
use Omise\Api\Receipt;
use Omise\Api\Recipient;
use Omise\Api\Refund;
use Omise\Api\Schedule;
use Omise\Api\Search;
use Omise\Api\Source;
use Omise\Api\Token;
use Omise\Api\Transaction;
use Omise\Api\Transfer;
use Omise\Exceptions\ApiException;
use Omise\Exceptions\ConfigurationException;
use Omise\Http\HttpClient;
use Omise\Http\Response;
use Omise\PaymentMethods\CreditCard;
use Omise\PaymentMethods\DirectDebit;
use Omise\PaymentMethods\MobileBanking;
use Omise\PaymentMethods\PromptPay;
use Omise\PaymentMethods\RabbitLinePay;
use Omise\PaymentMethods\ShopeepayJumpApp;
use Omise\PaymentMethods\ShopeepayQR;
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
    private ?Account $accountApi = null;
    private ?Balance $balanceApi = null;
    private ?Capability $capabilityApi = null;
    private ?Card $cardApi = null;
    private ?Chain $chainApi = null;
    private ?Charge $chargeApi = null;
    private ?Customer $customerApi = null;
    private ?Dispute $disputeApi = null;
    private ?Document $documentApi = null;
    private ?Event $eventApi = null;
    private ?Forex $forexApi = null;
    private ?Link $linkApi = null;
    private ?LinkedAccount $linkedAccountApi = null;
    private ?Occurrence $occurrenceApi = null;
    private ?Receipt $receiptApi = null;
    private ?Recipient $recipientApi = null;
    private ?Refund $refundApi = null;
    private ?Schedule $scheduleApi = null;
    private ?Search $searchApi = null;
    private ?Source $sourceApi = null;
    private ?Token $tokenApi = null;
    private ?Transaction $transactionApi = null;
    private ?Transfer $transferApi = null;

    // Payment method instances (lazy-loaded)
    private ?PromptPay $promptPay = null;
    private ?RabbitLinePay $rabbitLinePay = null;
    private ?CreditCard $creditCard = null;
    private ?DirectDebit $directDebit = null;
    private ?TruemoneyQR $truemoneyQR = null;
    private ?TruemoneyJumpApp $truemoneyJumpApp = null;
    private ?MobileBanking $mobileBanking = null;
    private ?ShopeepayQR $shopeepayQR = null;
    private ?ShopeepayJumpApp $shopeepayJumpApp = null;

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
     *   - default_currency (optional): Default charge currency (default: THB)
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
            'default_currency' => getenv('OMISE_DEFAULT_CURRENCY') ?: 'THB',
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

    /**
     * Get the Account API.
     */
    public function account(): Account
    {
        if ($this->accountApi === null) {
            $this->accountApi = new Account($this->httpClient);
        }

        return $this->accountApi;
    }

    /**
     * Get the Balance API.
     */
    public function balance(): Balance
    {
        if ($this->balanceApi === null) {
            $this->balanceApi = new Balance($this->httpClient);
        }

        return $this->balanceApi;
    }

    /**
     * Get the Capability API.
     */
    public function capability(): Capability
    {
        if ($this->capabilityApi === null) {
            $this->capabilityApi = new Capability($this->httpClient);
        }

        return $this->capabilityApi;
    }

    /**
     * Get the Card API.
     */
    public function cards(): Card
    {
        if ($this->cardApi === null) {
            $this->cardApi = new Card($this->httpClient);
        }

        return $this->cardApi;
    }

    /**
     * Get the Chain API.
     */
    public function chains(): Chain
    {
        if ($this->chainApi === null) {
            $this->chainApi = new Chain($this->httpClient);
        }

        return $this->chainApi;
    }

    /**
     * Get the Dispute API.
     */
    public function disputes(): Dispute
    {
        if ($this->disputeApi === null) {
            $this->disputeApi = new Dispute($this->httpClient);
        }

        return $this->disputeApi;
    }

    /**
     * Get the Document API.
     */
    public function documents(): Document
    {
        if ($this->documentApi === null) {
            $this->documentApi = new Document($this->httpClient);
        }

        return $this->documentApi;
    }

    /**
     * Get the Forex API.
     */
    public function forex(): Forex
    {
        if ($this->forexApi === null) {
            $this->forexApi = new Forex($this->httpClient);
        }

        return $this->forexApi;
    }

    /**
     * Get the Link API.
     */
    public function links(): Link
    {
        if ($this->linkApi === null) {
            $this->linkApi = new Link($this->httpClient);
        }

        return $this->linkApi;
    }

    /**
     * Get the Occurrence API.
     */
    public function occurrences(): Occurrence
    {
        if ($this->occurrenceApi === null) {
            $this->occurrenceApi = new Occurrence($this->httpClient);
        }

        return $this->occurrenceApi;
    }

    /**
     * Get the Receipt API.
     */
    public function receipts(): Receipt
    {
        if ($this->receiptApi === null) {
            $this->receiptApi = new Receipt($this->httpClient);
        }

        return $this->receiptApi;
    }

    /**
     * Get the Recipient API.
     */
    public function recipients(): Recipient
    {
        if ($this->recipientApi === null) {
            $this->recipientApi = new Recipient($this->httpClient);
        }

        return $this->recipientApi;
    }

    /**
     * Get the Refund API.
     */
    public function refunds(): Refund
    {
        if ($this->refundApi === null) {
            $this->refundApi = new Refund($this->httpClient);
        }

        return $this->refundApi;
    }

    /**
     * Get the Schedule API.
     */
    public function schedules(): Schedule
    {
        if ($this->scheduleApi === null) {
            $this->scheduleApi = new Schedule($this->httpClient);
        }

        return $this->scheduleApi;
    }

    /**
     * Get the Search API.
     */
    public function search(): Search
    {
        if ($this->searchApi === null) {
            $this->searchApi = new Search($this->httpClient);
        }

        return $this->searchApi;
    }

    /**
     * Get the Transaction API.
     */
    public function transactions(): Transaction
    {
        if ($this->transactionApi === null) {
            $this->transactionApi = new Transaction($this->httpClient);
        }

        return $this->transactionApi;
    }

    /**
     * Get the Transfer API.
     */
    public function transfers(): Transfer
    {
        if ($this->transferApi === null) {
            $this->transferApi = new Transfer($this->httpClient);
        }

        return $this->transferApi;
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

    /**
     * Get the ShopeePay QR payment method.
     */
    public function shopeepayQR(): ShopeepayQR
    {
        if ($this->shopeepayQR === null) {
            $this->shopeepayQR = new ShopeepayQR($this->charges(), $this->sources());
        }

        return $this->shopeepayQR;
    }

    /**
     * Get the ShopeePay Jump App payment method.
     */
    public function shopeepayJumpApp(): ShopeepayJumpApp
    {
        if ($this->shopeepayJumpApp === null) {
            $this->shopeepayJumpApp = new ShopeepayJumpApp($this->charges(), $this->sources());
        }

        return $this->shopeepayJumpApp;
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
            // Always verify. Without a configured secret, handle() fails with
            // WebhookException::missingSecret() instead of accepting unsigned
            // payloads. Use WebhookHandler::withoutVerification() to opt out.
            $this->webhookHandler = WebhookHandler::withVerification(
                $this->config->getWebhookSecret() ?? ''
            );
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
     * Create a ShopeePay QR charge with a simple interface.
     *
     * @param  float $amount  Amount in main currency unit
     * @param  string $currency  Currency code (THB, SGD, MYR)
     * @param  string $returnUri  URL to redirect after payment
     * @param  array $webhookEndpoints  Optional webhook URLs
     * @return Response The charge response with authorize_uri for redirect
     * @throws ApiException
     */
    public function payWithShopeepayQR(
        float $amount,
        string $currency,
        string $returnUri,
        array $webhookEndpoints = []
    ): Response {
        return $this->shopeepayQR()->pay($amount, $currency, $returnUri, $webhookEndpoints);
    }

    /**
     * Create a ShopeePay Jump App charge with a simple interface.
     *
     * @param  float $amount  Amount in main currency unit
     * @param  string $currency  Currency code (THB, SGD, MYR)
     * @param  string $returnUri  URL to redirect after payment
     * @param  array $options  Additional options (platform_type, webhook_endpoints, etc.)
     * @return Response The charge response with authorize_uri for app redirect
     * @throws ApiException
     */
    public function payWithShopeepayJumpApp(
        float $amount,
        string $currency,
        string $returnUri,
        array $options = []
    ): Response {
        return $this->shopeepayJumpApp()->pay($amount, $currency, $returnUri, $options);
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
