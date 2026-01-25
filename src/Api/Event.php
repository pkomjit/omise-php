<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Http\Response;

/**
 * Event API for retrieving webhook events.
 *
 * Events represent webhook notifications that Omise sends to your application.
 *
 * @see https://docs.omise.co/api-events
 */
class Event extends ApiResource
{
    protected string $endpoint = 'events';

    /**
     * Event key constants.
     */
    // Charge events
    public const CHARGE_CREATE = 'charge.create';
    public const CHARGE_UPDATE = 'charge.update';
    public const CHARGE_COMPLETE = 'charge.complete';
    public const CHARGE_CAPTURE = 'charge.capture';
    public const CHARGE_EXPIRE = 'charge.expire';
    public const CHARGE_REVERSE = 'charge.reverse';

    // Refund events
    public const REFUND_CREATE = 'refund.create';

    // Customer events
    public const CUSTOMER_CREATE = 'customer.create';
    public const CUSTOMER_UPDATE = 'customer.update';
    public const CUSTOMER_DESTROY = 'customer.destroy';
    public const CUSTOMER_UPDATE_CARD = 'customer.update.card';

    // Card events
    public const CARD_UPDATE = 'card.update';
    public const CARD_DESTROY = 'card.destroy';

    // Dispute events
    public const DISPUTE_CREATE = 'dispute.create';
    public const DISPUTE_UPDATE = 'dispute.update';
    public const DISPUTE_CLOSE = 'dispute.close';
    public const DISPUTE_ACCEPT = 'dispute.accept';

    // Transfer events
    public const TRANSFER_CREATE = 'transfer.create';
    public const TRANSFER_UPDATE = 'transfer.update';
    public const TRANSFER_DESTROY = 'transfer.destroy';
    public const TRANSFER_SEND = 'transfer.send';
    public const TRANSFER_PAY = 'transfer.pay';
    public const TRANSFER_FAIL = 'transfer.fail';

    /**
     * Retrieve an event by ID.
     */
    public function retrieve(string $eventId): Response
    {
        return parent::retrieve($eventId);
    }

    /**
     * List all events with optional filters.
     *
     * @param array $params Filter parameters:
     *   - offset: Starting offset
     *   - limit: Number of records to return (max 100)
     *   - from: Start date (ISO 8601)
     *   - to: End date (ISO 8601)
     *   - order: Sort order ('chronological' or 'reverse_chronological')
     */
    public function all(array $params = []): Response
    {
        return parent::all($this->normalizePaginationParams($params));
    }

    /**
     * Get the data object from an event.
     */
    public function getData(Response $event): ?array
    {
        return $event->get('data');
    }

    /**
     * Get the event key (e.g., 'charge.complete').
     */
    public function getKey(Response $event): ?string
    {
        return $event->get('key');
    }

    /**
     * Check if the event is a charge event.
     */
    public function isChargeEvent(Response $event): bool
    {
        $key = $this->getKey($event);
        return $key !== null && str_starts_with($key, 'charge.');
    }

    /**
     * Check if the event is a refund event.
     */
    public function isRefundEvent(Response $event): bool
    {
        $key = $this->getKey($event);
        return $key !== null && str_starts_with($key, 'refund.');
    }

    /**
     * Check if the event is a customer event.
     */
    public function isCustomerEvent(Response $event): bool
    {
        $key = $this->getKey($event);
        return $key !== null && str_starts_with($key, 'customer.');
    }

    /**
     * Check if the event is a dispute event.
     */
    public function isDisputeEvent(Response $event): bool
    {
        $key = $this->getKey($event);
        return $key !== null && str_starts_with($key, 'dispute.');
    }

    /**
     * Check if the event is a transfer event.
     */
    public function isTransferEvent(Response $event): bool
    {
        $key = $this->getKey($event);
        return $key !== null && str_starts_with($key, 'transfer.');
    }

    /**
     * Check if the event indicates a successful charge completion.
     */
    public function isSuccessfulCharge(Response $event): bool
    {
        if ($this->getKey($event) !== self::CHARGE_COMPLETE) {
            return false;
        }

        $data = $this->getData($event);
        return $data !== null && ($data['status'] ?? '') === 'successful';
    }

    /**
     * Check if the event indicates a failed charge.
     */
    public function isFailedCharge(Response $event): bool
    {
        if ($this->getKey($event) !== self::CHARGE_COMPLETE) {
            return false;
        }

        $data = $this->getData($event);
        return $data !== null && ($data['status'] ?? '') === 'failed';
    }

    /**
     * Check if the event is in live mode.
     */
    public function isLiveMode(Response $event): bool
    {
        return $event->get('livemode', false) === true;
    }
}
