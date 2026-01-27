<?php

declare(strict_types=1);

use Omise\Api\Charge;
use Omise\Api\Source;
use Omise\Http\Response;
use Omise\PaymentMethods\ShopeepayQR;

beforeEach(function () {
    $this->chargeApi = Mockery::mock(Charge::class);
    $this->sourceApi = Mockery::mock(Source::class);
    $this->shopeepayQR = new ShopeepayQR($this->chargeApi, $this->sourceApi);
});

afterEach(function () {
    Mockery::close();
});

describe('ShopeepayQR', function () {
    describe('payment method properties', function () {
        it('has correct type', function () {
            expect($this->shopeepayQR->getType())->toBe('shopeepay');
        });

        it('has correct name', function () {
            expect($this->shopeepayQR->getName())->toBe('ShopeePay QR');
        });

        it('has correct flow', function () {
            expect($this->shopeepayQR->getFlow())->toBe(Source::FLOW_REDIRECT);
        });

        it('supports THB, SGD, and MYR currencies', function () {
            expect($this->shopeepayQR->getSupportedCurrencies())->toBe(['THB', 'SGD', 'MYR']);
        });

        it('supports THB currency', function () {
            expect($this->shopeepayQR->supportsCurrency('THB'))->toBeTrue();
        });

        it('supports SGD currency', function () {
            expect($this->shopeepayQR->supportsCurrency('SGD'))->toBeTrue();
        });

        it('supports MYR currency', function () {
            expect($this->shopeepayQR->supportsCurrency('MYR'))->toBeTrue();
        });

        it('does not support USD currency', function () {
            expect($this->shopeepayQR->supportsCurrency('USD'))->toBeFalse();
        });
    });

    describe('amount limits - THB', function () {
        it('has correct minimum amount (20 THB)', function () {
            expect($this->shopeepayQR->getMinimumAmount('THB'))->toBe(2000);
        });

        it('has correct maximum amount (150,000 THB)', function () {
            expect($this->shopeepayQR->getMaximumAmount('THB'))->toBe(15000000);
        });

        it('has correct minimum in main unit', function () {
            expect($this->shopeepayQR->getMinimumInMainUnit('THB'))->toBe(20.0);
        });

        it('has correct maximum in main unit', function () {
            expect($this->shopeepayQR->getMaximumInMainUnit('THB'))->toBe(150000.0);
        });

        it('validates amount within limits', function () {
            expect($this->shopeepayQR->validateAmount(10000, 'THB'))->toBeTrue(); // 100 THB
        });

        it('rejects amount below minimum', function () {
            expect($this->shopeepayQR->validateAmount(1000, 'THB'))->toBeFalse(); // 10 THB
        });

        it('rejects amount above maximum', function () {
            expect($this->shopeepayQR->validateAmount(20000000, 'THB'))->toBeFalse(); // 200,000 THB
        });
    });

    describe('amount limits - SGD', function () {
        it('has correct minimum amount (1 SGD)', function () {
            expect($this->shopeepayQR->getMinimumAmount('SGD'))->toBe(100);
        });

        it('has correct maximum amount (20,000 SGD)', function () {
            expect($this->shopeepayQR->getMaximumAmount('SGD'))->toBe(2000000);
        });

        it('has correct minimum in main unit', function () {
            expect($this->shopeepayQR->getMinimumInMainUnit('SGD'))->toBe(1.0);
        });

        it('has correct maximum in main unit', function () {
            expect($this->shopeepayQR->getMaximumInMainUnit('SGD'))->toBe(20000.0);
        });

        it('validates amount within limits', function () {
            expect($this->shopeepayQR->validateAmount(5000, 'SGD'))->toBeTrue(); // 50 SGD
        });
    });

    describe('amount limits - MYR', function () {
        it('has correct minimum amount (1 MYR)', function () {
            expect($this->shopeepayQR->getMinimumAmount('MYR'))->toBe(100);
        });

        it('has correct maximum amount (4,999 MYR)', function () {
            expect($this->shopeepayQR->getMaximumAmount('MYR'))->toBe(499900);
        });

        it('has correct minimum in main unit', function () {
            expect($this->shopeepayQR->getMinimumInMainUnit('MYR'))->toBe(1.0);
        });

        it('has correct maximum in main unit', function () {
            expect($this->shopeepayQR->getMaximumInMainUnit('MYR'))->toBe(4999.0);
        });

        it('validates amount within limits', function () {
            expect($this->shopeepayQR->validateAmount(100000, 'MYR'))->toBeTrue(); // 1000 MYR
        });
    });

    describe('currency conversion', function () {
        it('converts THB to satang', function () {
            expect($this->shopeepayQR->toSmallestUnit(100.0, 'THB'))->toBe(10000);
        });

        it('converts satang to THB', function () {
            expect($this->shopeepayQR->toMainUnit(10000, 'THB'))->toBe(100.0);
        });

        it('converts SGD to cents', function () {
            expect($this->shopeepayQR->toSmallestUnit(50.0, 'SGD'))->toBe(5000);
        });

        it('converts cents to SGD', function () {
            expect($this->shopeepayQR->toMainUnit(5000, 'SGD'))->toBe(50.0);
        });

        it('converts MYR to sen', function () {
            expect($this->shopeepayQR->toSmallestUnit(100.0, 'MYR'))->toBe(10000);
        });

        it('converts sen to MYR', function () {
            expect($this->shopeepayQR->toMainUnit(10000, 'MYR'))->toBe(100.0);
        });
    });

    describe('country mapping', function () {
        it('returns Thailand for THB', function () {
            expect($this->shopeepayQR->getCountryForCurrency('THB'))->toBe('Thailand');
        });

        it('returns Singapore for SGD', function () {
            expect($this->shopeepayQR->getCountryForCurrency('SGD'))->toBe('Singapore');
        });

        it('returns Malaysia for MYR', function () {
            expect($this->shopeepayQR->getCountryForCurrency('MYR'))->toBe('Malaysia');
        });

        it('returns Unknown for unsupported currency', function () {
            expect($this->shopeepayQR->getCountryForCurrency('USD'))->toBe('Unknown');
        });
    });

    describe('expiration times', function () {
        it('returns 20 minutes for THB', function () {
            expect($this->shopeepayQR->getDefaultExpirationMinutes('THB'))->toBe(20);
        });

        it('returns 20 minutes for SGD', function () {
            expect($this->shopeepayQR->getDefaultExpirationMinutes('SGD'))->toBe(20);
        });

        it('returns 60 minutes for MYR', function () {
            expect($this->shopeepayQR->getDefaultExpirationMinutes('MYR'))->toBe(60);
        });

        it('has correct max expiration constant', function () {
            expect(ShopeepayQR::MAX_EXPIRATION_MINUTES)->toBe(60);
        });
    });

    describe('charge status helpers', function () {
        it('detects pending charge', function () {
            $charge = new Response(['status' => 'pending']);
            expect($this->shopeepayQR->isPending($charge))->toBeTrue();
        });

        it('detects successful charge', function () {
            $charge = new Response(['status' => 'successful']);
            expect($this->shopeepayQR->isSuccessful($charge))->toBeTrue();
        });

        it('detects failed charge', function () {
            $charge = new Response(['status' => 'failed']);
            expect($this->shopeepayQR->isFailed($charge))->toBeTrue();
        });

        it('detects expired charge', function () {
            $charge = new Response(['status' => 'expired']);
            expect($this->shopeepayQR->isExpired($charge))->toBeTrue();
        });

        it('detects pending redirect', function () {
            $charge = new Response([
                'status' => 'pending',
                'authorize_uri' => 'https://shopeepay.com/pay/123',
            ]);
            expect($this->shopeepayQR->isPendingRedirect($charge))->toBeTrue();
        });

        it('returns false for pending without authorize_uri', function () {
            $charge = new Response(['status' => 'pending']);
            expect($this->shopeepayQR->isPendingRedirect($charge))->toBeFalse();
        });
    });

    describe('authorize URI', function () {
        it('extracts authorize_uri from charge', function () {
            $charge = new Response([
                'authorize_uri' => 'https://shopeepay.com/pay/123',
            ]);
            expect($this->shopeepayQR->getAuthorizeUri($charge))->toBe('https://shopeepay.com/pay/123');
        });

        it('returns null when authorize_uri not present', function () {
            $charge = new Response([]);
            expect($this->shopeepayQR->getAuthorizeUri($charge))->toBeNull();
        });
    });

    describe('return URI', function () {
        it('extracts return_uri from charge', function () {
            $charge = new Response([
                'return_uri' => 'https://example.com/callback',
            ]);
            expect($this->shopeepayQR->getReturnUri($charge))->toBe('https://example.com/callback');
        });

        it('returns null when return_uri not present', function () {
            $charge = new Response([]);
            expect($this->shopeepayQR->getReturnUri($charge))->toBeNull();
        });
    });

    describe('failure codes', function () {
        it('has correct failure code constants', function () {
            expect(ShopeepayQR::FAILURE_PROCESSING)->toBe('failed_processing');
            expect(ShopeepayQR::FAILURE_CANCELLED)->toBe('payment_cancelled');
            expect(ShopeepayQR::FAILURE_EXPIRED)->toBe('payment_expired');
            expect(ShopeepayQR::FAILURE_REJECTED)->toBe('payment_rejected');
            expect(ShopeepayQR::FAILURE_INVALID_ACCOUNT)->toBe('invalid_account');
            expect(ShopeepayQR::FAILURE_INSUFFICIENT_FUND)->toBe('insufficient_fund');
        });

        it('gets failure code from charge', function () {
            $charge = new Response(['failure_code' => 'payment_cancelled']);
            expect($this->shopeepayQR->getFailureCode($charge))->toBe('payment_cancelled');
        });
    });

    describe('failure messages - Thai', function () {
        it('returns Thai message for failed_processing', function () {
            $charge = new Response(['failure_code' => 'failed_processing']);
            expect($this->shopeepayQR->getFailureMessage($charge, 'th'))->toBe('ระบบทำรายการไม่สำเร็จ');
        });

        it('returns Thai message for payment_cancelled', function () {
            $charge = new Response(['failure_code' => 'payment_cancelled']);
            expect($this->shopeepayQR->getFailureMessage($charge, 'th'))->toBe('ยกเลิกการชำระเงิน');
        });

        it('returns Thai message for payment_expired', function () {
            $charge = new Response(['failure_code' => 'payment_expired']);
            expect($this->shopeepayQR->getFailureMessage($charge, 'th'))->toBe('การชำระเงินหมดอายุ');
        });

        it('returns Thai message for payment_rejected', function () {
            $charge = new Response(['failure_code' => 'payment_rejected']);
            expect($this->shopeepayQR->getFailureMessage($charge, 'th'))->toBe('การชำระเงินถูกปฏิเสธ');
        });

        it('returns Thai message for invalid_account', function () {
            $charge = new Response(['failure_code' => 'invalid_account']);
            expect($this->shopeepayQR->getFailureMessage($charge, 'th'))->toBe('ไม่พบบัญชี ShopeePay ที่ถูกต้อง');
        });

        it('returns Thai message for insufficient_fund', function () {
            $charge = new Response(['failure_code' => 'insufficient_fund']);
            expect($this->shopeepayQR->getFailureMessage($charge, 'th'))->toBe('ยอดเงินไม่เพียงพอหรือเกินวงเงิน');
        });

        it('returns API failure_message for unknown code', function () {
            $charge = new Response([
                'failure_code' => 'unknown_error',
                'failure_message' => 'Something went wrong',
            ]);
            expect($this->shopeepayQR->getFailureMessage($charge, 'th'))->toBe('Something went wrong');
        });
    });

    describe('failure messages - English', function () {
        it('returns English message for failed_processing', function () {
            $charge = new Response(['failure_code' => 'failed_processing']);
            expect($this->shopeepayQR->getFailureMessage($charge, 'en'))->toBe('Payment processing failed');
        });

        it('returns English message for payment_cancelled', function () {
            $charge = new Response(['failure_code' => 'payment_cancelled']);
            expect($this->shopeepayQR->getFailureMessage($charge, 'en'))->toBe('Payment was cancelled');
        });

        it('returns English message for payment_expired', function () {
            $charge = new Response(['failure_code' => 'payment_expired']);
            expect($this->shopeepayQR->getFailureMessage($charge, 'en'))->toBe('Payment expired');
        });

        it('returns English message for payment_rejected', function () {
            $charge = new Response(['failure_code' => 'payment_rejected']);
            expect($this->shopeepayQR->getFailureMessage($charge, 'en'))->toBe('Payment was rejected by issuer');
        });

        it('returns English message for invalid_account', function () {
            $charge = new Response(['failure_code' => 'invalid_account']);
            expect($this->shopeepayQR->getFailureMessage($charge, 'en'))->toBe('No valid ShopeePay account found');
        });

        it('returns English message for insufficient_fund', function () {
            $charge = new Response(['failure_code' => 'insufficient_fund']);
            expect($this->shopeepayQR->getFailureMessage($charge, 'en'))->toBe('Insufficient funds or limit exceeded');
        });
    });

    describe('refund eligibility', function () {
        it('has correct refund window constant', function () {
            expect(ShopeepayQR::REFUND_WINDOW_DAYS)->toBe(180);
        });

        it('allows refund within 180 days', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-90 days')),
            ]);
            expect($this->shopeepayQR->canRefund($charge))->toBeTrue();
        });

        it('disallows refund after 180 days', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-181 days')),
            ]);
            expect($this->shopeepayQR->canRefund($charge))->toBeFalse();
        });

        it('does not allow refund for non-successful charge', function () {
            $charge = new Response([
                'status' => 'failed',
                'created_at' => date('c', strtotime('-1 day')),
            ]);
            expect($this->shopeepayQR->canRefund($charge))->toBeFalse();
        });

        it('returns refund deadline', function () {
            $createdAt = '2024-01-15T10:00:00+07:00';
            $charge = new Response([
                'status' => 'successful',
                'created_at' => $createdAt,
            ]);

            $deadline = $this->shopeepayQR->getRefundDeadline($charge);
            expect($deadline)->not->toBeNull();

            $createdTime = strtotime($createdAt);
            $expectedDeadline = date('c', $createdTime + (180 * 24 * 60 * 60));
            expect($deadline)->toBe($expectedDeadline);
        });

        it('returns null deadline when created_at is missing', function () {
            $charge = new Response(['status' => 'successful']);
            expect($this->shopeepayQR->getRefundDeadline($charge))->toBeNull();
        });
    });

    describe('amount validation for currency', function () {
        it('validates THB amount', function () {
            expect($this->shopeepayQR->validateAmountForCurrency(100.0, 'THB'))->toBeTrue();
        });

        it('validates SGD amount', function () {
            expect($this->shopeepayQR->validateAmountForCurrency(50.0, 'SGD'))->toBeTrue();
        });

        it('validates MYR amount', function () {
            expect($this->shopeepayQR->validateAmountForCurrency(100.0, 'MYR'))->toBeTrue();
        });

        it('throws exception for unsupported currency', function () {
            expect(fn() => $this->shopeepayQR->validateAmountForCurrency(100.0, 'USD'))
                ->toThrow(\InvalidArgumentException::class, 'Unsupported currency: USD');
        });

        it('rejects amount below THB minimum', function () {
            expect($this->shopeepayQR->validateAmountForCurrency(10.0, 'THB'))->toBeFalse();
        });

        it('rejects amount above THB maximum', function () {
            expect($this->shopeepayQR->validateAmountForCurrency(200000.0, 'THB'))->toBeFalse();
        });
    });
});
