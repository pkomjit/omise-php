<?php

declare(strict_types=1);

namespace Omise\Api;

use DateMalformedStringException;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Card API for managing customer cards.
 *
 * Cards are stored with customers and can be used for recurring charges.
 * This API allows you to retrieve, update, list, and delete cards for a customer.
 *
 * @see https://docs.omise.co/cards-api
 */
class Card extends ApiResource
{
    protected string $endpoint = 'customers';

    /**
     * Card brand constants.
     */
    public const string BRAND_VISA = 'Visa';
    public const string BRAND_MASTERCARD = 'MasterCard';
    public const string BRAND_JCB = 'JCB';
    public const string BRAND_AMEX = 'American Express';
    public const string BRAND_DISCOVER = 'Discover';
    public const string BRAND_DINERS = 'Diners Club';
    public const string BRAND_UNIONPAY = 'UnionPay';

    /**
     * Retrieve a card for a customer.
     *
     * @param  string $customerId  Customer ID
     * @param  string $cardId  Card ID
     * @throws ApiException
     */
    public function retrieveForCustomer(string $customerId, string $cardId): Response
    {
        $data = $this->client->get(
            "/customers/{$customerId}/cards/{$cardId}",
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * List all cards for a customer.
     *
     * @param  string $customerId  Customer ID
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function listForCustomer(string $customerId, array $params = []): Response
    {
        $data = $this->client->get(
            "/customers/{$customerId}/cards",
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Update a card for a customer.
     *
     * @param  string $customerId  Customer ID
     * @param  string $cardId  Card ID
     * @param  array $params  Parameters to update:
     *   - name: Cardholder name
     *   - expiration_month: Expiration month (1-12)
     *   - expiration_year: Expiration year (4 digits)
     *   - postal_code: Billing postal code
     *   - city: Billing city
     * @throws ApiException
     */
    public function update(string $customerId, string $cardId, array $params): Response
    {
        $data = $this->client->patch(
            "/customers/{$customerId}/cards/{$cardId}",
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Delete a card from a customer.
     *
     * @param  string $customerId  Customer ID
     * @param  string $cardId  Card ID
     * @throws ApiException
     */
    public function destroy(string $customerId, string $cardId): Response
    {
        $data = $this->client->delete(
            "/customers/{$customerId}/cards/{$cardId}",
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Get the card brand.
     */
    public function getBrand(Response $card): ?string
    {
        return $card->get('brand');
    }

    /**
     * Get the last 4 digits of the card.
     */
    public function getLastDigits(Response $card): ?string
    {
        return $card->get('last_digits');
    }

    /**
     * Get the first 6 digits (BIN) of the card.
     */
    public function getFirstDigits(Response $card): ?string
    {
        return $card->get('first_digits');
    }

    /**
     * Get the cardholder name.
     */
    public function getName(Response $card): ?string
    {
        return $card->get('name');
    }

    /**
     * Get the expiration month.
     */
    public function getExpirationMonth(Response $card): ?int
    {
        $month = $card->get('expiration_month');

        return $month !== null ? (int) $month : null;
    }

    /**
     * Get the expiration year.
     */
    public function getExpirationYear(Response $card): ?int
    {
        $year = $card->get('expiration_year');

        return $year !== null ? (int) $year : null;
    }

    /**
     * Check if the card is expired.
     * @throws DateMalformedStringException
     */
    public function isExpired(Response $card): bool
    {
        $month = $this->getExpirationMonth($card);
        $year = $this->getExpirationYear($card);

        if ($month === null || $year === null) {
            return false;
        }

        $now = new \DateTime();
        $expiry = new \DateTime();
        $expiry->setDate($year, $month, 1);
        $expiry->modify('last day of this month 23:59:59');

        return $now > $expiry;
    }

    /**
     * Get the country of issue.
     */
    public function getCountry(Response $card): ?string
    {
        return $card->get('country');
    }

    /**
     * Get the city.
     */
    public function getCity(Response $card): ?string
    {
        return $card->get('city');
    }

    /**
     * Get the postal code.
     */
    public function getPostalCode(Response $card): ?string
    {
        return $card->get('postal_code');
    }

    /**
     * Get the card financing type (credit, debit, prepaid).
     */
    public function getFinancing(Response $card): ?string
    {
        return $card->get('financing');
    }

    /**
     * Check if the card supports security code verification.
     */
    public function supportsSecurityCodeCheck(Response $card): bool
    {
        return (bool) $card->get('security_code_check', false);
    }

    /**
     * Check if the card is a Visa card.
     */
    public function isVisa(Response $card): bool
    {
        return $this->getBrand($card) === self::BRAND_VISA;
    }

    /**
     * Check if the card is a MasterCard.
     */
    public function isMastercard(Response $card): bool
    {
        return $this->getBrand($card) === self::BRAND_MASTERCARD;
    }

    /**
     * Check if the card is a JCB card.
     */
    public function isJCB(Response $card): bool
    {
        return $this->getBrand($card) === self::BRAND_JCB;
    }

    /**
     * Check if the card is an American Express card.
     */
    public function isAmex(Response $card): bool
    {
        return $this->getBrand($card) === self::BRAND_AMEX;
    }

    /**
     * Format the expiration date as MM/YY.
     */
    public function formatExpiration(Response $card): ?string
    {
        $month = $this->getExpirationMonth($card);
        $year = $this->getExpirationYear($card);

        if ($month === null || $year === null) {
            return null;
        }

        return sprintf('%02d/%02d', $month, $year % 100);
    }

    /**
     * Get a masked card number display string (e.g., "**** **** **** 4242").
     */
    public function getMaskedNumber(Response $card): ?string
    {
        $lastDigits = $this->getLastDigits($card);

        if ($lastDigits === null) {
            return null;
        }

        return "**** **** **** {$lastDigits}";
    }
}
