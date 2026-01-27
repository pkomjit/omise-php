<?php

use Omise\Api\Charge;
use Omise\Api\Source;
use Omise\Http\Response;
use Omise\PaymentMethods\TruemoneyQR;

describe('TruemoneyQR', function () {
    describe('payment method properties', function () {
        it('has correct type', function () {
            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getType())->toBe('truemoney_qr');
        });

        it('has correct name', function () {
            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getName())->toBe('TrueMoney QR');
        });

        it('has correct flow', function () {
            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getFlow())->toBe('offline');
        });

        it('supports THB currency only', function () {
            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->supportsCurrency('THB'))->toBeTrue()
                ->and($truemoneyQR->supportsCurrency('USD'))->toBeFalse()
                ->and($truemoneyQR->getSupportedCurrencies())->toBe(['THB']);
        });
    });

    describe('amount limits', function () {
        it('has correct minimum amount (100 THB)', function () {
            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getMinimumAmount('THB'))->toBe(10000)
                ->and($truemoneyQR->getMinimumThb())->toBe(100.0);
        });

        it('has correct maximum amount (50,000 THB)', function () {
            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getMaximumAmount('THB'))->toBe(5000000)
                ->and($truemoneyQR->getMaximumThb())->toBe(50000.0);
        });

        it('validates amount within limits', function () {
            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->validateAmount(10000, 'THB'))->toBeTrue()    // exactly min
                ->and($truemoneyQR->validateAmount(100000, 'THB'))->toBeTrue()
                ->and($truemoneyQR->validateAmount(5000000, 'THB'))->toBeTrue();  // exactly max
        });

        it('rejects amount below minimum', function () {
            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->validateAmount(9999, 'THB'))->toBeFalse();
        });

        it('rejects amount above maximum', function () {
            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->validateAmount(5000001, 'THB'))->toBeFalse();
        });

        it('validates THB amount', function () {
            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->validateThbAmount(100.00))->toBeTrue()
                ->and($truemoneyQR->validateThbAmount(50000.00))->toBeTrue()
                ->and($truemoneyQR->validateThbAmount(99.99))->toBeFalse();
        });
    });

    describe('currency conversion', function () {
        it('converts THB to satang', function () {
            expect(TruemoneyQR::toSatang(100.00))->toBe(10000)
                ->and(TruemoneyQR::toSatang(50000.00))->toBe(5000000)
                ->and(TruemoneyQR::toSatang(99.99))->toBe(9999);
        });

        it('converts satang to THB', function () {
            expect(TruemoneyQR::toThb(10000))->toBe(100.0)
                ->and(TruemoneyQR::toThb(5000000))->toBe(50000.0)
                ->and(TruemoneyQR::toThb(9999))->toBe(99.99);
        });
    });

    describe('charge status helpers', function () {
        it('detects pending charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'pending',
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->isPending($charge))->toBeTrue()
                ->and($truemoneyQR->isSuccessful($charge))->toBeFalse()
                ->and($truemoneyQR->isFailed($charge))->toBeFalse()
                ->and($truemoneyQR->isExpired($charge))->toBeFalse();
        });

        it('detects successful charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'successful',
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->isSuccessful($charge))->toBeTrue()
                ->and($truemoneyQR->isPending($charge))->toBeFalse()
                ->and($truemoneyQR->isFailed($charge))->toBeFalse();
        });

        it('detects failed charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'failed',
                'failure_code' => 'insufficient_balance',
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->isFailed($charge))->toBeTrue()
                ->and($truemoneyQR->getFailureCode($charge))->toBe('insufficient_balance');
        });

        it('detects expired charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'expired',
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->isExpired($charge))->toBeTrue()
                ->and($truemoneyQR->isPending($charge))->toBeFalse();
        });
    });

    describe('failure messages', function () {
        it('returns Thai failure message for insufficient_balance', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'insufficient_balance',
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getFailureMessage($charge, 'th'))->toBe('ยอดเงินไม่เพียงพอ');
        });

        it('returns English failure message for insufficient_balance', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'insufficient_balance',
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getFailureMessage($charge, 'en'))->toBe('Insufficient balance');
        });

        it('returns Thai failure message for payment_cancelled', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'payment_cancelled',
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getFailureMessage($charge, 'th'))->toBe('ยกเลิกการชำระเงิน');
        });

        it('returns Thai failure message for timeout', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'timeout',
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getFailureMessage($charge, 'th'))->toBe('หมดเวลาในการชำระเงิน');
        });

        it('returns Thai failure message for expired', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'expired',
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getFailureMessage($charge, 'th'))->toBe('QR Code หมดอายุ');
        });

        it('returns Thai failure message for failed_processing', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'failed_processing',
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getFailureMessage($charge, 'th'))->toBe('ระบบทำรายการไม่สำเร็จ');
        });

        it('returns API failure_message for unknown code', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'unknown_error',
                'failure_message' => 'Something went wrong',
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getFailureMessage($charge, 'en'))->toBe('Something went wrong');
        });
    });

    describe('QR code URL', function () {
        it('extracts QR code URL from charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'pending',
                'source' => [
                    'scannable_code' => [
                        'image' => [
                            'download_uri' => 'https://api.omise.co/qr/abc123.svg',
                        ],
                    ],
                ],
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getQrCodeUrl($charge))->toBe('https://api.omise.co/qr/abc123.svg');
        });

        it('returns null when QR code URL not present', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'pending',
                'source' => [],
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->getQrCodeUrl($charge))->toBeNull();
        });
    });

    describe('void and refund eligibility', function () {
        it('allows void on same day for successful charge', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c'), // Today
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->canVoid($charge))->toBeTrue();
        });

        it('disallows void on different day', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-1 day')), // Yesterday
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->canVoid($charge))->toBeFalse();
        });

        it('allows refund within 30 days', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-15 days')),
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->canRefund($charge))->toBeTrue();
        });

        it('disallows refund after 30 days', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-31 days')),
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->canRefund($charge))->toBeFalse();
        });

        it('returns refund deadline', function () {
            $createdAt = date('c', strtotime('2024-01-15'));
            $charge = new Response([
                'status' => 'successful',
                'created_at' => $createdAt,
            ]);

            $truemoneyQR = createTruemoneyQR();
            $deadline = $truemoneyQR->getRefundDeadline($charge);

            // Should be 30 days after creation
            expect($deadline)->not->toBeNull()
                ->and(strtotime($deadline))->toBe(strtotime('2024-02-14'));
        });

        it('does not allow void for non-successful charge', function () {
            $charge = new Response([
                'status' => 'pending',
                'created_at' => date('c'),
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->canVoid($charge))->toBeFalse();
        });

        it('does not allow refund for non-successful charge', function () {
            $charge = new Response([
                'status' => 'failed',
                'created_at' => date('c'),
            ]);

            $truemoneyQR = createTruemoneyQR();

            expect($truemoneyQR->canRefund($charge))->toBeFalse();
        });
    });

    describe('failure code constants', function () {
        it('has correct failure code constants', function () {
            expect(TruemoneyQR::FAILURE_PROCESSING)->toBe('failed_processing')
                ->and(TruemoneyQR::FAILURE_INSUFFICIENT_BALANCE)->toBe('insufficient_balance')
                ->and(TruemoneyQR::FAILURE_CANCELLED)->toBe('payment_cancelled')
                ->and(TruemoneyQR::FAILURE_TIMEOUT)->toBe('timeout')
                ->and(TruemoneyQR::FAILURE_EXPIRED)->toBe('expired');
        });
    });
});

/**
 * Helper function to create a TruemoneyQR instance with mocked dependencies.
 */
function createTruemoneyQR(): TruemoneyQR
{
    $chargeApi = Mockery::mock(Charge::class);
    $sourceApi = Mockery::mock(Source::class);

    return new TruemoneyQR($chargeApi, $sourceApi);
}
