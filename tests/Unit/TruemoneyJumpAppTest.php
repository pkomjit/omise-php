<?php

use Omise\Api\Charge;
use Omise\Api\Source;
use Omise\Http\Response;
use Omise\PaymentMethods\TruemoneyJumpApp;

describe('TruemoneyJumpApp', function () {
    describe('payment method properties', function () {
        it('has correct type', function () {
            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getType())->toBe('truemoney_jumpapp');
        });

        it('has correct name', function () {
            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getName())->toBe('TrueMoney App');
        });

        it('has correct flow', function () {
            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getFlow())->toBe('app_redirect');
        });

        it('supports THB currency only', function () {
            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->supportsCurrency('THB'))->toBeTrue()
                ->and($truemoneyJumpApp->supportsCurrency('USD'))->toBeFalse()
                ->and($truemoneyJumpApp->getSupportedCurrencies())->toBe(['THB']);
        });

        it('requires return_uri parameter', function () {
            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getRequiredParameters())->toBe(['return_uri']);
        });
    });

    describe('amount limits', function () {
        it('has correct minimum amount (100 THB)', function () {
            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getMinimumAmount('THB'))->toBe(10000)
                ->and($truemoneyJumpApp->getMinimumThb())->toBe(100.0);
        });

        it('has correct maximum amount (50,000 THB)', function () {
            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getMaximumAmount('THB'))->toBe(5000000)
                ->and($truemoneyJumpApp->getMaximumThb())->toBe(50000.0);
        });

        it('validates amount within limits', function () {
            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->validateAmount(10000, 'THB'))->toBeTrue()    // exactly min
                ->and($truemoneyJumpApp->validateAmount(100000, 'THB'))->toBeTrue()
                ->and($truemoneyJumpApp->validateAmount(5000000, 'THB'))->toBeTrue();  // exactly max
        });

        it('rejects amount below minimum', function () {
            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->validateAmount(9999, 'THB'))->toBeFalse();
        });

        it('rejects amount above maximum', function () {
            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->validateAmount(5000001, 'THB'))->toBeFalse();
        });

        it('validates THB amount', function () {
            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->validateThbAmount(100.00))->toBeTrue()
                ->and($truemoneyJumpApp->validateThbAmount(50000.00))->toBeTrue()
                ->and($truemoneyJumpApp->validateThbAmount(99.99))->toBeFalse();
        });
    });

    describe('currency conversion', function () {
        it('converts THB to satang', function () {
            expect(TruemoneyJumpApp::toSatang(100.00))->toBe(10000)
                ->and(TruemoneyJumpApp::toSatang(50000.00))->toBe(5000000)
                ->and(TruemoneyJumpApp::toSatang(99.99))->toBe(9999);
        });

        it('converts satang to THB', function () {
            expect(TruemoneyJumpApp::toThb(10000))->toBe(100.0)
                ->and(TruemoneyJumpApp::toThb(5000000))->toBe(50000.0)
                ->and(TruemoneyJumpApp::toThb(9999))->toBe(99.99);
        });
    });

    describe('charge status helpers', function () {
        it('detects pending charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'pending',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->isPending($charge))->toBeTrue()
                ->and($truemoneyJumpApp->isSuccessful($charge))->toBeFalse()
                ->and($truemoneyJumpApp->isFailed($charge))->toBeFalse()
                ->and($truemoneyJumpApp->isExpired($charge))->toBeFalse();
        });

        it('detects pending redirect', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'pending',
                'authorize_uri' => 'https://pay.truemoney.com/auth/abc123',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->isPendingRedirect($charge))->toBeTrue();
        });

        it('detects successful charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'successful',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->isSuccessful($charge))->toBeTrue()
                ->and($truemoneyJumpApp->isPending($charge))->toBeFalse()
                ->and($truemoneyJumpApp->isFailed($charge))->toBeFalse();
        });

        it('detects failed charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'failed',
                'failure_code' => 'insufficient_balance',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->isFailed($charge))->toBeTrue()
                ->and($truemoneyJumpApp->getFailureCode($charge))->toBe('insufficient_balance');
        });

        it('detects expired charge (3-minute timeout)', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'expired',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->isExpired($charge))->toBeTrue()
                ->and($truemoneyJumpApp->isPending($charge))->toBeFalse();
        });
    });

    describe('authorize and return URI', function () {
        it('extracts authorize URI from charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'pending',
                'authorize_uri' => 'https://pay.truemoney.com/auth/abc123',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getAuthorizeUri($charge))->toBe('https://pay.truemoney.com/auth/abc123');
        });

        it('extracts return URI from charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'return_uri' => 'https://your-site.com/callback',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getReturnUri($charge))->toBe('https://your-site.com/callback');
        });

        it('returns null when authorize URI not present', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'successful',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getAuthorizeUri($charge))->toBeNull();
        });
    });

    describe('failure messages', function () {
        it('returns Thai failure message for insufficient_balance', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'insufficient_balance',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getFailureMessage($charge, 'th'))->toBe('ยอดเงินไม่เพียงพอ');
        });

        it('returns English failure message for insufficient_balance', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'insufficient_balance',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getFailureMessage($charge, 'en'))->toBe('Insufficient balance');
        });

        it('returns Thai failure message for payment_cancelled', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'payment_cancelled',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getFailureMessage($charge, 'th'))->toBe('ยกเลิกการชำระเงิน');
        });

        it('returns Thai failure message for timeout (with 3-minute note)', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'timeout',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getFailureMessage($charge, 'th'))->toBe('หมดเวลาในการชำระเงิน (3 นาที)')
                ->and($truemoneyJumpApp->getFailureMessage($charge, 'en'))->toBe('Payment timed out (3 minutes)');
        });

        it('returns Thai failure message for expired', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'expired',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getFailureMessage($charge, 'th'))->toBe('การชำระเงินหมดอายุ');
        });

        it('returns Thai failure message for failed_processing', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'failed_processing',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getFailureMessage($charge, 'th'))->toBe('ระบบทำรายการไม่สำเร็จ');
        });

        it('returns API failure_message for unknown code', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'unknown_error',
                'failure_message' => 'Something went wrong',
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->getFailureMessage($charge, 'en'))->toBe('Something went wrong');
        });
    });

    describe('void and refund eligibility', function () {
        it('allows void on same day for successful charge', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c'), // Today
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->canVoid($charge))->toBeTrue();
        });

        it('disallows void on different day', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-1 day')), // Yesterday
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->canVoid($charge))->toBeFalse();
        });

        it('allows refund within 30 days', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-15 days')),
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->canRefund($charge))->toBeTrue();
        });

        it('disallows refund after 30 days', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-31 days')),
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->canRefund($charge))->toBeFalse();
        });

        it('returns refund deadline', function () {
            $createdAt = date('c', strtotime('2024-01-15'));
            $charge = new Response([
                'status' => 'successful',
                'created_at' => $createdAt,
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();
            $deadline = $truemoneyJumpApp->getRefundDeadline($charge);

            // Should be 30 days after creation
            expect($deadline)->not->toBeNull()
                ->and(strtotime($deadline))->toBe(strtotime('2024-02-14'));
        });

        it('does not allow void for non-successful charge', function () {
            $charge = new Response([
                'status' => 'pending',
                'created_at' => date('c'),
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->canVoid($charge))->toBeFalse();
        });

        it('does not allow refund for non-successful charge', function () {
            $charge = new Response([
                'status' => 'failed',
                'created_at' => date('c'),
            ]);

            $truemoneyJumpApp = createTruemoneyJumpApp();

            expect($truemoneyJumpApp->canRefund($charge))->toBeFalse();
        });
    });

    describe('failure code constants', function () {
        it('has correct failure code constants', function () {
            expect(TruemoneyJumpApp::FAILURE_PROCESSING)->toBe('failed_processing')
                ->and(TruemoneyJumpApp::FAILURE_INSUFFICIENT_BALANCE)->toBe('insufficient_balance')
                ->and(TruemoneyJumpApp::FAILURE_CANCELLED)->toBe('payment_cancelled')
                ->and(TruemoneyJumpApp::FAILURE_TIMEOUT)->toBe('timeout')
                ->and(TruemoneyJumpApp::FAILURE_EXPIRED)->toBe('expired');
        });
    });
});

/**
 * Helper function to create a TruemoneyJumpApp instance with mocked dependencies.
 */
function createTruemoneyJumpApp(): TruemoneyJumpApp
{
    $chargeApi = Mockery::mock(Charge::class);
    $sourceApi = Mockery::mock(Source::class);

    return new TruemoneyJumpApp($chargeApi, $sourceApi);
}
