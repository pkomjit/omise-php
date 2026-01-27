<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Forex API for retrieving exchange rates.
 *
 * The Forex API provides exchange rates for converting between currencies.
 *
 * @see https://docs.omise.co/forex-api
 */
class Forex extends ApiResource
{
    protected string $endpoint = 'forex';

    /**
     * Retrieve exchange rate for a currency.
     *
     * @param  string $currency  Currency code (e.g., 'USD', 'EUR', 'JPY')
     * @throws ApiException
     */
    public function retrieve(string $currency): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint(strtoupper($currency)),
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Get exchange rates for multiple currencies.
     *
     * @param  array $currencies  Array of currency codes
     * @return array<string, Response>  Array of responses keyed by currency
     * @throws ApiException
     */
    public function retrieveMultiple(array $currencies): array
    {
        $results = [];

        foreach ($currencies as $currency) {
            $results[strtoupper($currency)] = $this->retrieve($currency);
        }

        return $results;
    }

    /**
     * Get the base currency.
     */
    public function getBaseCurrency(Response $forex): string
    {
        return $forex->get('base', 'THB');
    }

    /**
     * Get the quote currency.
     */
    public function getQuoteCurrency(Response $forex): ?string
    {
        return $forex->get('quote');
    }

    /**
     * Get the exchange rate.
     */
    public function getRate(Response $forex): float
    {
        return (float) $forex->get('rate', 0.0);
    }

    /**
     * Convert an amount from the quote currency to the base currency.
     *
     * @param  Response $forex  The forex response
     * @param  int|float $amount  Amount in quote currency
     * @return float  Amount in base currency
     */
    public function convertToBase(Response $forex, int|float $amount): float
    {
        $rate = $this->getRate($forex);

        if ($rate <= 0) {
            return 0.0;
        }

        return $amount * $rate;
    }

    /**
     * Convert an amount from the base currency to the quote currency.
     *
     * @param  Response $forex  The forex response
     * @param  int|float $amount  Amount in base currency
     * @return float  Amount in quote currency
     */
    public function convertFromBase(Response $forex, int|float $amount): float
    {
        $rate = $this->getRate($forex);

        if ($rate <= 0) {
            return 0.0;
        }

        return $amount / $rate;
    }
}
