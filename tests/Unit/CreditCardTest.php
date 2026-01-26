<?php

use Omise\Http\Response;
use Omise\PaymentMethods\CreditCard;

describe('CreditCard', function () {
    describe('charge status helpers', function () {
        it('detects pending charge', function () {
            $charge = new Response([
                'status' => 'pending',
                'authorize_uri' => 'https://pay.omise.co/...',
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->isPending($charge))->toBeTrue()
                ->and($creditCard->requires3DSecure($charge))->toBeTrue()
                ->and($creditCard->isSuccessful($charge))->toBeFalse();
        });

        it('detects successful charge', function () {
            $charge = new Response([
                'status' => 'successful',
                'authorized' => true,
                'captured' => true,
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->isSuccessful($charge))->toBeTrue()
                ->and($creditCard->isPending($charge))->toBeFalse()
                ->and($creditCard->isFailed($charge))->toBeFalse();
        });

        it('detects failed charge', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'insufficient_fund',
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->isFailed($charge))->toBeTrue()
                ->and($creditCard->getFailureCode($charge))->toBe('insufficient_fund');
        });

        it('detects authorized but uncaptured charge', function () {
            $charge = new Response([
                'status' => 'pending',
                'authorized' => true,
                'captured' => false,
                'capturable' => true,
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->isAuthorized($charge))->toBeTrue()
                ->and($creditCard->isCapturable($charge))->toBeTrue();
        });

        it('detects reversed charge', function () {
            $charge = new Response([
                'status' => 'reversed',
                'reversed' => true,
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->isReversed($charge))->toBeTrue();
        });
    });

    describe('3D Secure', function () {
        it('extracts authorize_uri from charge', function () {
            $charge = new Response([
                'status' => 'pending',
                'authorize_uri' => 'https://pay.omise.co/3ds/123',
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->getAuthorizeUri($charge))->toBe('https://pay.omise.co/3ds/123');
        });

        it('detects when 3DS is required', function () {
            $charge = new Response([
                'status' => 'pending',
                'authorize_uri' => 'https://pay.omise.co/3ds/123',
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->requires3DSecure($charge))->toBeTrue();
        });

        it('detects when 3DS is not required', function () {
            $charge = new Response([
                'status' => 'successful',
                'authorize_uri' => null,
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->requires3DSecure($charge))->toBeFalse();
        });
    });

    describe('failure messages', function () {
        it('returns Thai failure message for insufficient_fund', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'insufficient_fund',
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->getFailureMessage($charge, 'th'))->toBe('วงเงินไม่เพียงพอ');
        });

        it('returns English failure message for insufficient_fund', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'insufficient_fund',
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->getFailureMessage($charge, 'en'))->toBe('Insufficient funds');
        });

        it('returns Thai failure message for invalid_card', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'invalid_card',
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->getFailureMessage($charge, 'th'))->toBe('บัตรไม่ถูกต้อง');
        });

        it('returns Thai failure message for stolen_or_lost_card', function () {
            $charge = new Response([
                'status' => 'failed',
                'failure_code' => 'stolen_or_lost_card',
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->getFailureMessage($charge, 'th'))->toBe('บัตรถูกแจ้งหาย');
        });
    });

    describe('card information', function () {
        it('extracts card info from charge', function () {
            $charge = new Response([
                'card' => [
                    'id' => 'card_test_123',
                    'brand' => 'Visa',
                    'last_digits' => '4242',
                ],
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->getCardInfo($charge))->toBeArray()
                ->and($creditCard->getCardBrand($charge))->toBe('Visa')
                ->and($creditCard->getCardLastDigits($charge))->toBe('4242');
        });
    });

    describe('multi-currency', function () {
        it('detects multi-currency charge', function () {
            $charge = new Response([
                'amount' => 10000,
                'currency' => 'USD',
                'funding_amount' => 350000,
                'funding_currency' => 'THB',
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->isMultiCurrency($charge))->toBeTrue()
                ->and($creditCard->getFundingAmount($charge))->toBe(350000)
                ->and($creditCard->getFundingCurrency($charge))->toBe('THB');
        });

        it('detects single-currency charge', function () {
            $charge = new Response([
                'amount' => 10000,
                'currency' => 'THB',
                'funding_amount' => 10000,
                'funding_currency' => 'THB',
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->isMultiCurrency($charge))->toBeFalse();
        });
    });

    describe('fees and net amount', function () {
        it('extracts fee and net amount', function () {
            $charge = new Response([
                'amount' => 10000,
                'net' => 9650,
                'fee' => 350,
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->getNetAmount($charge))->toBe(9650)
                ->and($creditCard->getFee($charge))->toBe(350);
        });
    });

    describe('refundability', function () {
        it('detects refundable charge', function () {
            $charge = new Response([
                'status' => 'successful',
                'refundable' => true,
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->isRefundable($charge))->toBeTrue();
        });

        it('detects non-refundable charge', function () {
            $charge = new Response([
                'status' => 'successful',
                'refundable' => false,
            ]);

            $creditCard = createCreditCard();

            expect($creditCard->isRefundable($charge))->toBeFalse();
        });
    });

    describe('amount validation', function () {
        it('validates THB amounts', function () {
            $creditCard = createCreditCard();

            expect($creditCard->validateAmount(2000, 'THB'))->toBeTrue()
                ->and($creditCard->validateAmount(1000, 'THB'))->toBeFalse();
        });

        it('returns correct limits', function () {
            $creditCard = createCreditCard();

            expect($creditCard->getMinimumAmount('THB'))->toBe(2000)
                ->and($creditCard->getMaximumAmount('THB'))->toBe(15000000);
        });
    });

    describe('card brand constants', function () {
        it('has correct brand constants', function () {
            expect(CreditCard::BRAND_VISA)->toBe('Visa')
                ->and(CreditCard::BRAND_MASTERCARD)->toBe('MasterCard')
                ->and(CreditCard::BRAND_JCB)->toBe('JCB')
                ->and(CreditCard::BRAND_AMEX)->toBe('American Express');
        });
    });

    describe('failure code constants', function () {
        it('has correct failure code constants', function () {
            expect(CreditCard::FAILURE_INVALID_CARD)->toBe('invalid_card')
                ->and(CreditCard::FAILURE_INSUFFICIENT_FUND)->toBe('insufficient_fund')
                ->and(CreditCard::FAILURE_STOLEN_OR_LOST_CARD)->toBe('stolen_or_lost_card')
                ->and(CreditCard::FAILURE_PAYMENT_REJECTED)->toBe('payment_rejected');
        });
    });
});

/**
 * Helper function to create a CreditCard instance with mocked dependencies.
 */
function createCreditCard(): CreditCard
{
    $chargeApi = Mockery::mock(\Omise\Api\Charge::class);
    $tokenApi = Mockery::mock(\Omise\Api\Token::class);
    $customerApi = Mockery::mock(\Omise\Api\Customer::class);

    return new CreditCard($chargeApi, $tokenApi, $customerApi);
}
