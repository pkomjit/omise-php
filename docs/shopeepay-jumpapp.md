# ShopeePay App (Jump App)

ShopeePay Jump App redirects customers from your website or mobile app to the ShopeePay or Shopee app to authorize and confirm payment. This provides a seamless in-app experience for customers who have the Shopee app installed.

## Overview

| Property | Value |
|----------|-------|
| Source Type | `shopeepay_jumpapp` |
| Flow | App Redirect |
| Countries | Thailand, Singapore, Malaysia |
| Refund | Yes (within 180 days) |
| Minimum API Version | `2017-11-02` |

## Amount Limits

| Country | Currency | Minimum | Maximum | Expiration |
|---------|----------|---------|---------|------------|
| Thailand | THB | 20 | 150,000 | 20 minutes |
| Singapore | SGD | 1 | 20,000 | 20 minutes |
| Malaysia | MYR | 1 | 9,999 | 20 minutes |

> **Note:** Malaysia has a lower maximum limit (9,999 MYR) compared to ShopeePay QR (4,999 MYR for QR).

## How It Works

1. Create a ShopeePay Jump App charge with amount, currency, and return URI
2. Redirect customer to the authorize URI (deep link to Shopee app)
3. Customer opens the Shopee app to authorize payment
4. Customer is redirected back to your return URI
5. Verify the charge status via webhook or API

## Platform Type Constants

```php
use Omise\PaymentMethods\ShopeepayJumpApp;

ShopeepayJumpApp::PLATFORM_IOS      // 'IOS'
ShopeepayJumpApp::PLATFORM_ANDROID  // 'ANDROID'
```

## Basic Usage

```php
use Omise\Omise;

$omise = new Omise([
    'public_key' => 'pkey_...',
    'secret_key' => 'skey_...',
]);

// Create payment (amount in main currency unit)
$charge = $omise->shopeepayJumpApp()->pay(
    100.00,  // amount
    'THB',   // currency
    'https://your-site.com/callback'
);

// Get the authorization URL (deep link) and redirect customer
$authorizeUrl = $omise->shopeepayJumpApp()->getAuthorizeUri($charge);
header("Location: {$authorizeUrl}");
exit;
```

## With Platform Type

Specify the customer's platform for optimal deep link handling:

```php
$charge = $omise->shopeepayJumpApp()->pay(
    100.00,
    'THB',
    'https://your-site.com/callback',
    [
        'platform_type' => ShopeepayJumpApp::PLATFORM_IOS,
    ]
);
```

## Currency-Specific Methods

```php
// Thailand (THB)
$charge = $omise->shopeepayJumpApp()->payThb(
    100.00,
    'https://your-site.com/callback'
);

// Singapore (SGD)
$charge = $omise->shopeepayJumpApp()->paySgd(
    50.00,
    'https://your-site.com/callback'
);

// Malaysia (MYR)
$charge = $omise->shopeepayJumpApp()->payMyr(
    100.00,
    'https://your-site.com/callback'
);

// With options
$charge = $omise->shopeepayJumpApp()->payThb(
    100.00,
    'https://your-site.com/callback',
    [
        'platform_type' => ShopeepayJumpApp::PLATFORM_ANDROID,
        'webhook_endpoints' => ['https://your-site.com/webhook'],
    ]
);
```

## With Webhook Endpoints

```php
$charge = $omise->shopeepayJumpApp()->pay(
    100.00,
    'THB',
    'https://your-site.com/callback',
    [
        'webhook_endpoints' => ['https://your-site.com/webhook'],
    ]
);
```

## Custom Expiration

```php
// Set custom expiration (max 60 minutes from creation)
$expiresAt = date('c', strtotime('+45 minutes'));

$charge = $omise->shopeepayJumpApp()->pay(
    100.00,
    'THB',
    'https://your-site.com/callback',
    [
        'expires_at' => $expiresAt,
    ]
);
```

## Charge in Smallest Currency Unit

```php
// Charge in satang/cents/sen (smallest unit)
$charge = $omise->shopeepayJumpApp()->charge(
    10000,  // 100 THB in satang
    'THB',
    [
        'return_uri' => 'https://your-site.com/callback',
        'platform_type' => ShopeepayJumpApp::PLATFORM_IOS,
    ]
);
```

## After Customer Returns

```php
// Check charge status after redirect
$charge = $omise->getCharge($chargeId);

if ($omise->shopeepayJumpApp()->isSuccessful($charge)) {
    // Payment completed successfully
}

if ($omise->shopeepayJumpApp()->isFailed($charge)) {
    $errorCode = $omise->shopeepayJumpApp()->getFailureCode($charge);
    $errorMessage = $omise->shopeepayJumpApp()->getFailureMessage($charge, 'th');
}

if ($omise->shopeepayJumpApp()->isExpired($charge)) {
    // Payment authorization expired
}
```

## Laravel Usage

```php
use Omise\Laravel\Facades\Omise;
use Omise\PaymentMethods\ShopeepayJumpApp;

// In your controller
public function createPayment(Request $request)
{
    $charge = Omise::payWithShopeepayJumpApp(
        $request->amount,
        $request->currency,
        route('payment.callback'),
        [
            'platform_type' => $request->is_ios
                ? ShopeepayJumpApp::PLATFORM_IOS
                : ShopeepayJumpApp::PLATFORM_ANDROID,
        ]
    );

    return redirect(Omise::shopeepayJumpApp()->getAuthorizeUri($charge));
}

public function callback(Request $request)
{
    $charge = Omise::getCharge($request->charge_id);

    if (Omise::shopeepayJumpApp()->isSuccessful($charge)) {
        return view('payment.success');
    }

    return view('payment.failed', [
        'message' => Omise::shopeepayJumpApp()->getFailureMessage($charge),
    ]);
}
```

