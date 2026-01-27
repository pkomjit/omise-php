<?php

declare(strict_types=1);

use Omise\Api\Charge;
use Omise\Api\Source;
use Omise\Http\Response;
use Omise\PaymentMethods\ShopeepayJumpApp;

beforeEach(function () {
    $this->chargeApi = Mockery::mock(Charge::class);
    $this->sourceApi = Mockery::mock(Source::class);
    $this->shopeepayJumpApp = new ShopeepayJumpApp($this->chargeApi, $this->sourceApi);
});

afterEach(function () {
    Mockery::close();
});

describe('ShopeepayJumpApp', function () {
    describe('payment method properties', function () {
        it('has correct type', function () {
            expect($this->shopeepayJumpApp->getType())->toBe('shopeepay_jumpapp');
        });

        it('has correct name', function () {
            expect($this->shopeepayJumpApp->getName())->toBe('ShopeePay App');
        });

        it('has correct flow', function () {
            expect($this->shopeepayJumpApp->getFlow())->toBe(Source::FLOW_APP_REDIRECT);
        });

        it('supports THB, SGD, and MYR currencies', function () {
            expect($this->shopeepayJumpApp->getSupportedCurrencies())->toBe(['THB', 'SGD', 'MYR']);
        });

        it('supports THB currency', function () {
            expect($this->shopeepayJumpApp->supportsCurrency('THB'))->toBeTrue();
        });

        it('supports SGD currency', function () {
            expect($this->shopeepayJumpApp->supportsCurrency('SGD'))->toBeTrue();
        });

        it('supports MYR currency', function () {
            expect($this->shopeepayJumpApp->supportsCurrency('MYR'))->toBeTrue();
        });

        it('does not support USD currency', function () {
            expect($this->shopeepayJumpApp->supportsCurrency('USD'))->toBeFalse();
        });
    });

    describe('amount limits - THB', function () {
        it('has correct minimum amount (20 THB)', function () {
            expect($this->shopeepayJumpApp->getMinimumAmount('THB'))->toBe(2000);
        });

        it('has correct maximum amount (150,000 THB)', function () {
            expect($this->shopeepayJumpApp->getMaximumAmount('THB'))->toBe(15000000);
        });

        it('has correct minimum in main unit', function () {
            expect($this->shopeepayJumpApp->getMinimumInMainUnit('THB'))->toBe(20.0);
        });

        it('has correct maximum in main unit', function () {
            expect($this->shopeepayJumpApp->getMaximumInMainUnit('THB'))->toBe(150000.0);
        });

        it('validates amount within limits', function () {
            expect($this->shopeepayJumpApp->validateAmount(10000, 'THB'))->toBeTrue(); // 100 THB
        });

        it('rejects amount below minimum', function () {
            expect($this->shopeepayJumpApp->validateAmount(1000, 'THB'))->toBeFalse(); // 10 THB
        });

        it('rejects amount above maximum', function () {
            expect($this->shopeepayJumpApp->validateAmount(20000000, 'THB'))->toBeFalse(); // 200,000 THB
        });
    });

    describe('amount limits - SGD', function () {
        it('has correct minimum amount (1 SGD)', function () {
            expect($this->shopeepayJumpApp->getMinimumAmount('SGD'))->toBe(100);
        });

        it('has correct maximum amount (20,000 SGD)', function () {
            expect($this->shopeepayJumpApp->getMaximumAmount('SGD'))->toBe(2000000);
        });

        it('has correct minimum in main unit', function () {
            expect($this->shopeepayJumpApp->getMinimumInMainUnit('SGD'))->toBe(1.0);
        });

        it('has correct maximum in main unit', function () {
            expect($this->shopeepayJumpApp->getMaximumInMainUnit('SGD'))->toBe(20000.0);
        });

        it('validates amount within limits', function () {
            expect($this->shopeepayJumpApp->validateAmount(5000, 'SGD'))->toBeTrue(); // 50 SGD
        });
    });

    describe('amount limits - MYR', function () {
        it('has correct minimum amount (1 MYR)', function () {
            expect($this->shopeepayJumpApp->getMinimumAmount('MYR'))->toBe(100);
        });

        it('has correct maximum amount (9,999 MYR)', function () {
            expect($this->shopeepayJumpApp->getMaximumAmount('MYR'))->toBe(999900);
        });

        it('has correct minimum in main unit', function () {
            expect($this->shopeepayJumpApp->getMinimumInMainUnit('MYR'))->toBe(1.0);
        });

        it('has correct maximum in main unit', function () {
            expect($this->shopeepayJumpApp->getMaximumInMainUnit('MYR'))->toBe(9999.0);
        });

        it('validates amount within limits', function () {
            expect($this->shopeepayJumpApp->validateAmount(100000, 'MYR'))->toBeTrue(); // 1000 MYR
        });
    });

    describe('currency conversion', function () {
        it('converts THB to satang', function () {
            expect($this->shopeepayJumpApp->toSmallestUnit(100.0, 'THB'))->toBe(10000);
        });

        it('converts satang to THB', function () {
            expect($this->shopeepayJumpApp->toMainUnit(10000, 'THB'))->toBe(100.0);
        });

        it('converts SGD to cents', function () {
            expect($this->shopeepayJumpApp->toSmallestUnit(50.0, 'SGD'))->toBe(5000);
        });

        it('converts cents to SGD', function () {
            expect($this->shopeepayJumpApp->toMainUnit(5000, 'SGD'))->toBe(50.0);
        });

        it('converts MYR to sen', function () {
            expect($this->shopeepayJumpApp->toSmallestUnit(100.0, 'MYR'))->toBe(10000);
        });

        it('converts sen to MYR', function () {
            expect($this->shopeepayJumpApp->toMainUnit(10000, 'MYR'))->toBe(100.0);
        });
    });

    describe('country mapping', function () {
        it('returns Thailand for THB', function () {
            expect($this->shopeepayJumpApp->getCountryForCurrency('THB'))->toBe('Thailand');
        });

        it('returns Singapore for SGD', function () {
            expect($this->shopeepayJumpApp->getCountryForCurrency('SGD'))->toBe('Singapore');
        });

        it('returns Malaysia for MYR', function () {
            expect($this->shopeepayJumpApp->getCountryForCurrency('MYR'))->toBe('Malaysia');
        });

        it('returns Unknown for unsupported currency', function () {
            expect($this->shopeepayJumpApp->getCountryForCurrency('USD'))->toBe('Unknown');
        });
    });

    describe('platform types', function () {
        it('has correct platform type constants', function () {
            expect(ShopeepayJumpApp::PLATFORM_IOS)->toBe('IOS');
            expect(ShopeepayJumpApp::PLATFORM_ANDROID)->toBe('ANDROID');
        });

        it('validates IOS platform type', function () {
            expect($this->shopeepayJumpApp->isValidPlatformType('IOS'))->toBeTrue();
        });

        it('validates ANDROID platform type', function () {
            expect($this->shopeepayJumpApp->isValidPlatformType('ANDROID'))->toBeTrue();
        });

        it('rejects invalid platform type', function () {
            expect($this->shopeepayJumpApp->isValidPlatformType('WINDOWS'))->toBeFalse();
        });

        it('throws exception for invalid platform type in validation', function () {
            expect(fn() => $this->shopeepayJumpApp->validatePlatformType('WINDOWS'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid platform type: WINDOWS. Must be IOS or ANDROID.');
        });
    });

    describe('expiration times', function () {
        it('has correct default expiration constant', function () {
            expect(ShopeepayJumpApp::DEFAULT_EXPIRATION_MINUTES)->toBe(20);
        });

        it('has correct max expiration constant', function () {
            expect(ShopeepayJumpApp::MAX_EXPIRATION_MINUTES)->toBe(60);
        });
    });

    describe('charge status helpers', function () {
        it('detects pending charge', function () {
            $charge = new Response(['status' => 'pending']);
            expect($this->shopeepayJumpApp->isPending($charge))->toBeTrue();
        });

        it('detects successful charge', function () {
            $charge = new Response(['status' => 'successful']);
            expect($this->shopeepayJumpApp->isSuccessful($charge))->toBeTrue();
        });

        it('detects failed charge', function () {
            $charge = new Response(['status' => 'failed']);
            expect($this->shopeepayJumpApp->isFailed($charge))->toBeTrue();
        });

        it('detects expired charge', function () {
            $charge = new Response(['status' => 'expired']);
            expect($this->shopeepayJumpApp->isExpired($charge))->toBeTrue();
        });

        it('detects pending redirect', function () {
            $charge = new Response([
                'status' => 'pending',
                'authorize_uri' => 'shopeepay://pay/123',
            ]);
            expect($this->shopeepayJumpApp->isPendingRedirect($charge))->toBeTrue();
        });

        it('returns false for pending without authorize_uri', function () {
            $charge = new Response(['status' => 'pending']);
            expect($this->shopeepayJumpApp->isPendingRedirect($charge))->toBeFalse();
        });
    });

    describe('authorize URI', function () {
        it('extracts authorize_uri from charge', function () {
            $charge = new Response([
                'authorize_uri' => 'shopeepay://pay/123',
            ]);
            expect($this->shopeepayJumpApp->getAuthorizeUri($charge))->toBe('shopeepay://pay/123');
        });

        it('returns null when authorize_uri not present', function () {
            $charge = new Response([]);
            expect($this->shopeepayJumpApp->getAuthorizeUri($charge))->toBeNull();
        });
    });

    describe('return URI', function () {
        it('extracts return_uri from charge', function () {
            $charge = new Response([
                'return_uri' => 'https://example.com/callback',
            ]);
            expect($this->shopeepayJumpApp->getReturnUri($charge))->toBe('https://example.com/callback');
        });

        it('returns null when return_uri not present', function () {
            $charge = new Response([]);
            expect($this->shopeepayJumpApp->getReturnUri($charge))->toBeNull();
        });
    });

    describe('failure codes', function () {
        it('has correct failure code constants', function () {
            expect(ShopeepayJumpApp::FAILURE_PROCESSING)->toBe('failed_processing');
            expect(ShopeepayJumpApp::FAILURE_CANCELLED)->toBe('payment_cancelled');
            expect(ShopeepayJumpApp::FAILURE_EXPIRED)->toBe('payment_expired');
            expect(ShopeepayJumpApp::FAILURE_REJECTED)->toBe('payment_rejected');
            expect(ShopeepayJumpApp::FAILURE_INVALID_ACCOUNT)->toBe('invalid_account');
            expect(ShopeepayJumpApp::FAILURE_INSUFFICIENT_FUND)->toBe('insufficient_fund');
        });

        it('gets failure code from charge', function () {
            $charge = new Response(['failure_code' => 'payment_cancelled']);
            expect($this->shopeepayJumpApp->getFailureCode($charge))->toBe('payment_cancelled');
        });
    });

    describe('failure messages - Thai', function () {
        it('returns Thai message for failed_processing', function () {
            $charge = new Response(['failure_code' => 'failed_processing']);
            expect($this->shopeepayJumpApp->getFailureMessage($charge, 'th'))->toBe('ระบบทำรายการไม่สำเร็จ');
        });

        it('returns Thai message for payment_cancelled', function () {
            $charge = new Response(['failure_code' => 'payment_cancelled']);
            expect($this->shopeepayJumpApp->getFailureMessage($charge, 'th'))->toBe('ยกเลิกการชำระเงิน');
        });

        it('returns Thai message for payment_expired', function () {
            $charge = new Response(['failure_code' => 'payment_expired']);
            expect($this->shopeepayJumpApp->getFailureMessage($charge, 'th'))->toBe('การชำระเงินหมดอายุ');
        });

        it('returns Thai message for payment_rejected', function () {
            $charge = new Response(['failure_code' => 'payment_rejected']);
            expect($this->shopeepayJumpApp->getFailureMessage($charge, 'th'))->toBe('การชำระเงินถูกปฏิเสธ');
        });

        it('returns Thai message for invalid_account', function () {
            $charge = new Response(['failure_code' => 'invalid_account']);
            expect($this->shopeepayJumpApp->getFailureMessage($charge, 'th'))->toBe('ไม่พบบัญชี ShopeePay ที่ถูกต้อง');
        });

        it('returns Thai message for insufficient_fund', function () {
            $charge = new Response(['failure_code' => 'insufficient_fund']);
            expect($this->shopeepayJumpApp->getFailureMessage($charge, 'th'))->toBe('ยอดเงินไม่เพียงพอหรือเกินวงเงิน');
        });

        it('returns API failure_message for unknown code', function () {
            $charge = new Response([
                'failure_code' => 'unknown_error',
                'failure_message' => 'Something went wrong',
            ]);
            expect($this->shopeepayJumpApp->getFailureMessage($charge, 'th'))->toBe('Something went wrong');
        });
    });

    describe('failure messages - English', function () {
        it('returns English message for failed_processing', function () {
            $charge = new Response(['failure_code' => 'failed_processing']);
            expect($this->shopeepayJumpApp->getFailureMessage($charge, 'en'))->toBe('Payment processing failed');
        });

        it('returns English message for payment_cancelled', function () {
            $charge = new Response(['failure_code' => 'payment_cancelled']);
            expect($this->shopeepayJumpApp->getFailureMessage($charge, 'en'))->toBe('Payment was cancelled');
        });

        it('returns English message for payment_expired', function () {
            $charge = new Response(['failure_code' => 'payment_expired']);
            expect($this->shopeepayJumpApp->getFailureMessage($charge, 'en'))->toBe('Payment expired');
        });

        it('returns English message for payment_rejected', function () {
            $charge = new Response(['failure_code' => 'payment_rejected']);
            expect($this->shopeepayJumpApp->getFailureMessage($charge, 'en'))->toBe('Payment was rejected by issuer');
        });

        it('returns English message for invalid_account', function () {
            $charge = new Response(['failure_code' => 'invalid_account']);
            expect($this->shopeepayJumpApp->getFailureMessage($charge, 'en'))->toBe('No valid ShopeePay account found');
        });

        it('returns English message for insufficient_fund', function () {
            $charge = new Response(['failure_code' => 'insufficient_fund']);
            expect($this->shopeepayJumpApp->getFailureMessage($charge, 'en'))->toBe('Insufficient funds or limit exceeded');
        });
    });

    describe('refund eligibility', function () {
        it('has correct refund window constant', function () {
            expect(ShopeepayJumpApp::REFUND_WINDOW_DAYS)->toBe(180);
        });

        it('allows refund within 180 days', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-90 days')),
            ]);
            expect($this->shopeepayJumpApp->canRefund($charge))->toBeTrue();
        });

        it('disallows refund after 180 days', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-181 days')),
            ]);
            expect($this->shopeepayJumpApp->canRefund($charge))->toBeFalse();
        });

        it('does not allow refund for non-successful charge', function () {
            $charge = new Response([
                'status' => 'failed',
                'created_at' => date('c', strtotime('-1 day')),
            ]);
            expect($this->shopeepayJumpApp->canRefund($charge))->toBeFalse();
        });

        it('returns refund deadline', function () {
            $createdAt = '2024-01-15T10:00:00+07:00';
            $charge = new Response([
                'status' => 'successful',
                'created_at' => $createdAt,
            ]);

            $deadline = $this->shopeepayJumpApp->getRefundDeadline($charge);
            expect($deadline)->not->toBeNull();

            $createdTime = strtotime($createdAt);
            $expectedDeadline = date('c', $createdTime + (180 * 24 * 60 * 60));
            expect($deadline)->toBe($expectedDeadline);
        });

        it('returns null deadline when created_at is missing', function () {
            $charge = new Response(['status' => 'successful']);
            expect($this->shopeepayJumpApp->getRefundDeadline($charge))->toBeNull();
        });
    });

    describe('amount validation for currency', function () {
        it('validates THB amount', function () {
            expect($this->shopeepayJumpApp->validateAmountForCurrency(100.0, 'THB'))->toBeTrue();
        });

        it('validates SGD amount', function () {
            expect($this->shopeepayJumpApp->validateAmountForCurrency(50.0, 'SGD'))->toBeTrue();
        });

        it('validates MYR amount', function () {
            expect($this->shopeepayJumpApp->validateAmountForCurrency(100.0, 'MYR'))->toBeTrue();
        });

        it('throws exception for unsupported currency', function () {
            expect(fn() => $this->shopeepayJumpApp->validateAmountForCurrency(100.0, 'USD'))
                ->toThrow(\InvalidArgumentException::class, 'Unsupported currency: USD');
        });

        it('rejects amount below THB minimum', function () {
            expect($this->shopeepayJumpApp->validateAmountForCurrency(10.0, 'THB'))->toBeFalse();
        });

        it('rejects amount above THB maximum', function () {
            expect($this->shopeepayJumpApp->validateAmountForCurrency(200000.0, 'THB'))->toBeFalse();
        });

        it('rejects amount above MYR maximum', function () {
            expect($this->shopeepayJumpApp->validateAmountForCurrency(10000.0, 'MYR'))->toBeFalse();
        });
    });
});
