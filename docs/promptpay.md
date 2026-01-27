# PromptPay

PromptPay is Thailand's national QR payment system, enabling instant payments through scanning a QR code.

## Overview

| Property | Value |
|----------|-------|
| Type | QR Code (Offline) |
| Flow | `offline` |
| Country | Thailand |
| Currency | THB |
| Minimum | 20 THB (2,000 satang) |
| Maximum | 150,000 THB (15,000,000 satang) |
| Refund | Not supported |

## How It Works

1. Create a PromptPay charge
2. Display the QR code to the customer
3. Customer scans QR with their banking app
4. Customer confirms payment in their app
5. Receive webhook notification on completion

## Basic Usage

```php
use Omise\Omise;

$omise = new Omise([
    'public_key' => 'pkey_...',
    'secret_key' => 'skey_...',
]);

// Simple payment (amount in THB)
$charge = $omise->promptPay()->pay(100.00);

// With webhook endpoints
$charge = $omise->promptPay()->pay(100.00, [
    'https://your-site.com/webhooks/omise'
]);

// With expiration time
$expiresAt = date('c', strtotime('+1 hour'));
$charge = $omise->promptPay()->pay(100.00, [], $expiresAt);
```

## QR Code Display

```php
// Get QR code URL (for img src)
$qrUrl = $omise->promptPay()->getQrCodeUrl($charge);
// Result: https://api.omise.co/charges/.../qr.svg

// Get QR code as base64
$qrBase64 = $omise->promptPay()->getQrCodeBase64($charge);

// Get QR code as data URI (for embedding in HTML)
$qrDataUri = $omise->promptPay()->getQrCodeDataUri($charge);
// Result: data:image/svg+xml;base64,...
```

### HTML Example

```html
<!-- Using URL -->
<img src="<?= $qrUrl ?>" alt="Scan to pay">

<!-- Using Data URI -->
<img src="<?= $qrDataUri ?>" alt="Scan to pay">
```

## Checking Payment Status

```php
// Check charge status
if ($omise->promptPay()->isPending($charge)) {
    // Waiting for payment
}

if ($omise->promptPay()->isSuccessful($charge)) {
    // Payment completed
}

if ($omise->promptPay()->isFailed($charge)) {
    $errorCode = $omise->promptPay()->getFailureCode($charge);
    $errorMessage = $omise->promptPay()->getFailureMessage($charge);
}

if ($omise->promptPay()->isExpired($charge)) {
    // QR code has expired
}
```

## Laravel Usage

```php
use Omise\Laravel\Facades\Omise;

// Create payment
$charge = Omise::payWithPromptPay(100.00);

// Get QR code
$qrUrl = Omise::promptPay()->getQrCodeUrl($charge);

// In your controller
public function createPayment(Request $request)
{
    $charge = Omise::promptPay()->pay($request->amount);

    return response()->json([
        'qr_code' => Omise::promptPay()->getQrCodeUrl($charge),
        'charge_id' => $charge->getId(),
    ]);
}
```

## Webhook Handling

PromptPay payments complete asynchronously. Use webhooks to receive payment notifications:

```php
$handler = WebhookHandler::withVerification('your_webhook_secret');

$handler->onChargeComplete(function ($event, $data) {
    if ($data['status'] === 'successful') {
        // Payment successful - update your order
        $chargeId = $data['id'];
    }
});

$handler->handle($payload, $signature, $timestamp);
```

## Failure Codes

| Code | Thai Message | English Message |
|------|--------------|-----------------|
| `failed_processing` | ระบบทำรายการไม่สำเร็จ | Payment processing failed |
| `insufficient_fund` | วงเงินไม่เพียงพอ | Insufficient funds |

## API Reference

| Method | Description |
|--------|-------------|
| `pay($amount, $webhooks, $expiresAt)` | Create a payment |
| `getQrCodeUrl($charge)` | Get QR code URL |
| `getQrCodeBase64($charge)` | Get QR code as base64 |
| `getQrCodeDataUri($charge)` | Get QR code as data URI |
| `isPending($charge)` | Check if pending |
| `isSuccessful($charge)` | Check if successful |
| `isFailed($charge)` | Check if failed |
| `isExpired($charge)` | Check if expired |
| `getFailureCode($charge)` | Get failure code |
| `getFailureMessage($charge)` | Get failure message (Thai) |
| `toSatang($thb)` | Convert THB to satang |
| `toThb($satang)` | Convert satang to THB |
| `getMinimumThb()` | Get minimum amount (20 THB) |
| `getMaximumThb()` | Get maximum amount (150,000 THB) |

## Notes

- QR codes expire after 24 hours by default
- Payments are final and cannot be refunded
- Works with any Thai banking app that supports PromptPay
