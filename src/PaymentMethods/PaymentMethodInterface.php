<?php

declare(strict_types=1);

namespace Omise\PaymentMethods;

use Omise\Http\Response;

/**
 * Interface for payment method implementations.
 */
interface PaymentMethodInterface
{
    /**
     * Get the payment method type identifier.
     */
    public function getType(): string;

    /**
     * Get the display name of the payment method.
     */
    public function getName(): string;

    /**
     * Get supported currencies for this payment method.
     *
     * @return string[]
     */
    public function getSupportedCurrencies(): array;

    /**
     * Check if the payment method supports a specific currency.
     */
    public function supportsCurrency(string $currency): bool;

    /**
     * Get the minimum amount for this payment method (in smallest currency unit).
     */
    public function getMinimumAmount(string $currency): int;

    /**
     * Get the maximum amount for this payment method (in smallest currency unit).
     */
    public function getMaximumAmount(string $currency): int;

    /**
     * Validate the amount is within allowed range.
     */
    public function validateAmount(int $amount, string $currency): bool;

    /**
     * Get the payment flow type.
     */
    public function getFlow(): string;

    /**
     * Create a charge using this payment method.
     */
    public function charge(int $amount, string $currency, array $options = []): Response;

    /**
     * Get additional parameters required for this payment method.
     *
     * @return string[]
     */
    public function getRequiredParameters(): array;
}
