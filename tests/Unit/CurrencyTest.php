<?php

use Omise\Currency;

describe('Currency', function () {
    describe('currency constants', function () {
        it('has correct currency codes', function () {
            expect(Currency::THB)->toBe('THB')
                ->and(Currency::USD)->toBe('USD')
                ->and(Currency::EUR)->toBe('EUR')
                ->and(Currency::JPY)->toBe('JPY')
                ->and(Currency::SGD)->toBe('SGD');
        });

        it('has list of all supported currencies', function () {
            $supported = Currency::all();

            expect($supported)->toContain('THB')
                ->and($supported)->toContain('USD')
                ->and($supported)->toContain('EUR')
                ->and($supported)->toContain('JPY')
                ->and($supported)->toContain('SGD')
                ->and($supported)->toContain('MYR')
                ->and($supported)->toHaveCount(13);
        });
    });

    describe('currency validation', function () {
        it('validates supported currencies', function () {
            expect(Currency::isSupported('THB'))->toBeTrue()
                ->and(Currency::isSupported('USD'))->toBeTrue()
                ->and(Currency::isSupported('EUR'))->toBeTrue()
                ->and(Currency::isSupported('thb'))->toBeTrue() // case insensitive
                ->and(Currency::isSupported('XXX'))->toBeFalse();
        });
    });

    describe('zero-decimal currencies', function () {
        it('identifies JPY as zero-decimal', function () {
            expect(Currency::isZeroDecimal('JPY'))->toBeTrue()
                ->and(Currency::isZeroDecimal('jpy'))->toBeTrue();
        });

        it('identifies THB as not zero-decimal', function () {
            expect(Currency::isZeroDecimal('THB'))->toBeFalse()
                ->and(Currency::isZeroDecimal('USD'))->toBeFalse();
        });

        it('returns correct subunit multiplier', function () {
            expect(Currency::getSubunitMultiplier('THB'))->toBe(100)
                ->and(Currency::getSubunitMultiplier('USD'))->toBe(100)
                ->and(Currency::getSubunitMultiplier('JPY'))->toBe(1);
        });
    });

    describe('amount conversion', function () {
        it('converts THB to satang', function () {
            expect(Currency::toSmallestUnit(100.00, 'THB'))->toBe(10000)
                ->and(Currency::toSmallestUnit(20.50, 'THB'))->toBe(2050)
                ->and(Currency::toSmallestUnit(0.01, 'THB'))->toBe(1);
        });

        it('converts satang to THB', function () {
            expect(Currency::toMainUnit(10000, 'THB'))->toBe(100.00)
                ->and(Currency::toMainUnit(2050, 'THB'))->toBe(20.50)
                ->and(Currency::toMainUnit(1, 'THB'))->toBe(0.01);
        });

        it('converts USD to cents', function () {
            expect(Currency::toSmallestUnit(100.00, 'USD'))->toBe(10000)
                ->and(Currency::toSmallestUnit(99.99, 'USD'))->toBe(9999);
        });

        it('handles JPY (zero-decimal) correctly', function () {
            expect(Currency::toSmallestUnit(1000, 'JPY'))->toBe(1000)
                ->and(Currency::toMainUnit(1000, 'JPY'))->toBe(1000.0);
        });
    });

    describe('currency symbols', function () {
        it('returns correct symbols', function () {
            expect(Currency::getSymbol('THB'))->toBe('฿')
                ->and(Currency::getSymbol('USD'))->toBe('$')
                ->and(Currency::getSymbol('EUR'))->toBe('€')
                ->and(Currency::getSymbol('GBP'))->toBe('£')
                ->and(Currency::getSymbol('JPY'))->toBe('¥');
        });
    });

    describe('subunit names', function () {
        it('returns correct subunit names', function () {
            expect(Currency::getSubunitName('THB'))->toBe('satang')
                ->and(Currency::getSubunitName('USD'))->toBe('cents')
                ->and(Currency::getSubunitName('GBP'))->toBe('pence')
                ->and(Currency::getSubunitName('JPY'))->toBe('yen');
        });
    });

    describe('amount formatting', function () {
        it('formats THB amount correctly', function () {
            expect(Currency::format(10000, 'THB'))->toBe('฿100.00')
                ->and(Currency::format(2050, 'THB'))->toBe('฿20.50');
        });

        it('formats USD amount correctly', function () {
            expect(Currency::format(9999, 'USD'))->toBe('$99.99');
        });

        it('formats JPY amount without decimals', function () {
            expect(Currency::format(1000, 'JPY'))->toBe('¥1,000');
        });

        it('formats with symbol after amount', function () {
            expect(Currency::format(10000, 'THB', false))->toBe('100.00 ฿');
        });
    });

    describe('amount limits', function () {
        it('returns correct minimum for THB', function () {
            expect(Currency::getMinimumAmount('THB'))->toBe(2000)
                ->and(Currency::getMinimumInMainUnit('THB'))->toBe(20.00);
        });

        it('returns correct maximum for THB', function () {
            expect(Currency::getMaximumAmount('THB'))->toBe(15000000)
                ->and(Currency::getMaximumInMainUnit('THB'))->toBe(150000.00);
        });

        it('returns correct limits for SGD', function () {
            expect(Currency::getMinimumAmount('SGD'))->toBe(100)
                ->and(Currency::getMaximumAmount('SGD'))->toBe(2000000);
        });
    });

    describe('amount validation', function () {
        it('validates amount within THB limits', function () {
            expect(Currency::validateAmount(2000, 'THB'))->toBeTrue()   // exactly min
                ->and(Currency::validateAmount(10000, 'THB'))->toBeTrue()
                ->and(Currency::validateAmount(15000000, 'THB'))->toBeTrue(); // exactly max
        });

        it('rejects amount below THB minimum', function () {
            expect(Currency::validateAmount(1999, 'THB'))->toBeFalse();
        });

        it('rejects amount above THB maximum', function () {
            expect(Currency::validateAmount(15000001, 'THB'))->toBeFalse();
        });
    });
});
