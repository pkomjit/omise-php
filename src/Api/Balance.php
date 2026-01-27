<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Balance API for retrieving account balance.
 *
 * @see https://docs.omise.co/balance-api
 */
class Balance extends ApiResource
{
    protected string $endpoint = 'balance';

    /**
     * Retrieve the account balance.
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
     * Get the available balance in the smallest currency unit.
     */
    public function getAvailable(Response $balance): int
    {
        return (int) $balance->get('available', 0);
    }

    /**
     * Get the total balance in the smallest currency unit.
     */
    public function getTotal(Response $balance): int
    {
        return (int) $balance->get('total', 0);
    }

    /**
     * Get the reserve balance in the smallest currency unit.
     */
    public function getReserve(Response $balance): int
    {
        return (int) $balance->get('reserve', 0);
    }

    /**
     * Get the transferable balance in the smallest currency unit.
     */
    public function getTransferable(Response $balance): int
    {
        return (int) $balance->get('transferable', 0);
    }

    /**
     * Get the balance currency.
     */
    public function getCurrency(Response $balance): string
    {
        return $balance->get('currency', 'THB');
    }

    /**
     * Check if the account can make a transfer of a specific amount.
     */
    public function canTransfer(Response $balance, int $amount): bool
    {
        return $this->getTransferable($balance) >= $amount;
    }
}
