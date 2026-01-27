# Mobile Banking

Mobile Banking enables customers to pay through their bank's mobile application. Customers are redirected to authorize payment using a deep link to their banking app.

## Overview

| Bank | Source Type | Country | Currency | Limits | Expiration | Refund |
|------|-------------|---------|----------|--------|------------|--------|
| Bangkok Bank (BBL) | `mobile_banking_bbl` | Thailand | THB | 20 - 150,000 | 15 min | No |
| KBank (K PLUS) | `mobile_banking_kbank` | Thailand | THB | 20 - 150,000 | 10 min | No |
| Krungthai Bank (KTB) | `mobile_banking_ktb` | Thailand | THB | 20 - 150,000 | 30 min | No |
| Bank of Ayudhya (BAY) | `mobile_banking_bay` | Thailand | THB | 20 - 150,000 | 15 min | No |
| SCB (SCB Easy) | `mobile_banking_scb` | Thailand | THB | 20 - 150,000 | 7 days | No |
| OCBC Digital | `mobile_banking_ocbc` | Singapore | SGD | 1 - 20,000 | varies | Yes (180 days) |

## How It Works

1. Create a mobile banking charge with bank type and return URI
2. Redirect customer to the authorize URI (deep link to bank app)
3. Customer opens their bank app to authorize payment
4. Customer is redirected back to your return URI
5. Verify the charge status via webhook or API

## Bank Constants

```php
use Omise\PaymentMethods\MobileBanking;

// Thailand banks
MobileBanking::BANK_BBL    // Bangkok Bank (Bualuang mBanking)
MobileBanking::BANK_KBANK  // KBank (K PLUS)
MobileBanking::BANK_KTB    // Krungthai Bank (KTB NEXT)
MobileBanking::BANK_BAY    // Bank of Ayudhya (KMA)
MobileBanking::BANK_SCB    // Siam Commercial Bank (SCB Easy)

// Singapore banks
MobileBanking::BANK_OCBC   // OCBC Digital

// Platform types
MobileBanking::PLATFORM_IOS
MobileBanking::PLATFORM_ANDROID
```

## Basic Usage

```php
use Omise\Omise;
use Omise\PaymentMethods\MobileBanking;

$omise = new Omise([
    'public_key' => 'pkey_...',
    'secret_key' => 'skey_...',
]);

// Create payment with KBank (amount in THB)
$charge = $omise->mobileBanking()->pay(
    MobileBanking::BANK_KBANK,
    100.00,  // 100 THB
    'https://your-site.com/callback'
);

// Get the authorization URL and redirect customer
$authorizeUrl = $omise->mobileBanking()->getAuthorizeUri($charge);
header("Location: {$authorizeUrl}");
exit;
```

## With Platform Type

Specify the customer's platform for optimal deep link handling:

```php
$charge = $omise->mobileBanking()->pay(
    MobileBanking::BANK_KBANK,
    100.00,
    'https://your-site.com/callback',
    [
        'platform_type' => MobileBanking::PLATFORM_IOS,
    ]
);
```

## Charge in Smallest Currency Unit

```php
// Charge in satang (smallest unit)
$charge = $omise->mobileBanking()->charge(
    MobileBanking::BANK_KBANK,
    10000,  // 100 THB in satang
    'https://your-site.com/callback'
);
```

## Singapore (OCBC)

```php
// OCBC uses SGD currency
$charge = $omise->mobileBanking()->pay(
    MobileBanking::BANK_OCBC,
    50.00,  // 50 SGD
    'https://your-site.com/callback'
);
```

## After Customer Returns

```php
// Check charge status after redirect
$charge = $omise->getCharge($chargeId);

if ($omise->mobileBanking()->isSuccessful($charge)) {
    // Payment completed successfully
}

if ($omise->mobileBanking()->isFailed($charge)) {
    $errorCode = $omise->mobileBanking()->getFailureCode($charge);
    $errorMessage = $omise->mobileBanking()->getFailureMessage($charge, 'th');
}

if ($omise->mobileBanking()->isExpired($charge)) {
    // Payment authorization expired
}
```

## Laravel Usage

```php
use Omise\Laravel\Facades\Omise;
use Omise\PaymentMethods\MobileBanking;

// In your controller
public function createPayment(Request $request)
{
    $charge = Omise::payWithMobileBanking(
        $request->bank_type,  // e.g., MobileBanking::BANK_KBANK
        $request->amount,
        route('payment.callback'),
        [
            'platform_type' => $request->platform,
        ]
    );

    return redirect(Omise::mobileBanking()->getAuthorizeUri($charge));
}

public function callback(Request $request)
{
    $charge = Omise::getCharge($request->charge_id);

    if (Omise::mobileBanking()->isSuccessful($charge)) {
        return view('payment.success');
    }

    return view('payment.failed', [
        'message' => Omise::mobileBanking()->getFailureMessage($charge),
    ]);
}
```

## Bank Selection UI