## Amount Validation

```php
// Get limits for a currency
$min = $omise->shopeepayJumpApp()->getMinimumAmount('THB');           // 2000 satang
$max = $omise->shopeepayJumpApp()->getMaximumAmount('THB');           // 15000000 satang
$minThb = $omise->shopeepayJumpApp()->getMinimumInMainUnit('THB');    // 20.0 THB
$maxThb = $omise->shopeepayJumpApp()->getMaximumInMainUnit('THB');    // 150000.0 THB

// MYR limits (different from QR)
$maxMyr = $omise->shopeepayJumpApp()->getMaximumInMainUnit('MYR');    // 9999.0 MYR

// Validate amount (returns true/false)
$isValid = $omise->shopeepayJumpApp()->validateAmountForCurrency(100.0, 'THB');

// Validate amount in smallest unit
$isValid = $omise->shopeepayJumpApp()->validateAmount(10000, 'THB');
```

## Platform Type Validation

```php
// Check if platform type is valid
$isValid = $omise->shopeepayJumpApp()->isValidPlatformType('IOS');     // true
$isValid = $omise->shopeepayJumpApp()->isValidPlatformType('WINDOWS'); // false

// Validate platform type (throws InvalidArgumentException if invalid)
$omise->shopeepayJumpApp()->validatePlatformType('IOS');
```

## Currency Helpers

```php
// Get country for currency
$country = $omise->shopeepayJumpApp()->getCountryForCurrency('THB'); // 'Thailand'

// Currency conversion
$satang = $omise->shopeepayJumpApp()->toSmallestUnit(100.0, 'THB');  // 10000
$thb = $omise->shopeepayJumpApp()->toMainUnit(10000, 'THB');         // 100.0
```

## Refund Eligibility

```php
// Check if charge can be refunded (within 180 days)
if ($omise->shopeepayJumpApp()->canRefund($charge)) {
    // Charge is eligible for refund
}

// Get refund deadline
$deadline = $omise->shopeepayJumpApp()->getRefundDeadline($charge);
// Returns: '2024-07-14T10:00:00+07:00' (ISO 8601)
```

## Failure Codes

| Code | Thai Message | English Message |
|------|--------------|-----------------|
| `failed_processing` | ระบบทำรายการไม่สำเร็จ | Payment processing failed |
| `payment_cancelled` | ยกเลิกการชำระเงิน | Payment was cancelled |
| `payment_expired` | การชำระเงินหมดอายุ | Payment expired |
| `payment_rejected` | การชำระเงินถูกปฏิเสธ | Payment was rejected by issuer |
| `invalid_account` | ไม่พบบัญชี ShopeePay ที่ถูกต้อง | No valid ShopeePay account found |
| `insufficient_fund` | ยอดเงินไม่เพียงพอหรือเกินวงเงิน | Insufficient funds or limit exceeded |

## API Reference

| Method | Description |
|--------|-------------|
| `pay($amount, $currency, $returnUri, $options)` | Create payment (amount in main unit) |
| `payThb($amount, $returnUri, $options)` | Create THB payment |
| `paySgd($amount, $returnUri, $options)` | Create SGD payment |
| `payMyr($amount, $returnUri, $options)` | Create MYR payment |
| `charge($amount, $currency, $options)` | Create charge (amount in smallest unit) |
| `getAuthorizeUri($charge)` | Get URL/deep link to redirect customer |
| `getReturnUri($charge)` | Get the return URI |
| `isPending($charge)` | Check if pending |
| `isPendingRedirect($charge)` | Check if awaiting customer redirect |
| `isSuccessful($charge)` | Check if successful |
| `isFailed($charge)` | Check if failed |
| `isExpired($charge)` | Check if expired |
| `getFailureCode($charge)` | Get failure code |
| `getFailureMessage($charge, $locale)` | Get failure message ('th' or 'en') |
| `canRefund($charge)` | Check if charge can be refunded |
| `getRefundDeadline($charge)` | Get refund deadline (ISO 8601) |
| `getCountryForCurrency($currency)` | Get country name for currency |
| `getMinimumAmount($currency)` | Get min in smallest unit |
| `getMaximumAmount($currency)` | Get max in smallest unit |
| `getMinimumInMainUnit($currency)` | Get min in main unit |
| `getMaximumInMainUnit($currency)` | Get max in main unit |
| `validateAmount($amount, $currency)` | Validate amount in smallest unit |
| `validateAmountForCurrency($amount, $currency)` | Validate amount in main unit |
| `isValidPlatformType($platformType)` | Check if platform type is valid |
| `validatePlatformType($platformType)` | Validate platform type |
| `toSmallestUnit($amount, $currency)` | Convert to smallest unit |
| `toMainUnit($amount, $currency)` | Convert to main unit |

## Supported Payment Methods

ShopeePay Jump App supports various payment methods within the ShopeePay ecosystem:
- Wallet balance
- Credit/debit cards linked to ShopeePay
- Direct debit/bank accounts
- SPayLater (availability varies by country)

## Notes

- Minimum API version: `2017-11-02`
- Feature activation may be required - contact support@omise.co
- Refunds are available within 180 days of the transaction
- Refunds are NOT available for off-us (mobile banking) transactions
- Custom expiration can be set up to 60 minutes from creation
- Default expiration is 20 minutes for all countries
- Use `platform_type` parameter for optimal deep link handling on mobile devices
- Customer must have the Shopee app installed on their device
- Malaysia has a different maximum limit (9,999 MYR) compared to ShopeePay QR
