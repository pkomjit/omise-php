<?php

use Omise\Http\Response;
use Omise\PaymentMethods\DirectDebit;

describe('DirectDebit', function () {
    describe('bank constants', function () {
        it('has correct bank type constants', function () {
            expect(DirectDebit::BANK_BAY)->toBe('direct_debit_bay')
                ->and(DirectDebit::BANK_KBANK)->toBe('direct_debit_kbank')
                ->and(DirectDebit::BANK_KTB)->toBe('direct_debit_ktb')
                ->and(DirectDebit::BANK_SCB)->toBe('direct_debit_scb');
        });

        it('has correct failure code constants', function () {
            expect(DirectDebit::FAILURE_PROCESSING)->toBe('failed_processing')
                ->and(DirectDebit::FAILURE_INVALID_ACCOUNT)->toBe('invalid_account')
                ->and(DirectDebit::FAILURE_REGISTRATION_REJECTED)->toBe('registration_rejected')
                ->and(DirectDebit::FAILURE_INSUFFICIENT_FUND)->toBe('insufficient_fund')
                ->and(DirectDebit::FAILURE_RATE_LIMIT)->toBe('rate_limit_exceeded');
        });
    });

    describe('supported banks', function () {
        it('returns all supported banks', function () {
            $directDebit = createDirectDebit();

            $banks = $directDebit->getSupportedBanks();

            expect($banks)->toBeArray()
                ->and($banks)->toHaveCount(4)
                ->and($banks)->toHaveKey(DirectDebit::BANK_BAY)
                ->and($banks)->toHaveKey(DirectDebit::BANK_KBANK)
                ->and($banks)->toHaveKey(DirectDebit::BANK_KTB)
                ->and($banks)->toHaveKey(DirectDebit::BANK_SCB);
        });

        it('returns correct bank names', function () {
            $directDebit = createDirectDebit();

            expect($directDebit->getBankName(DirectDebit::BANK_BAY))->toBe('Bank of Ayudhya (Krungsri)')
                ->and($directDebit->getBankName(DirectDebit::BANK_KBANK))->toBe('Kasikorn Bank')
                ->and($directDebit->getBankName(DirectDebit::BANK_KTB))->toBe('Krungthai Bank')
                ->and($directDebit->getBankName(DirectDebit::BANK_SCB))->toBe('Siam Commercial Bank');
        });

        it('returns correct bank codes', function () {
            $directDebit = createDirectDebit();

            expect($directDebit->getBankCode(DirectDebit::BANK_BAY))->toBe('BAY')
                ->and($directDebit->getBankCode(DirectDebit::BANK_KBANK))->toBe('KBANK')
                ->and($directDebit->getBankCode(DirectDebit::BANK_KTB))->toBe('KTB')
                ->and($directDebit->getBankCode(DirectDebit::BANK_SCB))->toBe('SCB');
        });

        it('checks if bank is supported', function () {
            $directDebit = createDirectDebit();

            expect($directDebit->isSupportedBank(DirectDebit::BANK_KBANK))->toBeTrue()
                ->and($directDebit->isSupportedBank('invalid_bank'))->toBeFalse();
        });
    });

    describe('linked account status helpers', function () {
        it('detects pending linked account', function () {
            $linkedAccount = new Response([
                'object' => 'linked_account',
                'id' => 'lacct_test_123',
                'status' => 'pending',
                'registration_uri' => 'https://bank.example.com/register',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->isLinkedAccountPending($linkedAccount))->toBeTrue()
                ->and($directDebit->isLinkedAccountSuccessful($linkedAccount))->toBeFalse()
                ->and($directDebit->isLinkedAccountFailed($linkedAccount))->toBeFalse();
        });

        it('detects successful linked account', function () {
            $linkedAccount = new Response([
                'object' => 'linked_account',
                'id' => 'lacct_test_123',
                'status' => 'successful',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->isLinkedAccountSuccessful($linkedAccount))->toBeTrue()
                ->and($directDebit->isLinkedAccountPending($linkedAccount))->toBeFalse()
                ->and($directDebit->isLinkedAccountFailed($linkedAccount))->toBeFalse();
        });

        it('detects failed linked account', function () {
            $linkedAccount = new Response([
                'object' => 'linked_account',
                'id' => 'lacct_test_123',
                'status' => 'failed',
                'failure_code' => 'registration_rejected',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->isLinkedAccountFailed($linkedAccount))->toBeTrue()
                ->and($directDebit->isLinkedAccountPending($linkedAccount))->toBeFalse()
                ->and($directDebit->isLinkedAccountSuccessful($linkedAccount))->toBeFalse();
        });
    });

    describe('charge status helpers', function () {
        it('detects pending charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'pending',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->isPending($charge))->toBeTrue()
                ->and($directDebit->isSuccessful($charge))->toBeFalse()
                ->and($directDebit->isFailed($charge))->toBeFalse();
        });

        it('detects successful charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'successful',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->isSuccessful($charge))->toBeTrue()
                ->and($directDebit->isPending($charge))->toBeFalse()
                ->and($directDebit->isFailed($charge))->toBeFalse();
        });

        it('detects failed charge', function () {
            $charge = new Response([
                'object' => 'charge',
                'status' => 'failed',
                'failure_code' => 'insufficient_fund',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->isFailed($charge))->toBeTrue()
                ->and($directDebit->getFailureCode($charge))->toBe('insufficient_fund');
        });
    });

    describe('failure messages', function () {
        it('returns Thai failure message for insufficient_fund', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'insufficient_fund',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->getFailureMessage($charge, 'th'))->toBe('ยอดเงินไม่เพียงพอ');
        });

        it('returns English failure message for insufficient_fund', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'insufficient_fund',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->getFailureMessage($charge, 'en'))->toBe('Insufficient funds or limit exceeded');
        });

        it('returns Thai failure message for invalid_account', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'invalid_account',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->getFailureMessage($charge, 'th'))->toBe('บัญชีไม่ถูกต้องหรือไม่พบ');
        });

        it('returns English failure message for invalid_account', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'invalid_account',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->getFailureMessage($charge, 'en'))->toBe('Account information invalid or not found');
        });

        it('returns Thai failure message for registration_rejected', function () {
            $linkedAccount = new Response([
                'status' => 'failed',
                'failure_code' => 'registration_rejected',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->getFailureMessage($linkedAccount, 'th'))->toBe('ธนาคารปฏิเสธการลงทะเบียน');
        });

        it('returns Thai failure message for failed_processing', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'failed_processing',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->getFailureMessage($charge, 'th'))->toBe('ระบบทำรายการไม่สำเร็จ');
        });

        it('returns Thai failure message for rate_limit_exceeded', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'rate_limit_exceeded',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->getFailureMessage($charge, 'th'))->toBe('มีการทำรายการมากเกินไป กรุณารอสักครู่');
        });

        it('returns API failure_message for unknown code', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'unknown_error',
                'failure_message' => 'Something went wrong',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->getFailureMessage($charge, 'en'))->toBe('Something went wrong');
        });
    });

    describe('amount validation', function () {
        it('validates minimum amount', function () {
            $directDebit = createDirectDebit();

            expect($directDebit->validateAmount(2000))->toBeTrue();
        });

        it('validates maximum amount', function () {
            $directDebit = createDirectDebit();

            expect($directDebit->validateAmount(15000000))->toBeTrue();
        });

        it('throws exception for amount below minimum', function () {
            $directDebit = createDirectDebit();

            expect(fn () => $directDebit->validateAmount(1999))
                ->toThrow(\InvalidArgumentException::class, 'Amount must be at least 2000 satang (20 THB)');
        });

        it('throws exception for amount above maximum', function () {
            $directDebit = createDirectDebit();

            expect(fn () => $directDebit->validateAmount(15000001))
                ->toThrow(\InvalidArgumentException::class, 'Amount must not exceed 15000000 satang (150,000 THB)');
        });

        it('validates THB amount', function () {
            $directDebit = createDirectDebit();

            expect($directDebit->validateThbAmount(20.00))->toBeTrue()
                ->and($directDebit->validateThbAmount(150000.00))->toBeTrue();
        });
    });

    describe('amount limits', function () {
        it('returns correct minimum amount in satang', function () {
            $directDebit = createDirectDebit();

            expect($directDebit->getMinimumAmount())->toBe(2000);
        });

        it('returns correct maximum amount in satang', function () {
            $directDebit = createDirectDebit();

            expect($directDebit->getMaximumAmount())->toBe(15000000);
        });

        it('returns correct minimum amount in THB', function () {
            $directDebit = createDirectDebit();

            expect($directDebit->getMinimumThb())->toBe(20.0);
        });

        it('returns correct maximum amount in THB', function () {
            $directDebit = createDirectDebit();

            expect($directDebit->getMaximumThb())->toBe(150000.0);
        });
    });

    describe('currency conversion', function () {
        it('converts THB to satang', function () {
            expect(DirectDebit::toSatang(100.00))->toBe(10000)
                ->and(DirectDebit::toSatang(20.50))->toBe(2050);
        });

        it('converts satang to THB', function () {
            expect(DirectDebit::toThb(10000))->toBe(100.0)
                ->and(DirectDebit::toThb(2050))->toBe(20.5);
        });
    });

    describe('refundability', function () {
        it('indicates Direct Debit charges cannot be refunded', function () {
            $directDebit = createDirectDebit();

            expect($directDebit->canRefund())->toBeFalse();
        });
    });

    describe('registration URI', function () {
        it('extracts registration URI from linked account', function () {
            $linkedAccount = new Response([
                'object' => 'linked_account',
                'id' => 'lacct_test_123',
                'status' => 'pending',
                'registration_uri' => 'https://bank.example.com/register/abc123',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->getRegistrationUri($linkedAccount))
                ->toBe('https://bank.example.com/register/abc123');
        });

        it('returns null when registration URI not present', function () {
            $linkedAccount = new Response([
                'object' => 'linked_account',
                'id' => 'lacct_test_123',
                'status' => 'successful',
            ]);

            $directDebit = createDirectDebit();

            expect($directDebit->getRegistrationUri($linkedAccount))->toBeNull();
        });
    });
});

/**
 * Helper function to create a DirectDebit instance with mocked dependencies.
 */
function createDirectDebit(): DirectDebit
{
    $chargeApi = Mockery::mock(\Omise\Api\Charge::class);
    $customerApi = Mockery::mock(\Omise\Api\Customer::class);
    $linkedAccountApi = Mockery::mock(\Omise\Api\LinkedAccount::class);

    // Set up default mock behavior for status checks
    $linkedAccountApi->shouldReceive('isPending')->andReturnUsing(function ($response) {
        return $response->get('status') === 'pending';
    });
    $linkedAccountApi->shouldReceive('isSuccessful')->andReturnUsing(function ($response) {
        return $response->get('status') === 'successful';
    });
    $linkedAccountApi->shouldReceive('isFailed')->andReturnUsing(function ($response) {
        return $response->get('status') === 'failed';
    });
    $linkedAccountApi->shouldReceive('getRegistrationUri')->andReturnUsing(function ($response) {
        return $response->get('registration_uri');
    });

    return new DirectDebit($chargeApi, $customerApi, $linkedAccountApi);
}
