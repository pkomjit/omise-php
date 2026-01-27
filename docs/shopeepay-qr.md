# ShopeePay QR

ShopeePay QR enables customers to pay by scanning a QR code using the ShopeePay app or Shopee app. Customers are redirected to ShopeePay's QR page where they can scan the QR code to complete the payment.

## Overview

| Property | Value |
|----------|-------|
| Source Type | `shopeepay` |
| Flow | Redirect (QR code page) |
| Countries | Thailand, Singapore, Malaysia |
| Refund | Yes (within 180 days) |
| Minimum API Version | `2017-11-02` |

## Amount Limits

| Country | Currency | Minimum | Maximum | Expiration |
|---------|----------|---------|---------|------------|
| Thailand | THB | 20 | 150,000 | 20 minutes |
| Singapore | SGD | 1 | 20,000 | 20 minutes |
| Malaysia | MYR | 1 | 4,999 | 60 minutes |

## How It Works

1. Create a ShopeePay charge with amount, currency, and return URI
2. Redirect customer to the authorize URI (ShopeePay QR page)
3. Customer opens ShopeePay/Shopee app to scan QR code
4. Customer authorizes payment in the app
5. Customer is redirected back to your return URI
6. Verify the charge status via webhook or API

## Basic Usage

```php
use Omise\Omise;

$omise = new Omise([
    'public_key' => 'pkey_...',
    'secret_key' => 'skey_...',
]);

// Create payment (amount in main currency unit)
$charge = $omise->shopeepayQR()->pay(
    100.00,  // amount
    'THB',   // currency
    'https://your-site.com/callback'
);

// Get the authorization URL and redirect customer
$authorizeUrl = $omise->shopeepayQR()->getAuthorizeUri($charge);
header("Location: {$authorizeUrl}");
exit;
```

## Currency-Specific Methods

```php
// Thailand (THB)
$charge = $omise->shopeepayQR()->payThb(
    100.00,
    'https://your-site.com/callback'
);

// Singapore (SGD)
$charge = $omise->shopeepayQR()->paySgd(
    50.00,
    'https://your-site.com/callback'
);

// Malaysia (MYR)
$charge = $omise->shopeepayQR()->payMyr(
    100.00,
    'https://your-site.com/callback'
);
```

## With Webhook Endpoints

```php
$charge = $omise->shopeepayQR()->pay(
    100.00,
    'THB',
    'https://your-site.com/callback',
    ['https://your-site.com/webhook']  // webhook endpoints
);
```

## Custom Expiration

```php
// Set custom expiration (max 60 minutes from creation)
$expiresAt = date('c', strtotime('+45 minutes'));

$charge = $omise->shopeepayQR()->pay(
    100.00,
    'THB',
    'https://your-site.com/callback',
    [],        // webhook endpoints
    $expiresAt // ISO 8601 datetime
);
```

## Charge in Smallest Currency Unit

```php
// Charge in satang/cents/sen (smallest unit)
$charge = $omise->shopeepayQR()->charge(
    10000,  // 100 THB in satang
    'THB',
    ['return_uri' => 'https://your-site.com/callback']
);
```

## After Customer Returns

```php
// Check charge status after redirect
$charge = $omise->getCharge($chargeId);

if ($omise->shopeepayQR()->isSuccessful($charge)) {
    // Payment completed successfully
}

if ($omise->shopeepayQR()->isFailed($charge)) {
    $errorCode = $omise->shopeepayQR()->getFailureCode($charge);
    $errorMessage = $omise->shopeepayQR()->getFailureMessage($charge, 'th');
}

if ($omise->shopeepayQR()->isExpired($charge)) {
    // QR code expired before payment
}
```

## Laravel Usage

```php
use Omise\Laravel\Facades\Omise;

// In your controller
public function createPayment(Request $request)
{
    $charge = Omise::payWithShopeepayQR(
        $request->amount,
        $request->currency,
        route('payment.callback')
    );

    return redirect(Omise::shopeepayQR()->getAuthorizeUri($charge));
}

public function callback(Request $request)
{
    $charge = Omise::getCharge($request->charge_id);

    if (Omise::shopeepayQR()->isSuccessful($charge)) {
        return view('payment.success');
    }

    return view('payment.failed', [
        'message' => Omise::shopeepayQR()->getFailureMessage($charge),
    ]);
}
```

## Amount Validation

```php
// Get limits for a currency
$min = $omise->shopeepayQR()->getMinimumAmount('THB');           // 2000 satang
$max = $omise->shopeepayQR()->getMaximumAmount('THB');           // 15000000 satang
$minThb = $omise->shopeepayQR()->getMinimumInMainUnit('THB');    // 20.0 THB
$maxThb = $omise->shopeepayQR()->getMaximumInMainUnit('THB');    // 150000.0 THB

// Validate amount (returns true/false)
$isValid = $omise->shopeepayQR()->validateAmountForCurrency(100.0, 'THB');

// Validate amount in smallest unit
$isValid = $omise->shopeepayQR()->validateAmount(10000, 'THB');
```

## Currency Helpers

```php
// Get country for currency
$country = $omise->shopeepayQR()->getCountryForCurrency('THB'); // 'Thailand'

// Get default expiration time
$minutes = $omise->shopeepayQR()->getDefaultExpirationMinutes('THB'); // 20
$minutes = $omise->shopeepayQR()->getDefaultExpirationMinutes('MYR'); // 60

// Currency conversion
$satang = $omise->shopeepayQR()->toSmallestUnit(100.0, 'THB');  // 10000
$thb = $omise->shopeepayQR()->toMainUnit(10000, 'THB');         // 100.0
```

## Refund Eligibility

```php
// Check if charge can be refunded (within 180 days)
if ($omise->shopeepayQR()->canRefund($charge)) {
    // Charge is eligible for refund
}

// Get refund deadline
$deadline = $omise->shopeepayQR()->getRefundDeadline($charge);
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
| `pay($amount, $currency, $returnUri, $webhooks, $expiresAt)` | Create payment (amount in main unit) |
| `payThb($amount, $returnUri, $webhooks)` | Create THB payment |
| `paySgd($amount, $returnUri, $webhooks)` | Create SGD payment |
| `payMyr($amount, $returnUri, $webhooks)` | Create MYR payment |
| `charge($amount, $currency, $options)` | Create charge (amount in smallest unit) |
| `getAuthorizeUri($charge)` | Get URL to redirect customer |
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
| `getDefaultExpirationMinutes($currency)` | Get default expiration time |
| `getMinimumAmount($currency)` | Get min in smallest unit |
| `getMaximumAmount($currency)` | Get max in smallest unit |
| `getMinimumInMainUnit($currency)` | Get min in main unit |
| `getMaximumInMainUnit($currency)` | Get max in main unit |
| `validateAmount($amount, $currency)` | Validate amount in smallest unit |
| `validateAmountForCurrency($amount, $currency)` | Validate amount in main unit |
| `toSmallestUnit($amount, $currency)` | Convert to smallest unit |
| `toMainUnit($amount, $currency)` | Convert to main unit |

## Supported Payment Methods

ShopeePay QR supports various payment methods within the ShopeePay ecosystem:
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
- Malaysia has a longer default expiration (60 minutes) compared to Thailand and Singapore (20 minutes)
