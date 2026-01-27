# TrueMoney QR

TrueMoney QR allows customers to pay by scanning a QR code using the TrueMoney app. Customers can pay using TrueMoney Wallet balance, bank accounts, credit/debit cards, or installment options.

## Overview

| Property | Value |
|----------|-------|
| Type | QR Code (Offline) |
| Flow | `offline` |
| Country | Thailand |
| Currency | THB |
| Minimum | 100 THB (10,000 satang) |
| Maximum | 50,000 THB (5,000,000 satang) |
| Void | Same-day only |
| Refund | Within 30 days |

## How It Works

1. Create a TrueMoney QR charge
2. Display the QR code to the customer
3. Customer scans QR code with TrueMoney app
4. Customer selects payment method and authorizes
5. Receive webhook notification on completion

## Payment Methods

Customers can fund TrueMoney QR payments using:
- TrueMoney Wallet (balance)
- Bank Account
- Credit/Debit Card
- Pay Next (full payment)
- Pay Next Extra (full payment)

## Basic Usage

```php
use Omise\Omise;

$omise = new Omise([
    'public_key' => 'pkey_...',
    'secret_key' => 'skey_...',
]);

// Simple payment (amount in THB)
$charge = $omise->truemoneyQR()->pay(100.00);

// With webhook endpoints
$charge = $omise->truemoneyQR()->pay(100.00, [
    'https://your-site.com/webhooks/omise'
]);

// With expiration time
$expiresAt = date('c', strtotime('+1 hour'));
$charge = $omise->truemoneyQR()->pay(100.00, [], $expiresAt);
```

## QR Code Display

```php
// Get QR code URL (for img src)
$qrUrl = $omise->truemoneyQR()->getQrCodeUrl($charge);

// Get QR code as base64
$qrBase64 = $omise->truemoneyQR()->getQrCodeBase64($charge);

// Get QR code as data URI (for embedding in HTML)
$qrDataUri = $omise->truemoneyQR()->getQrCodeDataUri($charge);
```

### HTML Example

```html
<!-- Using URL -->
<img src="<?= $qrUrl ?>" alt="Scan to pay with TrueMoney">

<!-- Using Data URI -->
<img src="<?= $qrDataUri ?>" alt="Scan to pay with TrueMoney">
```

## Checking Payment Status

```php
// Check charge status
if ($omise->truemoneyQR()->isPending($charge)) {
    // Waiting for payment
}

if ($omise->truemoneyQR()->isSuccessful($charge)) {
    // Payment completed
}

if ($omise->truemoneyQR()->isFailed($charge)) {
    $errorCode = $omise->truemoneyQR()->getFailureCode($charge);
    $errorMessage = $omise->truemoneyQR()->getFailureMessage($charge, 'th');
}

if ($omise->truemoneyQR()->isExpired($charge)) {
    // QR code has expired
}
```

## Laravel Usage

```php
use Omise\Laravel\Facades\Omise;

// Create payment
$charge = Omise::payWithTruemoneyQR(100.00);

// Get QR code
$qrUrl = Omise::truemoneyQR()->getQrCodeUrl($charge);

// In your controller
public function createPayment(Request $request)
{
    $charge = Omise::truemoneyQR()->pay($request->amount);

    return response()->json([
        'qr_code' => Omise::truemoneyQR()->getQrCodeUrl($charge),
        'charge_id' => $charge->getId(),
    ]);
}
```

## Void and Refund

```php
// Check if void is available (same-day only)
if ($omise->truemoneyQR()->canVoid($charge)) {
    // Can void the charge
}

// Check if refund is available (within 30 days)
if ($omise->truemoneyQR()->canRefund($charge)) {
    $deadline = $omise->truemoneyQR()->getRefundDeadline($charge);
}
```

## Failure Codes

| Code | Thai Message | English Message |
|------|--------------|-----------------|
| `failed_processing` | ระบบทำรายการไม่สำเร็จ | Payment processing failed |
| `insufficient_balance` | ยอดเงินไม่เพียงพอ | Insufficient balance |
| `payment_cancelled` | ยกเลิกการชำระเงิน | Payment was cancelled |
| `timeout` | หมดเวลาในการชำระเงิน | Payment timed out |
| `expired` | QR Code หมดอายุ | QR code expired |

## API Reference

| Method | Description |
|--------|-------------|
| `pay($amount, $webhooks, $expiresAt)` | Create a payment (amount in THB) |
| `charge($amount, $currency, $options)` | Create a charge (amount in satang) |
| `getQrCodeUrl($charge)` | Get QR code URL |
| `getQrCodeBase64($charge)` | Get QR code as base64 |
| `getQrCodeDataUri($charge)` | Get QR code as data URI |
| `isPending($charge)` | Check if pending |
| `isSuccessful($charge)` | Check if successful |
| `isFailed($charge)` | Check if failed |
| `isExpired($charge)` | Check if expired |
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

## Notes

- Minimum API version: `2017-11-02`
- Feature activation may be required - contact support@omise.co
- Voids are only available on the same day as the charge
- Refunds are available within 30 days (full and partial)
