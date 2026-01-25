<?php

use Omise\PaymentMethods\PromptPay;

describe('PromptPay', function () {
    describe('currency conversion', function () {
        it('converts THB to satang', function () {
            expect(PromptPay::toSatang(100.00))->toBe(10000);
            expect(PromptPay::toSatang(20.00))->toBe(2000);
            expect(PromptPay::toSatang(150000.00))->toBe(15000000);
            expect(PromptPay::toSatang(99.99))->toBe(9999);
        });

        it('converts satang to THB', function () {
            expect(PromptPay::toThb(10000))->toBe(100.00);
            expect(PromptPay::toThb(2000))->toBe(20.00);
            expect(PromptPay::toThb(15000000))->toBe(150000.00);
            expect(PromptPay::toThb(9999))->toBe(99.99);
        });
    });

    describe('amount limits', function () {
        it('has correct minimum amount', function () {
            // Minimum is 20 THB = 2000 satang
            expect(PromptPay::toSatang(20.00))->toBe(2000);
        });

        it('has correct maximum amount', function () {
            // Maximum is 150,000 THB = 15,000,000 satang
            expect(PromptPay::toSatang(150000.00))->toBe(15000000);
        });
    });
});