```php
// Get all supported banks
$banks = $omise->mobileBanking()->getSupportedBanks();
// Returns: [
//   'mobile_banking_bbl' => 'Bangkok Bank (Bualuang mBanking)',
//   'mobile_banking_kbank' => 'KBank (K PLUS)',
//   ...
// ]

// Get Thailand banks only
$thaiBanks = $omise->mobileBanking()->getThailandBanks();

// Get Singapore banks only
$sgBanks = $omise->mobileBanking()->getSingaporeBanks();

// Get bank details
$name = $omise->mobileBanking()->getBankName(MobileBanking::BANK_KBANK);     // 'KBank (K PLUS)'
$code = $omise->mobileBanking()->getBankCode(MobileBanking::BANK_KBANK);     // 'KBANK'
$currency = $omise->mobileBanking()->getCurrency(MobileBanking::BANK_KBANK); // 'THB'
$country = $omise->mobileBanking()->getCountry(MobileBanking::BANK_KBANK);   // 'Thailand'
$expiry = $omise->mobileBanking()->getExpirationTime(MobileBanking::BANK_KBANK); // '10 minutes'
```

## Amount Validation

```php
// Get limits for a bank
$min = $omise->mobileBanking()->getMinimumAmount(MobileBanking::BANK_KBANK);     // 2000 satang
$max = $omise->mobileBanking()->getMaximumAmount(MobileBanking::BANK_KBANK);     // 15000000 satang
$minThb = $omise->mobileBanking()->getMinimumInMainUnit(MobileBanking::BANK_KBANK); // 20.0 THB
$maxThb = $omise->mobileBanking()->getMaximumInMainUnit(MobileBanking::BANK_KBANK); // 150000.0 THB

// Validate amount (throws InvalidArgumentException if invalid)
$omise->mobileBanking()->validateAmount(MobileBanking::BANK_KBANK, 10000);

// Validate bank type (throws InvalidArgumentException if unsupported)
$omise->mobileBanking()->validateBank(MobileBanking::BANK_KBANK);
```

## Refund Eligibility

```php
// Check if bank supports refunds (only OCBC)
if ($omise->mobileBanking()->canRefund(MobileBanking::BANK_OCBC)) {
    // OCBC supports refunds within 180 days
}

// Check if a specific charge can be refunded
if ($omise->mobileBanking()->canRefundCharge($charge)) {
    // Charge is eligible for refund
}
```

## Failure Codes

| Code | Thai Message | English Message |
|------|--------------|-----------------|
| `failed_processing` | ระบบทำรายการไม่สำเร็จ | Payment processing failed |
| `insufficient_fund` | ยอดเงินไม่เพียงพอ | Insufficient funds or balance |
| `insufficient_balance` | ยอดเงินไม่เพียงพอ | Insufficient funds or balance |
| `invalid_account` | บัญชีไม่ถูกต้องหรือไม่พบ | Invalid or not found account |
| `payment_rejected` | ธนาคารปฏิเสธการชำระเงิน | Payment rejected by bank |
| `payment_expired` | หมดเวลาในการชำระเงิน | Payment authorization expired |
| `payment_cancelled` | ผู้ซื้อยกเลิกการชำระเงิน | Payment cancelled by customer |
| `timeout` | หมดเวลาในการชำระเงิน | Payment timed out |

## API Reference

| Method | Description |
|--------|-------------|
| `pay($bankType, $amount, $returnUri, $options)` | Create payment (amount in main unit) |
| `charge($bankType, $amount, $returnUri, $options)` | Create charge (amount in smallest unit) |
| `getAuthorizeUri($charge)` | Get URL to redirect customer |
| `getReturnUri($charge)` | Get the return URI |
| `isPending($charge)` | Check if pending |
| `isPendingRedirect($charge)` | Check if awaiting customer redirect |
| `isSuccessful($charge)` | Check if successful |
| `isFailed($charge)` | Check if failed |
| `isExpired($charge)` | Check if expired |
| `getFailureCode($charge)` | Get failure code |
| `getFailureMessage($charge, $locale)` | Get failure message ('th' or 'en') |
| `getSupportedBanks()` | Get all supported banks |
| `getThailandBanks()` | Get Thailand banks only |
| `getSingaporeBanks()` | Get Singapore banks only |
| `getBankName($bankType)` | Get bank display name |
| `getBankCode($bankType)` | Get bank short code |
| `getCurrency($bankType)` | Get currency for bank |
| `getCountry($bankType)` | Get country for bank |
| `getExpirationTime($bankType)` | Get charge expiration time |
| `isSupportedBank($bankType)` | Check if bank is supported |
| `validateBank($bankType)` | Validate bank type |
| `validateAmount($bankType, $amount)` | Validate amount for bank |
| `getMinimumAmount($bankType)` | Get min in smallest unit |
| `getMaximumAmount($bankType)` | Get max in smallest unit |
| `getMinimumInMainUnit($bankType)` | Get min in main unit |
| `getMaximumInMainUnit($bankType)` | Get max in main unit |
| `canRefund($bankType)` | Check if bank supports refunds |
| `canRefundCharge($charge)` | Check if charge can be refunded |
| `toSmallestUnit($bankType, $amount)` | Convert to smallest unit |
| `toMainUnit($bankType, $amount)` | Convert to main unit |

## Notes

- Minimum API version: `2017-11-02`
- Feature activation may be required - contact support@omise.co
- Most Thai banks do not support refunds through Omise
- OCBC (Singapore) supports refunds within 180 days
- Customer must have the bank's mobile app installed
- Authorization timeout varies by bank (10 minutes to 7 days)
- Use `platform_type` parameter for optimal deep link handling on mobile devices
