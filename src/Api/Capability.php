<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Capability API for retrieving account capabilities.
 *
 * The capability object contains information about what actions your account
 * can perform, such as available payment methods, supported currencies, etc.
 *
 * @see https://docs.omise.co/capability-api
 */
class Capability extends ApiResource
{
    protected string $endpoint = 'capability';

    /**
     * Retrieve the account capabilities.
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
     * Get the list of supported payment methods.
     *
     * @return array<string, array>
     */
    public function getPaymentMethods(Response $capability): array
    {
        return $capability->get('payment_methods', []);
    }

    /**
     * Get the list of supported payment method backends (source types).
     *
     * @return string[]
     */
    public function getPaymentBackends(Response $capability): array
    {
        $methods = $this->getPaymentMethods($capability);
        $backends = [];

        foreach ($methods as $method) {
            if (isset($method['name'])) {
                $backends[] = $method['name'];
            }
        }

        return $backends;
    }

    /**
     * Check if a specific payment method is supported.
     */
    public function supportsPaymentMethod(Response $capability, string $method): bool
    {
        return in_array($method, $this->getPaymentBackends($capability), true);
    }

    /**
     * Get the country of the account.
     */
    public function getCountry(Response $capability): string
    {
        return $capability->get('country', '');
    }

    /**
     * Get the list of supported currencies.
     *
     * @return string[]
     */
    public function getCurrencies(Response $capability): array
    {
        return $capability->get('currencies', []);
    }

    /**
     * Check if a specific currency is supported.
     */
    public function supportsCurrency(Response $capability, string $currency): bool
    {
        return in_array(strtoupper($currency), $this->getCurrencies($capability), true);
    }

    /**
     * Get the list of banks for a specific payment method.
     *
     * @return array
     */
    public function getBanksForMethod(Response $capability, string $method): array
    {
        $methods = $this->getPaymentMethods($capability);

        foreach ($methods as $m) {
            if (isset($m['name']) && $m['name'] === $method) {
                return $m['banks'] ?? [];
            }
        }

        return [];
    }

    /**
     * Get the transaction limits for a payment method.
     *
     * @return array{min: int|null, max: int|null}
     */
    public function getLimitsForMethod(Response $capability, string $method): array
    {
        $methods = $this->getPaymentMethods($capability);

        foreach ($methods as $m) {
            if (isset($m['name']) && $m['name'] === $method) {
                return [
                    'min' => $m['amount']['min'] ?? null,
                    'max' => $m['amount']['max'] ?? null,
                ];
            }
        }

        return ['min' => null, 'max' => null];
    }

    /**
     * Check if card payment is supported.
     */
    public function supportsCards(Response $capability): bool
    {
        return $this->supportsPaymentMethod($capability, 'card');
    }

    /**
     * Check if installment payments are supported.
     */
    public function supportsInstallments(Response $capability): bool
    {
        $methods = $this->getPaymentBackends($capability);

        foreach ($methods as $method) {
            if (str_starts_with($method, 'installment_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all installment providers.
     *
     * @return string[]
     */
    public function getInstallmentProviders(Response $capability): array
    {
        $methods = $this->getPaymentBackends($capability);
        $providers = [];

        foreach ($methods as $method) {
            if (str_starts_with($method, 'installment_')) {
                $providers[] = $method;
            }
        }

        return $providers;
    }

    /**
     * Check if zero interest installments are enabled.
     */
    public function isZeroInterestInstallments(Response $capability): bool
    {
        return (bool) $capability->get('zero_interest_installments', false);
    }
}
