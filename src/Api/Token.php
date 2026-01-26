<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\HttpClient;
use Omise\Http\Response;

/**
 * Token API for creating card tokens via Omise Vault.
 *
 * Tokens represent card information and can be used to create charges
 * or attach cards to customers. Tokens are single-use only.
 *
 * @see https://docs.omise.co/tokens-api
 */
class Token
{
    protected HttpClient $client;
    protected string $endpoint = 'tokens';

    public function __construct(HttpClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create a new card token.
     *
     * @param array $card Card parameters:
     *   - name (required): Cardholder name as printed on the card
     *   - number (required): Card number (no spaces or dashes)
     *   - expiration_month (required): Expiration month (1-12 or 01-12)
     *   - expiration_year (required): Expiration year (YY or YYYY)
     *   - security_code (optional but recommended): CVV/CVC code
     *   - city (optional): Billing city
     *   - country (optional): ISO 3166 two-letter country code
     *   - postal_code (optional): Billing postal code
     *   - state (optional): Billing state/province
     *   - street1 (optional): Billing street address line 1
     *   - street2 (optional): Billing street address line 2
     *   - phone_number (optional): Contact phone number
     *   - email (optional): Customer email
     *
     * @throws ApiException
     */
    public function create(array $card): Response
    {
        $this->validateCardParams($card);

        // Format parameters for Vault API (uses form encoding with card[key] format)
        $params = $this->formatCardParams($card);

        $data = $this->client->vaultPost('/' . $this->endpoint, $params);

        return new Response($data);
    }

    /**
     * Create a token with simplified parameters.
     *
     * @param string $name Cardholder name
     * @param string $number Card number
     * @param int $expirationMonth Expiration month (1-12)
     * @param int $expirationYear Expiration year (YYYY or YY)
     * @param string|null $securityCode CVV/CVC code
     * @throws ApiException
     */
    public function createFromCard(
        string $name,
        string $number,
        int $expirationMonth,
        int $expirationYear,
        ?string $securityCode = null
    ): Response {
        $card = [
            'name' => $name,
            'number' => $number,
            'expiration_month' => $expirationMonth,
            'expiration_year' => $expirationYear,
        ];

        if ($securityCode !== null) {
            $card['security_code'] = $securityCode;
        }

        return $this->create($card);
    }

    /**
     * Retrieve a token by ID.
     *
     * @throws ApiException
     */
    public function retrieve(string $tokenId): Response
    {
        $data = $this->client->vaultGet("/{$this->endpoint}/{$tokenId}");

        return new Response($data);
    }

    /**
     * Get the card ID from a token (after it's been used to create a card).
     */
    public function getCardId(Response $token): ?string
    {
        $card = $token->get('card');

        if (! is_array($card)) {
            return null;
        }

        return $card['id'] ?? null;
    }

    /**
     * Get the card brand from a token.
     */
    public function getCardBrand(Response $token): ?string
    {
        $card = $token->get('card');

        if (! is_array($card)) {
            return null;
        }

        return $card['brand'] ?? null;
    }

    /**
     * Get the last 4 digits of the card number.
     */
    public function getCardLastDigits(Response $token): ?string
    {
        $card = $token->get('card');

        if (! is_array($card)) {
            return null;
        }

        return $card['last_digits'] ?? null;
    }

    /**
     * Check if the token has been used.
     */
    public function isUsed(Response $token): bool
    {
        return $token->get('used') === true;
    }

    /**
     * Get card expiration as a formatted string (MM/YY).
     */
    public function getCardExpiration(Response $token): ?string
    {
        $card = $token->get('card');

        if (! is_array($card)) {
            return null;
        }

        $month = $card['expiration_month'] ?? null;
        $year = $card['expiration_year'] ?? null;

        if ($month === null || $year === null) {
            return null;
        }

        return sprintf('%02d/%02d', $month, $year % 100);
    }

    /**
     * Format card parameters for Vault API.
     */
    private function formatCardParams(array $card): array
    {
        $params = [];

        foreach ($card as $key => $value) {
            $params["card[{$key}]"] = (string) $value;
        }

        return $params;
    }

    /**
     * Validate required card parameters.
     *
     * @throws \InvalidArgumentException
     */
    private function validateCardParams(array $card): void
    {
        if (empty($card['name'])) {
            throw new \InvalidArgumentException('card[name] is required');
        }

        if (empty($card['number'])) {
            throw new \InvalidArgumentException('card[number] is required');
        }

        if (empty($card['expiration_month'])) {
            throw new \InvalidArgumentException('card[expiration_month] is required');
        }

        if (empty($card['expiration_year'])) {
            throw new \InvalidArgumentException('card[expiration_year] is required');
        }

        // Validate card number format (basic check - numbers only)
        $number = preg_replace('/\D/', '', $card['number']);
        if (strlen($number) < 13 || strlen($number) > 19) {
            throw new \InvalidArgumentException('Invalid card number length');
        }

        // Validate expiration month
        $month = (int) $card['expiration_month'];
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Invalid expiration month');
        }

        // Validate expiration year
        $year = (int) $card['expiration_year'];
        $currentYear = (int) date('Y');
        if ($year < 100) {
            $year += 2000;
        }
        if ($year < $currentYear || $year > $currentYear + 20) {
            throw new \InvalidArgumentException('Invalid expiration year');
        }
    }
}
