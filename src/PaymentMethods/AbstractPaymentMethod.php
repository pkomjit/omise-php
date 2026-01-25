<?php

declare(strict_types=1);

namespace Omise\PaymentMethods;

use Omise\Api\Charge;
use Omise\Api\Source;
use Omise\Http\Response;

/**
 * Base class for payment method implementations.
 */
abstract class AbstractPaymentMethod implements PaymentMethodInterface
{
    protected Charge $chargeApi;
    protected Source $sourceApi;

    /**
     * Payment method type identifier.
     */
    protected string $type;

    /**
     * Display name.
     */
    protected string $name;

    /**
     * Payment flow type.
     */
    protected string $flow = Source::FLOW_REDIRECT;

    /**
     * Supported currencies.
     *
     * @var string[]
     */
    protected array $supportedCurrencies = ['THB'];

    /**
     * Minimum amounts per currency (in smallest unit).
     *
     * @var array<string, int>
     */
    protected array $minimumAmounts = [];

    /**
     * Maximum amounts per currency (in smallest unit).
     *
     * @var array<string, int>
     */
    protected array $maximumAmounts = [];

    /**
     * Required additional parameters.
     *
     * @var string[]
     */
    protected array $requiredParameters = [];

    public function __construct(Charge $chargeApi, Source $sourceApi)
    {
        $this->chargeApi = $chargeApi;
        $this->sourceApi = $sourceApi;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSupportedCurrencies(): array
    {
        return $this->supportedCurrencies;
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->supportedCurrencies, true);
    }

    public function getMinimumAmount(string $currency): int
    {
        $currency = strtoupper($currency);

        return $this->minimumAmounts[$currency] ?? 0;
    }

    public function getMaximumAmount(string $currency): int
    {
        $currency = strtoupper($currency);

        return $this->maximumAmounts[$currency] ?? PHP_INT_MAX;
    }

    public function validateAmount(int $amount, string $currency): bool
    {
        $min = $this->getMinimumAmount($currency);
        $max = $this->getMaximumAmount($currency);

        return $amount >= $min && $amount <= $max;
    }

    public function getFlow(): string
    {
        return $this->flow;
    }

    public function getRequiredParameters(): array
    {
        return $this->requiredParameters;
    }

    /**
     * Validate that all required parameters are present.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateRequiredParameters(array $options): void
    {
        foreach ($this->requiredParameters as $param) {
            if (! isset($options[$param]) || $options[$param] === '') {
                throw new \InvalidArgumentException("Missing required parameter: {$param}");
            }
        }
    }

    /**
     * Create a source for this payment method.
     */
    protected function createSource(int $amount, string $currency, array $additionalParams = []): Response
    {
        $params = array_merge([
            'type' => $this->type,
            'amount' => $amount,
            'currency' => $currency,
        ], $additionalParams);

        return $this->sourceApi->create($params);
    }

    /**
     * Create a charge from a source.
     */
    protected function createChargeFromSource(
        Response $source,
        int $amount,
        string $currency,
        array $options = []
    ): Response {
        $chargeParams = array_merge([
            'amount' => $amount,
            'currency' => $currency,
            'source' => $source->getId(),
        ], $options);

        return $this->chargeApi->create($chargeParams);
    }

    /**
     * Default charge implementation using source creation.
     */
    public function charge(int $amount, string $currency, array $options = []): Response
    {
        // Validate currency
        if (! $this->supportsCurrency($currency)) {
            throw new \InvalidArgumentException(
                "Currency {$currency} is not supported by {$this->name}"
            );
        }

        // Validate amount
        if (! $this->validateAmount($amount, $currency)) {
            throw new \InvalidArgumentException(
                "Amount must be between {$this->getMinimumAmount($currency)} and {$this->getMaximumAmount($currency)}"
            );
        }

        // Validate required parameters
        $this->validateRequiredParameters($options);

        // Extract source-specific parameters
        $sourceParams = $this->extractSourceParams($options);

        // Extract charge-specific parameters
        $chargeParams = $this->extractChargeParams($options);

        // Create source
        $source = $this->createSource($amount, $currency, $sourceParams);

        // Create charge
        return $this->createChargeFromSource($source, $amount, $currency, $chargeParams);
    }

    /**
     * Extract parameters for source creation.
     * Override in subclasses to customize.
     */
    protected function extractSourceParams(array $options): array
    {
        return [];
    }

    /**
     * Extract parameters for charge creation.
     */
    protected function extractChargeParams(array $options): array
    {
        $chargeKeys = ['return_uri', 'webhook_endpoints', 'description', 'metadata', 'expires_at'];
        $params = [];

        foreach ($chargeKeys as $key) {
            if (isset($options[$key])) {
                $params[$key] = $options[$key];
            }
        }

        return $params;
    }
}
