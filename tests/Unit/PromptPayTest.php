<?php

use Omise\Config;
use Omise\Omise;
use Omise\PaymentMethods\PromptPay;

describe('PromptPay', function () {
    describe('currency conversion', function () {
        it('converts THB to satang', function () {
            expect(PromptPay::toSatang(100.00))->toBe(10000)
                ->and(PromptPay::toSatang(20.00))->toBe(2000)
                ->and(PromptPay::toSatang(150000.00))->toBe(15000000)
                ->and(PromptPay::toSatang(99.99))->toBe(9999);
        });

        it('converts satang to THB', function () {
            expect(PromptPay::toThb(10000))->toBe(100.00)
                ->and(PromptPay::toThb(2000))->toBe(20.00)
                ->and(PromptPay::toThb(15000000))->toBe(150000.00)
                ->and(PromptPay::toThb(9999))->toBe(99.99);
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

    describe('QR code', function () {
        it('has correct QR code', function () {
            $chargeArray = json_decode('{"object":"charge","id":"chrg_test_66hruyhgfugvrowlvke","location":"/charges/chrg_test_66hruyhgfugvrowlvke","amount":2000,"acquirer_reference_number":null,"net":1965,"fee":33,"fee_vat":2,"interest":0,"interest_vat":0,"funding_amount":2000,"refunded_amount":0,"transaction_fees":{"fee_flat":"0.0","fee_rate":"1.65","vat_rate":"7.0"},"platform_fee":{"fixed":null,"amount":null,"percentage":null},"currency":"THB","funding_currency":"THB","ip":null,"refunds":{"object":"list","data":[],"limit":20,"offset":0,"total":0,"location":"/charges/chrg_test_66hruyhgfugvrowlvke/refunds","order":"chronological","from":"1970-01-01T00:00:00Z","to":"2026-01-25T10:26:26Z"},"link":null,"description":null,"metadata":[],"card":null,"source":{"object":"source","id":"src_test_66hruyfw0f5dljoll6n","livemode":false,"location":"/sources/src_test_66hruyfw0f5dljoll6n","amount":2000,"barcode":null,"bank":null,"created_at":"2026-01-25T10:26:26Z","currency":"THB","email":null,"flow":"offline","installment_term":null,"ip":"184.22.19.156","absorption_type":null,"name":null,"mobile_number":null,"phone_number":null,"platform_type":null,"scannable_code":{"object":"barcode","type":"qr","image":{"object":"document","livemode":false,"id":"docu_test_66hruyiz5s3mlx7oru0","deleted":false,"filename":"qrcode.svg","location":"/charges/chrg_test_66hruyhgfugvrowlvke/documents/docu_test_66hruyiz5s3mlx7oru0","kind":"qr","download_uri":"https://api.omise.co/charges/chrg_test_66hruyhgfugvrowlvke/documents/docu_test_66hruyiz5s3mlx7oru0/downloads/4650066FE872354F","created_at":"2026-01-25T10:26:26Z"},"raw_data":null},"qr_settings":null,"billing":null,"shipping":null,"items":[],"references":null,"provider_references":{"reference_number_1":"pay2_test_66hruyhimlcy92zjg50","reference_number_2":null,"payment_channel":null},"store_id":null,"store_name":null,"terminal_id":null,"type":"promptpay","zero_interest_installments":null,"charge_status":"pending","receipt_amount":null,"discounts":[],"promotion_code":null,"supplier_id":null},"schedule":null,"linked_account":null,"customer":null,"dispute":null,"transaction":null,"failure_code":null,"failure_message":null,"merchant_advice":null,"status":"pending","authorize_uri":null,"return_uri":null,"created_at":"2026-01-25T10:26:26Z","paid_at":null,"authorized_at":null,"expires_at":"2026-01-26T10:26:26Z","expired_at":null,"reversed_at":null,"multi_capture":false,"zero_interest_installments":true,"branch":null,"terminal":null,"device":null,"authorized":false,"capturable":false,"capture":true,"disputable":false,"livemode":false,"refundable":false,"partially_refundable":false,"reversed":false,"reversible":false,"voided":false,"paid":false,"expired":false,"can_perform_void":false,"approval_code":null}',true);
            $charge = new \Omise\Http\Response($chargeArray);
            $config = [
                'public_key' => 'pkey_test_5jpaer5tf7f5lqht42g',
                'secret_key' => 'skey_test_5jpaevl1ktxxtv5tirn',
                'api_url' => 'https://api.omise.co',
                'api_version' => '2019-05-29',
                'mode' => 'test'
            ];
            $qrDataUri = (new Omise($config))->promptPay()->getQrCodeDataUri($charge);
            expect($qrDataUri)->toBeString();
        });
    });
});
