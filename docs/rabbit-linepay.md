# Rabbit LINE Pay

Rabbit LINE Pay is a mobile e-wallet service integrated with LINE Messenger. It uses a redirect flow where customers authorize payment in the LINE app.

## Overview

| Property | Value |
|----------|-------|
| Type | Redirect |
| Flow | `redirect` |
| Country | Thailand |
| Currency | THB |
| Minimum | 20 THB (2,000 satang) |
| Maximum | 150,000 THB (15,000,000 satang) |
| Refund | Within 60 days |

## How It Works

1. Create a Rabbit LINE Pay charge with a return URI
2. Redirect customer to the authorize URI
3. Customer opens LINE app to authorize payment
4. Customer is redirected back to your return URI
5. Verify the charge status

## Basic Usage

```php
use Omise\Omise;

$omise = new Omise([
    'public_key' => 'pkey_...',
    'secret_key' => 'skey_...',
]);

// Create payment (amount in THB, return_uri required)
$charge = $omise->rabbitLinePay()->pay(
    100.00,
    'https://your-site.com/payment/complete'
);

// Get the authorization URL and redirect customer
$authorizeUrl = $omise->rabbitLinePay()->getAuthorizeUri($charge);
header("Location: {$authorizeUrl}");
exit;
```

## After Customer Returns

```php
// After customer completes payment, check status
$charge = $omise->getCharge($chargeId);

if ($omise->rabbitLinePay()->isSuccessful($charge)) {
    // Payment completed successfully
}

if ($omise->rabbitLinePay()->isFailed($charge)) {
    $errorCode = $omise->rabbitLinePay()->getFailureCode($charge);
    // Thai message
    $errorMessage = $omise->rabbitLinePay()->getFailureMessage($charge, 'th');
    // English message
    $errorMessageEn = $omise->rabbitLinePay()->getFailureMessage($charge, 'en');
}
```

## Checking Refund Eligibility

```php
// Check refund eligibility (within 60 days)
if ($omise->rabbitLinePay()->canRefund($charge)) {
    $deadline = $omise->rabbitLinePay()->getRefundDeadline($charge);
    // $deadline is an ISO 8601 datetime string
}
```

## Laravel Usage

```php
use Omise\Laravel\Facades\Omise;

// In your controller
public function createPayment(Request $request)
{
    $charge = Omise::payWithRabbitLinePay(
        $request->amount,
        route('payment.callback')
    );

    return redirect(Omise::rabbitLinePay()->getAuthorizeUri($charge));
}

public function paymentCallback(Request $request)
{
    $charge = Omise::getCharge($request->charge_id);

    if (Omise::rabbitLinePay()->isSuccessful($charge)) {
        return view('payment.success');
    }

    return view('payment.failed', [
        'message' => Omise::rabbitLinePay()->getFailureMessage($charge),
    ]);
}
```

## Status Helpers

```php
// Check if awaiting customer redirect
if ($omise->rabbitLinePay()->isPendingRedirect($charge)) {
    // Customer needs to be redirected
}

// Check if pending
if ($omise->rabbitLinePay()->isPending($charge)) {
    // Waiting for payment
}

// Check if expired
if ($omise->rabbitLinePay()->isExpired($charge)) {
    // Payment link expired
}

// Check if reversed/refunded
if ($omise->rabbitLinePay()->isReversed($charge)) {
    // Payment was refunded
}
```

## Failure Codes

| Code | Thai Message | English Message |
|------|--------------|-----------------|
| `failed_processing` | ระบบทำรายการไม่สำเร็จ | Payment processing failed |
| `insufficient_balance` | วงเงินคงเหลือไม่เพียงพอ | Insufficient balance |
| `payment_cancelled` | ผู้ซื้อยกเลิกการชำระเงิน | Payment was cancelled by customer |
| `timeout` | หมดเวลาในการชำระเงิน | Payment timed out |

## API Reference

| Method | Description |
|--------|-------------|
| `pay($amount, $returnUri, $webhooks)` | Create a payment |
| `getAuthorizeUri($charge)` | Get URL to redirect customer |
| `getReturnUri($charge)` | Get the return URI |
| `isPending($charge)` | Check if pending |
| `isPendingRedirect($charge)` | Check if awaiting customer redirect |
| `isSuccessful($charge)` | Check if successful |
| `isFailed($charge)` | Check if failed |
| `isExpired($charge)` | Check if expired |
| `isReversed($charge)` | Check if reversed/refunded |
| `canRefund($charge)` | Check if refund is allowed (60 days) |
| `getRefundDeadline($charge)` | Get refund deadline date |
| `getFailureCode($charge)` | Get failure code |
| `getFailureMessage($charge, $locale)` | Get failure message ('th' or 'en') |
| `toSatang($thb)` | Convert THB to satang |
| `toThb($satang)` | Convert satang to THB |
| `getMinimumThb()` | Get minimum amount (20 THB) |
| `getMaximumThb()` | Get maximum amount (150,000 THB) |

## Notes

- Refunds are supported within 60 days of the original charge
- Customers must have the LINE app installed
- Payment authorization happens in the LINE app
