<?php

use Omise\Http\Response;
use Omise\PaymentMethods\RabbitLinePay;

describe('RabbitLinePay', function () {
    describe('currency conversion', function () {
        it('converts THB to satang', function () {
            expect(RabbitLinePay::toSatang(100.00))->toBe(10000)
                ->and(RabbitLinePay::toSatang(20.00))->toBe(2000)
                ->and(RabbitLinePay::toSatang(150000.00))->toBe(15000000)
                ->and(RabbitLinePay::toSatang(99.99))->toBe(9999);
        });

        it('converts satang to THB', function () {
            expect(RabbitLinePay::toThb(10000))->toBe(100.00)
                ->and(RabbitLinePay::toThb(2000))->toBe(20.00)
                ->and(RabbitLinePay::toThb(15000000))->toBe(150000.00)
                ->and(RabbitLinePay::toThb(9999))->toBe(99.99);
        });
    });

    describe('amount limits', function () {
        it('has correct minimum amount (20 THB)', function () {
            expect(RabbitLinePay::toSatang(20.00))->toBe(2000);
        });

        it('has correct maximum amount (150,000 THB)', function () {
            expect(RabbitLinePay::toSatang(150000.00))->toBe(15000000);
        });
    });

    describe('charge status helpers', function () {
        it('detects pending charge', function () {
            $charge = new Response([
                'status' => 'pending',
                'authorize_uri' => 'https://pay.line.me/...',
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->isPending($charge))->toBeTrue()
                ->and($rabbitLinePay->isPendingRedirect($charge))->toBeTrue()
                ->and($rabbitLinePay->isSuccessful($charge))->toBeFalse()
                ->and($rabbitLinePay->isFailed($charge))->toBeFalse();
        });

        it('detects successful charge', function () {
            $charge = new Response([
                'status' => 'successful',
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->isSuccessful($charge))->toBeTrue()
                ->and($rabbitLinePay->isPending($charge))->toBeFalse()
                ->and($rabbitLinePay->isFailed($charge))->toBeFalse();
        });

        it('detects failed charge', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'payment_cancelled',
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->isFailed($charge))->toBeTrue()
                ->and($rabbitLinePay->isSuccessful($charge))->toBeFalse()
                ->and($rabbitLinePay->getFailureCode($charge))->toBe('payment_cancelled');
        });

        it('detects expired charge', function () {
            $charge = new Response([
                'status' => 'expired',
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->isExpired($charge))->toBeTrue()
                ->and($rabbitLinePay->isSuccessful($charge))->toBeFalse();
        });

        it('detects reversed charge', function () {
            $charge = new Response([
                'status' => 'reversed',
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->isReversed($charge))->toBeTrue();
        });
    });

    describe('failure messages', function () {
        it('returns Thai failure message for failed_processing', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'failed_processing',
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getFailureMessage($charge, 'th'))->toBe('ระบบทำรายการไม่สำเร็จ');
        });

        it('returns English failure message for failed_processing', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'failed_processing',
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getFailureMessage($charge, 'en'))->toBe('Payment processing failed');
        });

        it('returns Thai failure message for insufficient_balance', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'insufficient_balance',
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getFailureMessage($charge, 'th'))->toBe('วงเงินคงเหลือไม่เพียงพอ');
        });

        it('returns Thai failure message for payment_cancelled', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'payment_cancelled',
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getFailureMessage($charge, 'th'))->toBe('ผู้ซื้อยกเลิกการชำระเงิน');
        });

        it('returns Thai failure message for timeout', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'timeout',
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getFailureMessage($charge, 'th'))->toBe('หมดเวลาในการชำระเงิน');
        });
    });

    describe('authorize URI', function () {
        it('extracts authorize_uri from charge', function () {
            $charge = new Response([
                'status' => 'pending',
                'authorize_uri' => 'https://pay.line.me/authorize/test123',
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getAuthorizeUri($charge))->toBe('https://pay.line.me/authorize/test123');
        });

        it('returns null when authorize_uri is missing', function () {
            $charge = new Response([
                'status' => 'pending',
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getAuthorizeUri($charge))->toBeNull();
        });
    });

    describe('return URI', function () {
        it('extracts return_uri from charge', function () {
            $charge = new Response([
                'return_uri' => 'https://example.com/callback',
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getReturnUri($charge))->toBe('https://example.com/callback');
        });
    });

    describe('refund eligibility', function () {
        it('allows refund for successful charge within 60 days', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-30 days')),
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->canRefund($charge))->toBeTrue();
        });

        it('denies refund for successful charge after 60 days', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-61 days')),
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->canRefund($charge))->toBeFalse();
        });

        it('denies refund for non-successful charge', function () {
            $charge = new Response([
                'status' => 'pending',
                'created_at' => date('c'),
            ]);

            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->canRefund($charge))->toBeFalse();
        });

        it('calculates refund deadline correctly', function () {
            $createdAt = '2025-01-15T10:00:00+00:00';
            $charge = new Response([
                'status' => 'successful',
                'created_at' => $createdAt,
            ]);

            $rabbitLinePay = createRabbitLinePay();
            $deadline = $rabbitLinePay->getRefundDeadline($charge);

            // Deadline should be 60 days after creation
            expect($deadline)->not->toBeNull();
            $deadlineTime = strtotime($deadline);
            $createdTime = strtotime($createdAt);
            $expectedDeadline = $createdTime + (60 * 24 * 60 * 60);

            expect($deadlineTime)->toBe($expectedDeadline);
        });
    });

    describe('payment method properties', function () {
        it('has correct type', function () {
            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getType())->toBe('rabbit_linepay');
        });

        it('has correct name', function () {
            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getName())->toBe('Rabbit LINE Pay');
        });

        it('has correct flow type', function () {
            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getFlow())->toBe('redirect');
        });

        it('supports THB currency', function () {
            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->supportsCurrency('THB'))->toBeTrue()
                ->and($rabbitLinePay->supportsCurrency('USD'))->toBeFalse();
        });

        it('requires return_uri parameter', function () {
            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getRequiredParameters())->toContain('return_uri');
        });
    });

    describe('amount validation', function () {
        it('validates amount within limits', function () {
            $rabbitLinePay = createRabbitLinePay();

            // Valid amounts
            expect($rabbitLinePay->validateThbAmount(20.00))->toBeTrue()
                ->and($rabbitLinePay->validateThbAmount(100.00))->toBeTrue()
                ->and($rabbitLinePay->validateThbAmount(150000.00))->toBeTrue();
        });

        it('rejects amount below minimum', function () {
            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->validateThbAmount(19.99))->toBeFalse();
        });

        it('rejects amount above maximum', function () {
            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->validateThbAmount(150001.00))->toBeFalse();
        });

        it('returns correct minimum in THB', function () {
            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getMinimumThb())->toBe(20.00);
        });

        it('returns correct maximum in THB', function () {
            $rabbitLinePay = createRabbitLinePay();

            expect($rabbitLinePay->getMaximumThb())->toBe(150000.00);
        });
    });
});

/**
 * Helper function to create a RabbitLinePay instance with mocked dependencies.
 */
function createRabbitLinePay(): RabbitLinePay
{
    $chargeApi = Mockery::mock(\Omise\Api\Charge::class);
    $sourceApi = Mockery::mock(\Omise\Api\Source::class);

    return new RabbitLinePay($chargeApi, $sourceApi);
}
