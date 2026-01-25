<?php

declare(strict_types=1);

namespace Omise\Webhook;

use Closure;
use Omise\Api\Event;
use Omise\Exceptions\WebhookException;
use Omise\Http\Response;

/**
 * Handler for processing Omise webhooks.
 */
class WebhookHandler
{
    private ?SignatureVerifier $verifier;

    /**
     * Registered event handlers.
     *
     * @var array<string, array<Closure>>
     */
    private array $handlers = [];

    /**
     * Catch-all handler for unregistered events.
     */
    private ?Closure $fallbackHandler = null;

    public function __construct(?SignatureVerifier $verifier = null)
    {
        $this->verifier = $verifier;
    }

    /**
     * Create handler with signature verification.
     */
    public static function withVerification(string $secret, int $tolerance = 300): self
    {
        return new self(new SignatureVerifier($secret, $tolerance));
    }

    /**
     * Create handler without signature verification (not recommended for production).
     */
    public static function withoutVerification(): self
    {
        return new self(null);
    }

    /**
     * Register a handler for a specific event.
     *
     * @param string $eventKey The event key (e.g., 'charge.complete')
     * @param Closure $handler The handler function: fn(Response $event, array $data) => mixed
     */
    public function on(string $eventKey, Closure $handler): self
    {
        if (! isset($this->handlers[$eventKey])) {
            $this->handlers[$eventKey] = [];
        }

        $this->handlers[$eventKey][] = $handler;

        return $this;
    }

    /**
     * Register a handler for charge completion events.
     */
    public function onChargeComplete(Closure $handler): self
    {
        return $this->on(Event::CHARGE_COMPLETE, $handler);
    }

    /**
     * Register a handler for charge creation events.
     */
    public function onChargeCreate(Closure $handler): self
    {
        return $this->on(Event::CHARGE_CREATE, $handler);
    }

    /**
     * Register a handler for refund events.
     */
    public function onRefundCreate(Closure $handler): self
    {
        return $this->on(Event::REFUND_CREATE, $handler);
    }

    /**
     * Register a handler for dispute events.
     */
    public function onDisputeCreate(Closure $handler): self
    {
        return $this->on(Event::DISPUTE_CREATE, $handler);
    }

    /**
     * Register a fallback handler for unregistered events.
     */
    public function onUnhandled(Closure $handler): self
    {
        $this->fallbackHandler = $handler;

        return $this;
    }

    /**
     * Process a webhook request.
     *
     * @param string $payload The raw request body
     * @param string|null $signature The Omise-Signature header (required if verifier is set)
     * @param string|null $timestamp The Omise-Signature-Timestamp header (required if verifier is set)
     *
     * @return array Results from all executed handlers
     *
     * @throws WebhookException
     */
    public function handle(string $payload, ?string $signature = null, ?string $timestamp = null): array
    {
        // Verify signature if verifier is configured
        if ($this->verifier !== null) {
            $this->verifier->verify($payload, $signature ?? '', $timestamp ?? '');
        }

        // Parse payload
        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw WebhookException::invalidPayload();
        }

        $event = new Response($data);
        $eventKey = $event->get('key');

        // Execute registered handlers
        $results = [];

        if (isset($this->handlers[$eventKey])) {
            foreach ($this->handlers[$eventKey] as $handler) {
                $results[] = $handler($event, $event->get('data', []));
            }
        } elseif ($this->fallbackHandler !== null) {
            $results[] = ($this->fallbackHandler)($event, $event->get('data', []));
        }

        return $results;
    }

    /**
     * Parse webhook payload without verification (for testing or when verification is handled elsewhere).
     * @throws WebhookException
     */
    public function parsePayload(string $payload): Response
    {
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw WebhookException::invalidPayload();
        }

        return new Response($data);
    }

    /**
     * Check if an event is in live mode.
     */
    public function isLiveMode(Response $event): bool
    {
        return $event->get('livemode', false) === true;
    }

    /**
     * Get the charge ID from a charge event.
     */
    public function getChargeId(Response $event): ?string
    {
        $data = $event->get('data', []);

        return $data['id'] ?? null;
    }

    /**
     * Get the charge status from a charge event.
     */
    public function getChargeStatus(Response $event): ?string
    {
        $data = $event->get('data', []);

        return $data['status'] ?? null;
    }

    /**
     * Check if the webhook represents a successful payment.
     */
    public function isSuccessfulPayment(Response $event): bool
    {
        return $event->get('key') === Event::CHARGE_COMPLETE
            && $this->getChargeStatus($event) === 'successful';
    }

    /**
     * Check if the webhook represents a failed payment.
     */
    public function isFailedPayment(Response $event): bool
    {
        return $event->get('key') === Event::CHARGE_COMPLETE
            && $this->getChargeStatus($event) === 'failed';
    }

    /**
     * Get the failure code from a failed charge event.
     */
    public function getFailureCode(Response $event): ?string
    {
        $data = $event->get('data', []);

        return $data['failure_code'] ?? null;
    }

    /**
     * Get the failure message from a failed charge event.
     */
    public function getFailureMessage(Response $event): ?string
    {
        $data = $event->get('data', []);

        return $data['failure_message'] ?? null;
    }

    /**
     * Get the source type from a charge event.
     */
    public function getSourceType(Response $event): ?string
    {
        $data = $event->get('data', []);
        $source = $data['source'] ?? null;

        if (is_array($source)) {
            return $source['type'] ?? null;
        }

        return null;
    }

    /**
     * Check if verifier is configured.
     */
    public function hasVerifier(): bool
    {
        return $this->verifier !== null;
    }

    /**
     * Get registered event keys.
     */
    public function getRegisteredEvents(): array
    {
        return array_keys($this->handlers);
    }
}
