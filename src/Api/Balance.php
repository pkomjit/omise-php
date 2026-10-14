<?php

declare(strict_types=1);

namespace Omise\Api;

use DateTimeImmutable;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Balance API for retrieving the account balance.
 *
 * All amounts are in the smallest currency unit (e.g. satang for THB).
 *
 * @see https://docs.omise.co/balance-api/thailand
 */
class Balance extends ApiResource
{
    protected string $endpoint = 'balance';

    public const string OBJECT = 'balance';

    /**
     * Retrieve the current balance of the account (GET /balance).
     *
     * @throws ApiException
     */
    public function get(): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint(),
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Check that a response is a balance object.
     */
    public function isBalance(Response $balance): bool
    {
        return $balance->getObject() === self::OBJECT;
    }

    /**
     * Get the total balance after deducted fees.
     */
    public function getTotal(Response $balance): int
    {
        return (int) $balance->get('total', 0);
    }

    /**
     * Get the amount available to be transferred.
     */
    public function getTransferable(Response $balance): int
    {
        return (int) $balance->get('transferable', 0);
    }

    /**
     * Get the amount reserved.
     *
     * @see https://www.omise.co/what-is-the-reserve-balance
     */
    public function getReserve(Response $balance): int
    {
        return (int) $balance->get('reserve', 0);
    }

    /**
     * Get the amount on hold.
     */
    public function getOnHold(Response $balance): int
    {
        return (int) $balance->get('on_hold', 0);
    }

    /**
     * Get the available balance.
     *
     * @deprecated `available` is not part of the current Balance API; it is
     *             only returned by old API versions. Use getTransferable().
     */
    public function getAvailable(Response $balance): int
    {
        return (int) $balance->get('available', 0);
    }

    /**
     * Get the balance currency (three-letter ISO 4217 code).
     */
    public function getCurrency(Response $balance): string
    {
        return strtoupper((string) $balance->get('currency', 'THB'));
    }

    /**
     * Check whether this is a live (true) or test (false) balance.
     */
    public function isLiveMode(Response $balance): bool
    {
        return $balance->get('livemode', false) === true;
    }

    /**
     * Get the API path of the balance object.
     */
    public function getLocation(Response $balance): ?string
    {
        return $balance->get('location');
    }

    /**
     * Get the time the balance figures apply to, if present.
     */
    public function getAt(Response $balance): ?DateTimeImmutable
    {
        return self::parseDate($balance->get('at'));
    }

    /**
     * Get the balance creation time (UTC).
     */
    public function getCreatedAt(Response $balance): ?DateTimeImmutable
    {
        return self::parseDate($balance->get('created_at'));
    }

    /**
     * Check if the account can transfer a specific amount.
     *
     * @param  int $amount  Amount in the smallest currency unit
     */
    public function canTransfer(Response $balance, int $amount): bool
    {
        return $this->getTransferable($balance) >= $amount;
    }

    /**
     * Summarise a balance as a plain array of the documented fields.
     *
     * @return array{currency: string, total: int, transferable: int, reserve: int, on_hold: int, livemode: bool}
     */
    public function summary(Response $balance): array
    {
        return [
            'currency' => $this->getCurrency($balance),
            'total' => $this->getTotal($balance),
            'transferable' => $this->getTransferable($balance),
            'reserve' => $this->getReserve($balance),
            'on_hold' => $this->getOnHold($balance),
            'livemode' => $this->isLiveMode($balance),
        ];
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
