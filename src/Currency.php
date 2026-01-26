<?php

declare(strict_types=1);

namespace Omise;

/**
 * Currency constants and utilities for Omise SDK.
 *
 * Multi-currency support allows charging in various currencies while
 * settling in your account's funding currency.
 *
 * @see https://docs.omise.co/multi-currency
 */
class Currency
{
    /**
     * Supported currencies.
     */
    public const string THB = 'THB'; // Thai Baht
    public const string USD = 'USD'; // US Dollar
    public const string EUR = 'EUR'; // Euro
    public const string GBP = 'GBP'; // British Pound
    public const string JPY = 'JPY'; // Japanese Yen
    public const string SGD = 'SGD'; // Singapore Dollar
    public const string MYR = 'MYR'; // Malaysian Ringgit
    public const string AUD = 'AUD'; // Australian Dollar
    public const string CAD = 'CAD'; // Canadian Dollar
    public const string CHF = 'CHF'; // Swiss Franc
    public const string CNY = 'CNY'; // Chinese Yuan
    public const string DKK = 'DKK'; // Danish Krone
    public const string HKD = 'HKD'; // Hong Kong Dollar

    /**
     * All supported currencies for multi-currency accounts.
     *
     * @var string[]
     */
    public const array SUPPORTED = [
        self::THB,
        self::USD,
        self::EUR,
        self::GBP,
        self::JPY,
        self::SGD,
        self::MYR,
        self::AUD,
        self::CAD,
        self::CHF,
        self::CNY,
        self::DKK,
        self::HKD,
    ];

    /**
     * Zero-decimal currencies (no subunit).
     * These currencies don't have cents/satang, so amount is in main unit.
     *
     * @var string[]
     */
    public const array ZERO_DECIMAL = [
        self::JPY,
    ];

    /**
     * Smallest unit names per currency.
     *
     * @var array<string, string>
     */
    private const array SUBUNIT_NAMES = [
        self::THB => 'satang',
        self::USD => 'cents',
        self::EUR => 'cents',
        self::GBP => 'pence',
        self::JPY => 'yen',
        self::SGD => 'cents',
        self::MYR => 'sen',
        self::AUD => 'cents',
        self::CAD => 'cents',
        self::CHF => 'rappen',
        self::CNY => 'fen',
        self::DKK => 'ore',
        self::HKD => 'cents',
    ];

    /**
     * Currency symbols.
     *
     * @var array<string, string>
     */
    private const array SYMBOLS = [
        self::THB => '฿',
        self::USD => '$',
        self::EUR => '€',
        self::GBP => '£',
        self::JPY => '¥',
        self::SGD => 'S$',
        self::MYR => 'RM',
        self::AUD => 'A$',
        self::CAD => 'C$',
        self::CHF => 'CHF',
        self::CNY => '¥',
        self::DKK => 'kr',
        self::HKD => 'HK$',
    ];

    /**
     * Transaction limits per currency (in smallest unit).
     *
     * @var array<string, array{min: int, max: int}>
     */
    private const array LIMITS = [
        self::THB => ['min' => 2000, 'max' => 15000000],      // 20 - 150,000 THB
        self::SGD => ['min' => 100, 'max' => 2000000],        // 1 - 20,000 SGD
        self::MYR => ['min' => 100, 'max' => 3000000],        // 1 - 30,000 MYR
        self::JPY => ['min' => 100, 'max' => 6000000],        // 100 - 6,000,000 JPY
        self::USD => ['min' => 100, 'max' => 5000000],        // 1 - 50,000 USD
        self::EUR => ['min' => 100, 'max' => 5000000],        // 1 - 50,000 EUR
        self::GBP => ['min' => 100, 'max' => 5000000],        // 1 - 50,000 GBP
        self::AUD => ['min' => 100, 'max' => 5000000],        // 1 - 50,000 AUD
        self::CAD => ['min' => 100, 'max' => 5000000],        // 1 - 50,000 CAD
        self::CHF => ['min' => 100, 'max' => 5000000],        // 1 - 50,000 CHF
        self::CNY => ['min' => 100, 'max' => 50000000],       // 1 - 500,000 CNY
        self::DKK => ['min' => 100, 'max' => 50000000],       // 1 - 500,000 DKK
        self::HKD => ['min' => 100, 'max' => 50000000],       // 1 - 500,000 HKD
    ];

