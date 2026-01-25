# Omise PHP SDK

A PHP SDK for [Omise](https://www.omise.co/) payment gateway integration. Supports both standalone PHP and Laravel applications.

## Features

- PHP 8.3+ support
- Works with or without Laravel
- PromptPay (QR Payment) support
- Charge, Source, and Event APIs
- Webhook signature verification
- Type-safe with full IDE support

## Installation

```bash
composer require pkomjit/omise-php
```

## Quick Start

### Standalone PHP

```php
<?php

use Omise\Omise;

$omise = new Omise([
    'public_key' => 'pkey_...',
    'secret_key' => 'skey_...',
]);

// Create a PromptPay charge (100 THB)
$charge = $omise->payWithPromptPay(100.00);

// Get the QR code URL
$qrCodeUrl = $omise->promptPay()->getQrCodeUrl($charge);

// Or get it as base64 for embedding in HTML
$qrCodeBase64 = $omise->promptPay()->getQrCodeBase64($charge);
```

### Laravel

1. Publish the configuration:

```bash
php artisan vendor:publish --tag=omise-config
```

2. Add your API keys to `.env`:

```env
OMISE_PUBLIC_KEY=pkey_...
OMISE_SECRET_KEY=skey_...
OMISE_WEBHOOK_SECRET=whsec_...  # Optional, for webhook verification
```

3. Use the facade:

```php
use Omise\Laravel\Facades\Omise;

// Create a PromptPay charge
$charge = Omise::payWithPromptPay(100.00);

// Get QR code URL
$qrCodeUrl = Omise::promptPay()->getQrCodeUrl($charge);
```

Or use dependency injection:

```php
use Omise\Omise;

class PaymentController extends Controller
{
    public function __construct(private Omise $omise) {}

    public function createPayment(Request $request)
    {
        $charge = $this->omise->payWithPromptPay($request->amount);

        return response()->json([
            'qr_code' => $this->omise->promptPay()->getQrCodeUrl($charge),
            'charge_id' => $charge->getId(),
        ]);
    }
}
```

## PromptPay Usage

PromptPay is Thailand's national QR payment system. Here's how to use it:

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

// Get QR code in different formats
$qrUrl = $omise->promptPay()->getQrCodeUrl($charge);
$qrBase64 = $omise->promptPay()->getQrCodeBase64($charge);
$qrDataUri = $omise->promptPay()->getQrCodeDataUri($charge);

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
```

### PromptPay Limits

- Minimum: ฿20.00
- Maximum: ฿150,000.00
- QR code expires after 24 hours by default

## Charge API

```php
// Create a charge with source
$charge = $omise->charges()->create([
    'amount' => 10000, // 100 THB in satang
    'currency' => 'THB',
    'source' => ['type' => 'promptpay'],
]);

// Retrieve a charge
$charge = $omise->charges()->retrieve('chrg_...');

// List charges
$charges = $omise->charges()->all([
    'limit' => 10,
    'order' => 'reverse_chronological',
]);

// Update a charge
$charge = $omise->charges()->update('chrg_...', [
    'description' => 'Updated description',
]);

// Expire a pending charge
$charge = $omise->charges()->expire('chrg_...');
```

## Source API

```php
// Create a PromptPay source
$source = $omise->sources()->createPromptPay(10000, 'THB');

// Create an internet banking source
$source = $omise->sources()->createInternetBanking('scb', 10000, 'THB');

// Create a TrueMoney source
$source = $omise->sources()->createTrueMoney(10000, '0812345678', 'THB');

// Generic source creation
$source = $omise->sources()->create([
    'type' => 'promptpay',
    'amount' => 10000,
    'currency' => 'THB',
]);
```

## Event API

```php
// Retrieve an event
$event = $omise->events()->retrieve('evnt_...');

// List events
$events = $omise->events()->all([
    'limit' => 25,
]);

// Check event type
if ($omise->events()->isSuccessfulCharge($event)) {
    // Handle successful payment
}
```

## Webhook Handling

### Basic Usage

```php
use Omise\Webhook\WebhookHandler;

// With signature verification (recommended)
$handler = WebhookHandler::withVerification('your_webhook_secret');

// Register handlers
$handler->onChargeComplete(function ($event, $data) {
    if ($data['status'] === 'successful') {
        // Payment successful
        $chargeId = $data['id'];
        // Update your order...
    }
});

// Process the webhook
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_OMISE_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_OMISE_SIGNATURE_TIMESTAMP'] ?? '';

try {
    $handler->handle($payload, $signature, $timestamp);
} catch (\Omise\Exceptions\WebhookException $e) {
    http_response_code(400);
    exit('Invalid webhook');
}

http_response_code(200);
```

### Laravel Webhook Controller

```php
use Illuminate\Http\Request;
use Omise\Webhook\WebhookHandler;
use Omise\Exceptions\WebhookException;

class OmiseWebhookController extends Controller
{
    public function __construct(private WebhookHandler $handler) {}

    public function handle(Request $request)
    {
        $this->handler->onChargeComplete(function ($event, $data) {
            if ($data['status'] === 'successful') {
                // Handle successful payment
            } elseif ($data['status'] === 'failed') {
                // Handle failed payment
            }
        });

        try {
            $this->handler->handle(
                $request->getContent(),
                $request->header('Omise-Signature'),
                $request->header('Omise-Signature-Timestamp')
            );
        } catch (WebhookException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['success' => true]);
    }
}
```

### Webhook Events

Common webhook events you can listen for:

- `charge.create` - Charge created
- `charge.complete` - Charge completed (successful or failed)
- `charge.expire` - Charge expired
- `refund.create` - Refund created
- `dispute.create` - Dispute created

## Configuration Options

```php
$omise = new Omise([
    // Required
    'public_key' => 'pkey_...',
    'secret_key' => 'skey_...',

    // Optional
    'api_url' => 'https://api.omise.co',     // API base URL
    'api_version' => '2019-05-29',            // API version
    'mode' => 'live',                         // 'live' or 'test'
    'webhook_secret' => 'whsec_...',          // For webhook verification
    'timeout' => 30,                          // Request timeout in seconds
    'ssl_verify' => true,                     // Verify SSL certificates
]);
```

### Environment Variables

The SDK can read from environment variables:

```php
$omise = Omise::fromEnvironment();
```

Expected variables:
- `OMISE_PUBLIC_KEY`
- `OMISE_SECRET_KEY`
- `OMISE_WEBHOOK_SECRET` (optional)
- `OMISE_MODE` (optional, default: 'live')

## Error Handling

```php
use Omise\Exceptions\OmiseException;
use Omise\Exceptions\ApiException;
use Omise\Exceptions\ConfigurationException;
use Omise\Exceptions\WebhookException;

try {
    $charge = $omise->payWithPromptPay(100.00);
} catch (ApiException $e) {
    // API error
    echo $e->getMessage();
    echo $e->getOmiseCode();     // e.g., 'invalid_charge'
    echo $e->getHttpStatusCode(); // e.g., 400
} catch (ConfigurationException $e) {
    // Configuration error (missing keys, etc.)
    echo $e->getMessage();
} catch (OmiseException $e) {
    // General Omise error
    echo $e->getMessage();
}
```

## Testing

### Test Mode

Use test keys (prefixed with `_test_`) for development:

```php
$omise = new Omise([
    'public_key' => 'pkey_test_...',
    'secret_key' => 'skey_test_...',
]);

// Check mode
if ($omise->isTestMode()) {
    // Running in test mode
}
```

### Running Tests

```bash
composer test
```

## API Reference

### Omise (Main Class)

| Method | Description |
|--------|-------------|
| `charges()` | Get Charge API |
| `sources()` | Get Source API |
| `events()` | Get Event API |
| `promptPay()` | Get PromptPay payment method |
| `webhooks()` | Get webhook handler |
| `payWithPromptPay($amount, $webhooks)` | Quick PromptPay payment |
| `getCharge($id)` | Retrieve a charge |
| `getEvent($id)` | Retrieve an event |
| `isTestMode()` | Check if in test mode |
| `isLiveMode()` | Check if in live mode |

### PromptPay

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

## License

MIT License

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

- [Omise Documentation](https://docs.omise.co/)
- [Omise Dashboard](https://dashboard.omise.co/)
- [GitHub Issues](https://github.com/yourdomain/omise-php/issues)
