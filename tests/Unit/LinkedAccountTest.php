<?php

use Omise\Api\LinkedAccount;
use Omise\Http\Response;

describe('LinkedAccount', function () {
    describe('bank type constants', function () {
        it('has correct bank type constants', function () {
            expect(LinkedAccount::TYPE_BAY)->toBe('direct_debit_bay')
                ->and(LinkedAccount::TYPE_KBANK)->toBe('direct_debit_kbank')
                ->and(LinkedAccount::TYPE_KTB)->toBe('direct_debit_ktb')
                ->and(LinkedAccount::TYPE_SCB)->toBe('direct_debit_scb');
        });
    });

    describe('status constants', function () {
        it('has correct status constants', function () {
            expect(LinkedAccount::STATUS_PENDING)->toBe('pending')
                ->and(LinkedAccount::STATUS_SUCCESSFUL)->toBe('successful')
                ->and(LinkedAccount::STATUS_FAILED)->toBe('failed')
                ->and(LinkedAccount::STATUS_DELETED)->toBe('deleted');
        });
    });

    describe('supported banks', function () {
        it('returns all supported bank types', function () {
            $linkedAccount = createLinkedAccount();

            $banks = $linkedAccount->getSupportedBanks();

            expect($banks)->toBeArray()
                ->and($banks)->toHaveCount(4)
                ->and($banks)->toContain(LinkedAccount::TYPE_BAY)
                ->and($banks)->toContain(LinkedAccount::TYPE_KBANK)
                ->and($banks)->toContain(LinkedAccount::TYPE_KTB)
                ->and($banks)->toContain(LinkedAccount::TYPE_SCB);
        });

        it('returns correct bank names', function () {
            $linkedAccount = createLinkedAccount();

            expect($linkedAccount->getBankName(LinkedAccount::TYPE_BAY))->toBe('Bank of Ayudhya (Krungsri)')
                ->and($linkedAccount->getBankName(LinkedAccount::TYPE_KBANK))->toBe('Kasikorn Bank')
                ->and($linkedAccount->getBankName(LinkedAccount::TYPE_KTB))->toBe('Krungthai Bank')
                ->and($linkedAccount->getBankName(LinkedAccount::TYPE_SCB))->toBe('Siam Commercial Bank');
        });

        it('returns type as bank name for unknown type', function () {
            $linkedAccount = createLinkedAccount();

            expect($linkedAccount->getBankName('unknown_bank'))->toBe('unknown_bank');
        });

        it('checks if bank type is supported', function () {
            $linkedAccount = createLinkedAccount();

            expect($linkedAccount->isSupportedBank(LinkedAccount::TYPE_KBANK))->toBeTrue()
                ->and($linkedAccount->isSupportedBank(LinkedAccount::TYPE_BAY))->toBeTrue()
                ->and($linkedAccount->isSupportedBank('invalid_type'))->toBeFalse();
        });
    });

    describe('status helpers', function () {
        it('detects pending status', function () {
            $linkedAccount = createLinkedAccount();
            $response = new Response(['status' => 'pending']);

            expect($linkedAccount->isPending($response))->toBeTrue()
                ->and($linkedAccount->isSuccessful($response))->toBeFalse()
                ->and($linkedAccount->isFailed($response))->toBeFalse()
                ->and($linkedAccount->isDeleted($response))->toBeFalse();
        });

        it('detects successful status', function () {
            $linkedAccount = createLinkedAccount();
            $response = new Response(['status' => 'successful']);

            expect($linkedAccount->isSuccessful($response))->toBeTrue()
                ->and($linkedAccount->isPending($response))->toBeFalse()
                ->and($linkedAccount->isFailed($response))->toBeFalse()
                ->and($linkedAccount->isDeleted($response))->toBeFalse();
        });

        it('detects failed status', function () {
            $linkedAccount = createLinkedAccount();
            $response = new Response(['status' => 'failed']);

            expect($linkedAccount->isFailed($response))->toBeTrue()
                ->and($linkedAccount->isPending($response))->toBeFalse()
                ->and($linkedAccount->isSuccessful($response))->toBeFalse()
                ->and($linkedAccount->isDeleted($response))->toBeFalse();
        });

        it('detects deleted status', function () {
            $linkedAccount = createLinkedAccount();
            $response = new Response(['status' => 'deleted']);

            expect($linkedAccount->isDeleted($response))->toBeTrue()
                ->and($linkedAccount->isPending($response))->toBeFalse()
                ->and($linkedAccount->isSuccessful($response))->toBeFalse()
                ->and($linkedAccount->isFailed($response))->toBeFalse();
        });
    });

    describe('response helpers', function () {
        it('extracts registration URI from response', function () {
            $linkedAccount = createLinkedAccount();
            $response = new Response([
                'status' => 'pending',
                'registration_uri' => 'https://bank.example.com/register/abc123',
            ]);

            expect($linkedAccount->getRegistrationUri($response))
                ->toBe('https://bank.example.com/register/abc123');
        });

        it('returns null for missing registration URI', function () {
            $linkedAccount = createLinkedAccount();
            $response = new Response(['status' => 'successful']);

            expect($linkedAccount->getRegistrationUri($response))->toBeNull();
        });

        it('extracts type from response', function () {
            $linkedAccount = createLinkedAccount();
            $response = new Response(['type' => 'direct_debit_kbank']);

            expect($linkedAccount->getType($response))->toBe('direct_debit_kbank');
        });

        it('extracts status from response', function () {
            $linkedAccount = createLinkedAccount();
            $response = new Response(['status' => 'pending']);

            expect($linkedAccount->getStatus($response))->toBe('pending');
        });
    });

    describe('parameter validation', function () {
        it('throws exception for missing type', function () {
            $linkedAccount = createLinkedAccountForValidation();

            expect(fn () => $linkedAccount->create(['return_uri' => 'https://example.com']))
                ->toThrow(\InvalidArgumentException::class, 'type is required');
        });

        it('throws exception for invalid bank type', function () {
            $linkedAccount = createLinkedAccountForValidation();

            expect(fn () => $linkedAccount->create([
                'type' => 'invalid_bank',
                'return_uri' => 'https://example.com',
            ]))->toThrow(\InvalidArgumentException::class, 'Invalid bank type');
        });

        it('throws exception for missing return_uri', function () {
            $linkedAccount = createLinkedAccountForValidation();

            expect(fn () => $linkedAccount->create(['type' => LinkedAccount::TYPE_KBANK]))
                ->toThrow(\InvalidArgumentException::class, 'return_uri is required');
        });
    });
});

/**
 * Helper function to create a LinkedAccount instance with mocked HTTP client.
 */
function createLinkedAccount(): LinkedAccount
{
    $httpClient = Mockery::mock(\Omise\Http\HttpClient::class);

    return new LinkedAccount($httpClient);
}

/**
 * Helper function to create LinkedAccount for validation tests.
 */
function createLinkedAccountForValidation(): LinkedAccount
{
    $httpClient = Mockery::mock(\Omise\Http\HttpClient::class);

    return new LinkedAccount($httpClient);
}