    /**
     * Check if a currency is supported.
     */
    public static function isSupported(string $currency): bool
    {
        return in_array(strtoupper($currency), self::SUPPORTED, true);
    }

    /**
     * Check if a currency is zero-decimal (no subunit).
     */
    public static function isZeroDecimal(string $currency): bool
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true);
    }

    /**
     * Get the subunit multiplier for a currency.
     * Returns 1 for zero-decimal currencies, 100 for others.
     */
    public static function getSubunitMultiplier(string $currency): int
    {
        return self::isZeroDecimal($currency) ? 1 : 100;
    }

    /**
     * Convert main unit amount to smallest unit (e.g., THB to satang).
     *
     * @param float $amount Amount in main unit (e.g., 100.50 THB)
     * @param string $currency Currency code
     * @return int Amount in smallest unit (e.g., 10050 satang)
     */
    public static function toSmallestUnit(float $amount, string $currency): int
    {
        $multiplier = self::getSubunitMultiplier($currency);

        return (int) round($amount * $multiplier);
    }

    /**
     * Convert smallest unit amount to main unit (e.g., satang to THB).
     *
     * @param int $amount Amount in smallest unit (e.g., 10050 satang)
     * @param string $currency Currency code
     * @return float Amount in main unit (e.g., 100.50 THB)
     */
    public static function toMainUnit(int $amount, string $currency): float
    {
        $multiplier = self::getSubunitMultiplier($currency);

        return $amount / $multiplier;
    }

    /**
     * Get the name of the smallest unit for a currency.
     */
    public static function getSubunitName(string $currency): string
    {
        $currency = strtoupper($currency);

        return self::SUBUNIT_NAMES[$currency] ?? 'subunits';
    }

    /**
     * Get the currency symbol.
     */
    public static function getSymbol(string $currency): string
    {
        $currency = strtoupper($currency);

        return self::SYMBOLS[$currency] ?? $currency;
    }

    /**
     * Format amount with currency symbol.
     *
     * @param int $amountInSmallestUnit Amount in smallest unit
     * @param string $currency Currency code
     * @param bool $symbolFirst Whether to put symbol before amount
     */
    public static function format(int $amountInSmallestUnit, string $currency, bool $symbolFirst = true): string
    {
        $currency = strtoupper($currency);
        $mainAmount = self::toMainUnit($amountInSmallestUnit, $currency);
        $symbol = self::getSymbol($currency);

        $decimals = self::isZeroDecimal($currency) ? 0 : 2;
        $formatted = number_format($mainAmount, $decimals);

        return $symbolFirst
            ? "{$symbol}{$formatted}"
            : "{$formatted} {$symbol}";
    }

    /**
     * Get minimum amount in smallest unit for a currency.
     */
    public static function getMinimumAmount(string $currency): int
    {
        $currency = strtoupper($currency);

        return self::LIMITS[$currency]['min'] ?? 100;
    }

    /**
     * Get maximum amount in smallest unit for a currency.
     */
    public static function getMaximumAmount(string $currency): int
    {
        $currency = strtoupper($currency);

        return self::LIMITS[$currency]['max'] ?? PHP_INT_MAX;
    }

    /**
     * Validate that an amount is within allowed limits.
     *
     * @param int $amountInSmallestUnit Amount in smallest unit
     * @param string $currency Currency code
     */
    public static function validateAmount(int $amountInSmallestUnit, string $currency): bool
    {
        $min = self::getMinimumAmount($currency);
        $max = self::getMaximumAmount($currency);

        return $amountInSmallestUnit >= $min && $amountInSmallestUnit <= $max;
    }

    /**
     * Get minimum amount in main unit for a currency.
     */
    public static function getMinimumInMainUnit(string $currency): float
    {
        return self::toMainUnit(self::getMinimumAmount($currency), $currency);
    }

    /**
     * Get maximum amount in main unit for a currency.
     */
    public static function getMaximumInMainUnit(string $currency): float
    {
        return self::toMainUnit(self::getMaximumAmount($currency), $currency);
    }

    /**
     * Get all supported currencies.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return self::SUPPORTED;
    }
}
