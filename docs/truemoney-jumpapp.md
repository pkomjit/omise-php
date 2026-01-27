# TrueMoney App Redirection (Jump App)

TrueMoney Jump App redirects customers from your website or mobile app to the TrueMoney app to authorize and confirm payment. Payment expires if not completed within 3 minutes.

## Overview

| Property | Value |
|----------|-------|
| Type | App Redirect |
| Flow | `app_redirect` |
| Country | Thailand |
| Currency | THB |
| Minimum | 100 THB (10,000 satang) |
| Maximum | 50,000 THB (5,000,000 satang) |
| Timeout | 3 minutes |
| Void | Same-day only |
| Refund | Within 30 days |

## How It Works

1. Create a TrueMoney Jump App charge with a return URI
2. Redirect customer to the authorize URI
3. Customer opens TrueMoney app to authorize (3-minute timeout)
4. Customer selects payment method and confirms
5. Customer is redirected back to your return URI
6. Receive webhook notification on completion

## Payment Methods

Customers can fund TrueMoney Jump App payments using:
- TrueMoney Wallet (balance)
- Bank Account
- Credit/Debit Card
- Pay Next (full payment)
- Pay Next Extra (full payment)

> **Note:** Partial refunds are only supported for wallet and bank account payments.

## Basic Usage

```php
use Omise\Omise;

$omise = new Omise([
    'public_key' => 'pkey_...',
    'secret_key' => 'skey_...',
]);

// Create payment (amount in THB, return_uri required)
$charge = $omise->truemoneyJumpApp()->pay(
    100.00,
    'https://your-site.com/payment/complete'
);

// Get the authorization URL and redirect customer
$authorizeUrl = $omise->truemoneyJumpApp()->getAuthorizeUri($charge);
header("Location: {$authorizeUrl}");
exit;
```

## After Customer Returns

```php
// After customer completes payment, check status
$charge = $omise->getCharge($chargeId);

if ($omise->truemoneyJumpApp()->isSuccessful($charge)) {
    // Payment completed successfully
}

if ($omise->truemoneyJumpApp()->isFailed($charge)) {
    $errorCode = $omise->truemoneyJumpApp()->getFailureCode($charge);
    $errorMessage = $omise->truemoneyJumpApp()->getFailureMessage($charge, 'th');
}

if ($omise->truemoneyJumpApp()->isExpired($charge)) {
    // Payment timed out (3-minute limit)
}
```

## Laravel Usage

```php
use Omise\Laravel\Facades\Omise;

// In your controller
public function createPayment(Request $request)
{
    $charge = Omise::payWithTruemoneyJumpApp(
        $request->amount,
        route('payment.callback')
    );

    return redirect(Omise::truemoneyJumpApp()->getAuthorizeUri($charge));
}

public function paymentCallback(Request $request)
{
    $charge = Omise::getCharge($request->charge_id);

    if (Omise::truemoneyJumpApp()->isSuccessful($charge)) {
        return view('payment.success');
    }

    if (Omise::truemoneyJumpApp()->isExpired($charge)) {
        return view('payment.expired', [
            'message' => 'Payment timed out. Please try again.'
        ]);
    }

    return view('payment.failed', [
        'message' => Omise::truemoneyJumpApp()->getFailureMessage($charge),
    ]);
}
```

## Status Helpers

```php
// Check if awaiting customer redirect
if ($omise->truemoneyJumpApp()->isPendingRedirect($charge)) {
    // Customer needs to be redirected to TrueMoney app
}

// Check if pending
if ($omise->truemoneyJumpApp()->isPending($charge)) {
    // Waiting for payment
}

// Check if expired (3-minute timeout)
if ($omise->truemoneyJumpApp()->isExpired($charge)) {
    // Payment timed out
}

// Check if successful
if ($omise->truemoneyJumpApp()->isSuccessful($charge)) {
    // Payment completed
}

// Check if failed
if ($omise->truemoneyJumpApp()->isFailed($charge)) {
    // Payment failed
}
```

## Void and Refund

```php
// Check if void is available (same-day only)
if ($omise->truemoneyJumpApp()->canVoid($charge)) {
    // Can void the charge
}

// Check if refund is available (within 30 days)
if ($omise->truemoneyJumpApp()->canRefund($charge)) {
    $deadline = $omise->truemoneyJumpApp()->getRefundDeadline($charge);
}
```

## Failure Codes

| Code | Thai Message | English Message |
|------|--------------|-----------------|
| `failed_processing` | ระบบทำรายการไม่สำเร็จ | Payment processing failed |
| `insufficient_balance` | ยอดเงินไม่เพียงพอ | Insufficient balance |
| `payment_cancelled` | ยกเลิกการชำระเงิน | Payment was cancelled |
| `timeout` | หมดเวลาในการชำระเงิน (3 นาที) | Payment timed out (3 minutes) |
| `expired` | การชำระเงินหมดอายุ | Payment expired |

## API Reference

| Method | Description |
|--------|-------------|
| `pay($amount, $returnUri, $webhooks)` | Create a payment (amount in THB) |
| `charge($amount, $currency, $options)` | Create a charge (amount in satang) |
| `getAuthorizeUri($charge)` | Get URL to redirect customer |
| `getReturnUri($charge)` | Get the return URI |
| `isPending($charge)` | Check if pending |
| `isPendingRedirect($charge)` | Check if awaiting customer redirect |
| `isSuccessful($charge)` | Check if successful |
| `isFailed($charge)` | Check if failed |
| `isExpired($charge)` | Check if expired (3-minute timeout) |
| `canVoid($charge)` | Check if void is available (same-day) |
| `canRefund($charge)` | Check if refund is available (30 days) |
| `getRefundDeadline($charge)` | Get refund deadline date |
| `getFailureCode($charge)` | Get failure code |
| `getFailureMessage($charge, $locale)` | Get failure message ('th' or 'en') |
| `toSatang($thb)` | Convert THB to satang |
| `toThb($satang)` | Convert satang to THB |
| `validateThbAmount($amount)` | Validate amount in THB |
| `getMinimumThb()` | Get minimum amount (100 THB) |
| `getMaximumThb()` | Get maximum amount (50,000 THB) |
| `getType()` | Get payment method type |
| `getName()` | Get payment method name |
| `getFlow()` | Get payment flow |
| `getSupportedCurrencies()` | Get supported currencies |
| `getRequiredParameters()` | Get required parameters |

## Notes

- Minimum API version: `2017-11-02`
- Feature activation may be required - contact support@omise.co
- **Payment expires after 3 minutes** if customer doesn't complete authorization
- Customers must have the TrueMoney app installed
- Voids are only available on the same day as the charge
- Refunds are available within 30 days
- Partial refunds are only supported for wallet and bank account payments
