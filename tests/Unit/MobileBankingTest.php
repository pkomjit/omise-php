<?php

use Omise\Api\Charge;
use Omise\Api\Source;
use Omise\Http\Response;
use Omise\PaymentMethods\MobileBanking;

describe('MobileBanking', function () {
    describe('bank constants', function () {
        it('has correct bank type constants', function () {
            expect(MobileBanking::BANK_BBL)->toBe('mobile_banking_bbl')
                ->and(MobileBanking::BANK_KBANK)->toBe('mobile_banking_kbank')
                ->and(MobileBanking::BANK_KTB)->toBe('mobile_banking_ktb')
                ->and(MobileBanking::BANK_BAY)->toBe('mobile_banking_bay')
                ->and(MobileBanking::BANK_SCB)->toBe('mobile_banking_scb')
                ->and(MobileBanking::BANK_OCBC)->toBe('mobile_banking_ocbc');
        });

        it('has correct platform constants', function () {
            expect(MobileBanking::PLATFORM_IOS)->toBe('IOS')
                ->and(MobileBanking::PLATFORM_ANDROID)->toBe('ANDROID');
        });

        it('has correct failure code constants', function () {
            expect(MobileBanking::FAILURE_PROCESSING)->toBe('failed_processing')
                ->and(MobileBanking::FAILURE_INSUFFICIENT_FUND)->toBe('insufficient_fund')
                ->and(MobileBanking::FAILURE_INSUFFICIENT_BALANCE)->toBe('insufficient_balance')
                ->and(MobileBanking::FAILURE_INVALID_ACCOUNT)->toBe('invalid_account')
                ->and(MobileBanking::FAILURE_PAYMENT_REJECTED)->toBe('payment_rejected')
                ->and(MobileBanking::FAILURE_PAYMENT_EXPIRED)->toBe('payment_expired')
                ->and(MobileBanking::FAILURE_PAYMENT_CANCELLED)->toBe('payment_cancelled')
                ->and(MobileBanking::FAILURE_TIMEOUT)->toBe('timeout');
        });
    });

    describe('supported banks', function () {
        it('returns all supported banks', function () {
            $mobileBanking = createMobileBanking();
            $banks = $mobileBanking->getSupportedBanks();

            expect($banks)->toBeArray()
                ->and($banks)->toHaveCount(6)
                ->and($banks)->toHaveKey(MobileBanking::BANK_BBL)
                ->and($banks)->toHaveKey(MobileBanking::BANK_KBANK)
                ->and($banks)->toHaveKey(MobileBanking::BANK_KTB)
                ->and($banks)->toHaveKey(MobileBanking::BANK_BAY)
                ->and($banks)->toHaveKey(MobileBanking::BANK_SCB)
                ->and($banks)->toHaveKey(MobileBanking::BANK_OCBC);
        });

        it('returns Thailand banks only', function () {
            $mobileBanking = createMobileBanking();
            $banks = $mobileBanking->getThailandBanks();

            expect($banks)->toHaveCount(5)
                ->and($banks)->toHaveKey(MobileBanking::BANK_BBL)
                ->and($banks)->toHaveKey(MobileBanking::BANK_KBANK)
                ->and($banks)->not->toHaveKey(MobileBanking::BANK_OCBC);
        });

        it('returns Singapore banks only', function () {
            $mobileBanking = createMobileBanking();
            $banks = $mobileBanking->getSingaporeBanks();

            expect($banks)->toHaveCount(1)
                ->and($banks)->toHaveKey(MobileBanking::BANK_OCBC);
        });

        it('returns correct bank names', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getBankName(MobileBanking::BANK_BBL))->toBe('Bangkok Bank (Bualuang mBanking)')
                ->and($mobileBanking->getBankName(MobileBanking::BANK_KBANK))->toBe('KBank (K PLUS)')
                ->and($mobileBanking->getBankName(MobileBanking::BANK_KTB))->toBe('Krungthai Bank (KTB NEXT)')
                ->and($mobileBanking->getBankName(MobileBanking::BANK_BAY))->toBe('Bank of Ayudhya (KMA)')
                ->and($mobileBanking->getBankName(MobileBanking::BANK_SCB))->toBe('Siam Commercial Bank (SCB Easy)')
                ->and($mobileBanking->getBankName(MobileBanking::BANK_OCBC))->toBe('OCBC Digital');
        });

        it('returns correct bank codes', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getBankCode(MobileBanking::BANK_BBL))->toBe('BBL')
                ->and($mobileBanking->getBankCode(MobileBanking::BANK_KBANK))->toBe('KBANK')
                ->and($mobileBanking->getBankCode(MobileBanking::BANK_KTB))->toBe('KTB')
                ->and($mobileBanking->getBankCode(MobileBanking::BANK_BAY))->toBe('BAY')
                ->and($mobileBanking->getBankCode(MobileBanking::BANK_SCB))->toBe('SCB')
                ->and($mobileBanking->getBankCode(MobileBanking::BANK_OCBC))->toBe('OCBC');
        });

        it('checks if bank is supported', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->isSupportedBank(MobileBanking::BANK_KBANK))->toBeTrue()
                ->and($mobileBanking->isSupportedBank(MobileBanking::BANK_OCBC))->toBeTrue()
                ->and($mobileBanking->isSupportedBank('invalid_bank'))->toBeFalse();
        });

        it('returns bank type for unknown bank', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getBankName('unknown_bank'))->toBe('unknown_bank')
                ->and($mobileBanking->getBankCode('unknown_bank'))->toBe('unknown_bank');
        });
    });

    describe('bank currencies', function () {
        it('returns THB for Thai banks', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getCurrency(MobileBanking::BANK_BBL))->toBe('THB')
                ->and($mobileBanking->getCurrency(MobileBanking::BANK_KBANK))->toBe('THB')
                ->and($mobileBanking->getCurrency(MobileBanking::BANK_KTB))->toBe('THB')
                ->and($mobileBanking->getCurrency(MobileBanking::BANK_BAY))->toBe('THB')
                ->and($mobileBanking->getCurrency(MobileBanking::BANK_SCB))->toBe('THB');
        });

        it('returns SGD for OCBC', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getCurrency(MobileBanking::BANK_OCBC))->toBe('SGD');
        });
    });

    describe('bank countries', function () {
        it('returns Thailand for Thai banks', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getCountry(MobileBanking::BANK_KBANK))->toBe('Thailand')
                ->and($mobileBanking->getCountry(MobileBanking::BANK_BBL))->toBe('Thailand');
        });

        it('returns Singapore for OCBC', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getCountry(MobileBanking::BANK_OCBC))->toBe('Singapore');
        });
    });

    describe('amount limits for Thai banks', function () {
        it('has correct minimum amount (20 THB)', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getMinimumAmount(MobileBanking::BANK_KBANK))->toBe(2000)
                ->and($mobileBanking->getMinimumAmount(MobileBanking::BANK_BBL))->toBe(2000)
                ->and($mobileBanking->getMinimumInMainUnit(MobileBanking::BANK_KBANK))->toBe(20.0);
        });

        it('has correct maximum amount (150,000 THB)', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getMaximumAmount(MobileBanking::BANK_KBANK))->toBe(15000000)
                ->and($mobileBanking->getMaximumInMainUnit(MobileBanking::BANK_KBANK))->toBe(150000.0);
        });
    });

    describe('amount limits for OCBC', function () {
        it('has correct minimum amount (1 SGD)', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getMinimumAmount(MobileBanking::BANK_OCBC))->toBe(100)
                ->and($mobileBanking->getMinimumInMainUnit(MobileBanking::BANK_OCBC))->toBe(1.0);
        });

        it('has correct maximum amount (20,000 SGD)', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getMaximumAmount(MobileBanking::BANK_OCBC))->toBe(2000000)
                ->and($mobileBanking->getMaximumInMainUnit(MobileBanking::BANK_OCBC))->toBe(20000.0);
        });
    });

    describe('amount validation', function () {
        it('validates amount within limits for Thai banks', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->validateAmount(MobileBanking::BANK_KBANK, 2000))->toBeTrue()
                ->and($mobileBanking->validateAmount(MobileBanking::BANK_KBANK, 15000000))->toBeTrue();
        });

        it('throws exception for amount below minimum', function () {
            $mobileBanking = createMobileBanking();

            expect(fn() => $mobileBanking->validateAmount(MobileBanking::BANK_KBANK, 1999))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('throws exception for amount above maximum', function () {
            $mobileBanking = createMobileBanking();

            expect(fn() => $mobileBanking->validateAmount(MobileBanking::BANK_KBANK, 15000001))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('validates OCBC amount within SGD limits', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->validateAmount(MobileBanking::BANK_OCBC, 100))->toBeTrue()
                ->and($mobileBanking->validateAmount(MobileBanking::BANK_OCBC, 2000000))->toBeTrue();
        });
    });

    describe('bank validation', function () {
        it('validates supported bank', function () {
            $mobileBanking = createMobileBanking();

            expect(fn() => $mobileBanking->validateBank(MobileBanking::BANK_KBANK))->not->toThrow(\Exception::class);
        });

        it('throws exception for unsupported bank', function () {
            $mobileBanking = createMobileBanking();

            expect(fn() => $mobileBanking->validateBank('invalid_bank'))
                ->toThrow(\InvalidArgumentException::class, 'Unsupported bank type: invalid_bank');
        });
    });

    describe('charge status helpers', function () {
        it('detects pending charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'pending',
                'authorize_uri' => 'https://bank.example.com/authorize',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->isPending($charge))->toBeTrue()
                ->and($mobileBanking->isPendingRedirect($charge))->toBeTrue()
                ->and($mobileBanking->isSuccessful($charge))->toBeFalse()
                ->and($mobileBanking->isFailed($charge))->toBeFalse()
                ->and($mobileBanking->isExpired($charge))->toBeFalse();
        });

        it('detects successful charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'successful',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->isSuccessful($charge))->toBeTrue()
                ->and($mobileBanking->isPending($charge))->toBeFalse();
        });

        it('detects failed charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'failed',
                'failure_code' => 'payment_rejected',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->isFailed($charge))->toBeTrue()
                ->and($mobileBanking->getFailureCode($charge))->toBe('payment_rejected');
        });

        it('detects expired charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'expired',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->isExpired($charge))->toBeTrue();
        });
    });

    describe('authorize and return URI', function () {
        it('extracts authorize URI from charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'pending',
                'authorize_uri' => 'https://kbank.example.com/authorize/abc123',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getAuthorizeUri($charge))->toBe('https://kbank.example.com/authorize/abc123');
        });

        it('extracts return URI from charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'return_uri' => 'https://your-site.com/callback',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getReturnUri($charge))->toBe('https://your-site.com/callback');
        });
    });

    describe('failure messages', function () {
        it('returns Thai failure message for payment_rejected', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'payment_rejected',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getFailureMessage($charge, 'th'))->toBe('ธนาคารปฏิเสธการชำระเงิน');
        });

        it('returns English failure message for payment_rejected', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'payment_rejected',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getFailureMessage($charge, 'en'))->toBe('Payment rejected by bank');
        });

        it('returns Thai failure message for insufficient_fund', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'insufficient_fund',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getFailureMessage($charge, 'th'))->toBe('ยอดเงินไม่เพียงพอ');
        });

        it('returns Thai failure message for insufficient_balance', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'insufficient_balance',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getFailureMessage($charge, 'th'))->toBe('ยอดเงินไม่เพียงพอ');
        });

        it('returns Thai failure message for payment_expired', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'payment_expired',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getFailureMessage($charge, 'th'))->toBe('หมดเวลาในการชำระเงิน');
        });

        it('returns Thai failure message for payment_cancelled', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'payment_cancelled',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getFailureMessage($charge, 'th'))->toBe('ผู้ซื้อยกเลิกการชำระเงิน');
        });

        it('returns Thai failure message for invalid_account', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'invalid_account',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getFailureMessage($charge, 'th'))->toBe('บัญชีไม่ถูกต้องหรือไม่พบ');
        });

        it('returns Thai failure message for failed_processing', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'failed_processing',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getFailureMessage($charge, 'th'))->toBe('ระบบทำรายการไม่สำเร็จ');
        });

        it('returns API failure_message for unknown code', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'unknown_error',
                'failure_message' => 'Something went wrong',
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getFailureMessage($charge, 'en'))->toBe('Something went wrong');
        });
    });

    describe('refundability', function () {
        it('indicates Thai banks cannot be refunded', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->canRefund(MobileBanking::BANK_KBANK))->toBeFalse()
                ->and($mobileBanking->canRefund(MobileBanking::BANK_BBL))->toBeFalse()
                ->and($mobileBanking->canRefund(MobileBanking::BANK_KTB))->toBeFalse()
                ->and($mobileBanking->canRefund(MobileBanking::BANK_BAY))->toBeFalse()
                ->and($mobileBanking->canRefund(MobileBanking::BANK_SCB))->toBeFalse();
        });

        it('indicates OCBC can be refunded', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->canRefund(MobileBanking::BANK_OCBC))->toBeTrue();
        });

        it('checks OCBC charge refund eligibility within 180 days', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-90 days')),
                'source' => [
                    'type' => 'mobile_banking_ocbc',
                ],
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->canRefundCharge($charge))->toBeTrue();
        });

        it('rejects OCBC charge refund after 180 days', function () {
            $charge = new Response([
                'status' => 'successful',
                'created_at' => date('c', strtotime('-181 days')),
                'source' => [
                    'type' => 'mobile_banking_ocbc',
                ],
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->canRefundCharge($charge))->toBeFalse();
        });

        it('rejects refund for non-successful charges', function () {
            $charge = new Response([
                'status' => 'pending',
                'source' => [
                    'type' => 'mobile_banking_ocbc',
                ],
            ]);

            $mobileBanking = createMobileBanking();

            expect($mobileBanking->canRefundCharge($charge))->toBeFalse();
        });
    });

    describe('expiration times', function () {
        it('returns correct expiration times', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->getExpirationTime(MobileBanking::BANK_BBL))->toBe('15 minutes')
                ->and($mobileBanking->getExpirationTime(MobileBanking::BANK_KBANK))->toBe('10 minutes')
                ->and($mobileBanking->getExpirationTime(MobileBanking::BANK_KTB))->toBe('30 minutes')
                ->and($mobileBanking->getExpirationTime(MobileBanking::BANK_BAY))->toBe('15 minutes')
                ->and($mobileBanking->getExpirationTime(MobileBanking::BANK_SCB))->toBe('7 days')
                ->and($mobileBanking->getExpirationTime(MobileBanking::BANK_OCBC))->toBe('varies');
        });
    });

    describe('currency conversion', function () {
        it('converts THB to satang for Thai banks', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->toSmallestUnit(MobileBanking::BANK_KBANK, 100.00))->toBe(10000)
                ->and($mobileBanking->toSmallestUnit(MobileBanking::BANK_KBANK, 20.00))->toBe(2000);
        });

        it('converts satang to THB for Thai banks', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->toMainUnit(MobileBanking::BANK_KBANK, 10000))->toBe(100.0)
                ->and($mobileBanking->toMainUnit(MobileBanking::BANK_KBANK, 2000))->toBe(20.0);
        });

        it('converts SGD to cents for OCBC', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->toSmallestUnit(MobileBanking::BANK_OCBC, 100.00))->toBe(10000)
                ->and($mobileBanking->toSmallestUnit(MobileBanking::BANK_OCBC, 1.00))->toBe(100);
        });

        it('converts cents to SGD for OCBC', function () {
            $mobileBanking = createMobileBanking();

            expect($mobileBanking->toMainUnit(MobileBanking::BANK_OCBC, 10000))->toBe(100.0)
                ->and($mobileBanking->toMainUnit(MobileBanking::BANK_OCBC, 100))->toBe(1.0);
        });
    });
});

/**
 * Helper function to create a MobileBanking instance with mocked dependencies.
 */
function createMobileBanking(): MobileBanking
{
    $chargeApi = Mockery::mock(Charge::class);
    $sourceApi = Mockery::mock(Source::class);

    return new MobileBanking($chargeApi, $sourceApi);
}
