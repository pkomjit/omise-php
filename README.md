# Omise PHP SDK

A PHP SDK for [Omise](https://www.omise.co/) payment gateway integration. Supports both standalone PHP and Laravel applications.

> **Unofficial package.** This is a community-maintained SDK and is not affiliated with or endorsed by Omise / Opn Payments. For the official library see [omise/omise-php](https://github.com/omise/omise-php).

## Features

- PHP 8.3+ support
- Works with or without Laravel
- Multiple payment methods (Credit Card, PromptPay, TrueMoney, Rabbit LINE Pay, Direct Debit, Mobile Banking, ShopeePay)
- Multi-currency support (THB, USD, EUR, GBP, JPY, SGD, and more)
- Token, Customer, Charge, Source, and Event APIs
- 3D Secure authentication support
- Webhook signature verification
- Type-safe with full IDE support

## Supported Payment Methods

| Payment Method | Type | Country | Currency | Limits | Docs |
|----------------|------|---------|----------|--------|------|
| **Credit Card** | Card/3DS | Multi | Multi | Varies | [docs/credit-card.md](docs/credit-card.md) |
| **PromptPay** | QR Code | Thailand | THB | 20 - 150,000 THB | [docs/promptpay.md](docs/promptpay.md) |
| **Rabbit LINE Pay** | Redirect | Thailand | THB | 20 - 150,000 THB | [docs/rabbit-linepay.md](docs/rabbit-linepay.md) |
| **Direct Debit** | Bank Link | Thailand | THB | 20 - 150,000 THB | [docs/direct-debit.md](docs/direct-debit.md) |
| **Mobile Banking** | App Redirect | Thailand/Singapore | THB/SGD | Varies by bank | [docs/mobile-banking.md](docs/mobile-banking.md) |
| **ShopeePay QR** | Redirect | Thailand/Singapore/Malaysia | THB/SGD/MYR | Varies by country | [docs/shopeepay-qr.md](docs/shopeepay-qr.md) |
| **ShopeePay App** | App Redirect | Thailand/Singapore/Malaysia | THB/SGD/MYR | Varies by country | [docs/shopeepay-jumpapp.md](docs/shopeepay-jumpapp.md) |
| **TrueMoney QR** | QR Code | Thailand | THB | 100 - 50,000 THB | [docs/truemoney-qr.md](docs/truemoney-qr.md) |
| **TrueMoney App** | App Redirect | Thailand | THB | 100 - 50,000 THB | [docs/truemoney-jumpapp.md](docs/truemoney-jumpapp.md) |

### Supported Currencies (Multi-Currency)

| Currency | Country | Limits (min - max) |
|----------|---------|-------------------|
| THB | Thailand | 20 - 150,000 |
| USD | USA | 1 - 50,000 |
| EUR | Europe | 1 - 50,000 |
| GBP | UK | 1 - 50,000 |
| JPY | Japan | 100 - 6,000,000 |
| SGD | Singapore | 1 - 20,000 |
| MYR | Malaysia | 1 - 30,000 |
| AUD | Australia | 1 - 50,000 |
| CAD | Canada | 1 - 50,000 |
| HKD | Hong Kong | 1 - 500,000 |

> **Note:** Multi-currency requires activation. Contact support@omise.co to enable.

### Supported Banks

#### Internet Banking / Mobile Banking

| Bank | Code | Type |
|------|------|------|
| Bank of Ayudhya (Krungsri) | `bay` | Internet/Mobile |
| Bangkok Bank | `bbl` | Internet/Mobile |
| Krungthai Bank | `ktb` | Internet/Mobile |
| Siam Commercial Bank | `scb` | Internet/Mobile |
| Kasikorn Bank | `kbank` | Mobile only |

#### Direct Debit

| Bank | Code |
|------|------|
| Bank of Ayudhya (Krungsri) | `direct_debit_bay` |
| Kasikorn Bank | `direct_debit_kbank` |
| Krungthai Bank | `direct_debit_ktb` |
| Siam Commercial Bank | `direct_debit_scb` |

#### Mobile Banking

| Bank | Code | Country |
|------|------|---------|
| Bangkok Bank (Bualuang mBanking) | `mobile_banking_bbl` | Thailand |
| Kasikorn Bank (K PLUS) | `mobile_banking_kbank` | Thailand |
| Krungthai Bank (KTB NEXT) | `mobile_banking_ktb` | Thailand |
| Bank of Ayudhya (KMA) | `mobile_banking_bay` | Thailand |
| Siam Commercial Bank (SCB Easy) | `mobile_banking_scb` | Thailand |
| OCBC Digital | `mobile_banking_ocbc` | Singapore |

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

// Credit Card payment (with 3D Secure)
$charge = $omise->payWithCard($tokenId, 100.00, 'THB', 'https://your-site.com/3ds');

// PromptPay - QR Code payment
$charge = $omise->payWithPromptPay(100.00);
$qrCodeUrl = $omise->promptPay()->getQrCodeUrl($charge);

// TrueMoney QR - QR Code payment
$charge = $omise->payWithTruemoneyQR(100.00);
$qrCodeUrl = $omise->truemoneyQR()->getQrCodeUrl($charge);

// TrueMoney App - Redirect payment
$charge = $omise->payWithTruemoneyJumpApp(100.00, 'https://your-site.com/callback');
$authorizeUrl = $omise->truemoneyJumpApp()->getAuthorizeUri($charge);

// Rabbit LINE Pay - Redirect payment
$charge = $omise->payWithRabbitLinePay(100.00, 'https://your-site.com/callback');
$authorizeUrl = $omise->rabbitLinePay()->getAuthorizeUri($charge);

// Mobile Banking - App redirect payment
use Omise\PaymentMethods\MobileBanking;

$charge = $omise->payWithMobileBanking(
    MobileBanking::BANK_KBANK,
    100.00,
    'https://your-site.com/callback'
);
$authorizeUrl = $omise->mobileBanking()->getAuthorizeUri($charge);

// ShopeePay QR - Redirect payment
$charge = $omise->payWithShopeepayQR(100.00, 'THB', 'https://your-site.com/callback');
$authorizeUrl = $omise->shopeepayQR()->getAuthorizeUri($charge);

// ShopeePay App - App redirect payment
$charge = $omise->payWithShopeepayJumpApp(100.00, 'THB', 'https://your-site.com/callback');
$authorizeUrl = $omise->shopeepayJumpApp()->getAuthorizeUri($charge);
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
OMISE_WEBHOOK_SECRET=whsec_...  # Required to accept webhooks (unsigned webhooks are rejected)
```

3. Use the facade:

```php
use Omise\Laravel\Facades\Omise;

// PromptPay - QR Code payment
$charge = Omise::payWithPromptPay(100.00);
$qrCodeUrl = Omise::promptPay()->getQrCodeUrl($charge);

// TrueMoney QR
$charge = Omise::payWithTruemoneyQR(100.00);

// Rabbit LINE Pay - Redirect payment
$charge = Omise::payWithRabbitLinePay(100.00, route('payment.callback'));
return redirect(Omise::rabbitLinePay()->getAuthorizeUri($charge));

// Mobile Banking - App redirect payment
use Omise\PaymentMethods\MobileBanking;

$charge = Omise::payWithMobileBanking(
    MobileBanking::BANK_KBANK,
    100.00,
    route('payment.callback')
);
return redirect(Omise::mobileBanking()->getAuthorizeUri($charge));

// ShopeePay QR - Redirect payment
$charge = Omise::payWithShopeepayQR(100.00, 'THB', route('payment.callback'));
return redirect(Omise::shopeepayQR()->getAuthorizeUri($charge));

// ShopeePay App - App redirect payment
$charge = Omise::payWithShopeepayJumpApp(100.00, 'THB', route('payment.callback'));
return redirect(Omise::shopeepayJumpApp()->getAuthorizeUri($charge));
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

## Payment Method Documentation

For detailed usage of each payment method, see the documentation in the `docs/` folder:

- [Credit Card](docs/credit-card.md) - Card payments with 3D Secure and multi-currency
- [PromptPay](docs/promptpay.md) - Thailand's national QR payment system
- [Rabbit LINE Pay](docs/rabbit-linepay.md) - LINE app wallet payments
- [Direct Debit](docs/direct-debit.md) - Bank account linking for recurring payments
- [Mobile Banking](docs/mobile-banking.md) - Bank app redirect payments (Thailand/Singapore)
- [ShopeePay QR](docs/shopeepay-qr.md) - ShopeePay QR code payments (Thailand/Singapore/Malaysia)
- [ShopeePay App](docs/shopeepay-jumpapp.md) - ShopeePay app redirect payments (Thailand/Singapore/Malaysia)
- [TrueMoney QR](docs/truemoney-qr.md) - TrueMoney QR code payments
- [TrueMoney App](docs/truemoney-jumpapp.md) - TrueMoney app redirect payments

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
    'default_currency' => 'THB',              // Default currency for payments
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
- `OMISE_WEBHOOK_SECRET` (required for webhooks; without it `webhooks()->handle()` rejects every payload)
- `OMISE_MODE` (optional, default: 'live')
- `OMISE_DEFAULT_CURRENCY` (optional, default: 'THB')

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
| `tokens()` | Get Token API |
| `customers()` | Get Customer API |
| `linkedAccounts()` | Get LinkedAccount API |
| `promptPay()` | Get PromptPay payment method |
| `rabbitLinePay()` | Get Rabbit LINE Pay payment method |
| `creditCard()` | Get Credit Card payment method |
| `directDebit()` | Get Direct Debit payment method |
| `truemoneyQR()` | Get TrueMoney QR payment method |
| `truemoneyJumpApp()` | Get TrueMoney Jump App payment method |
| `mobileBanking()` | Get Mobile Banking payment method |
| `shopeepayQR()` | Get ShopeePay QR payment method |
| `shopeepayJumpApp()` | Get ShopeePay Jump App payment method |
| `webhooks()` | Get webhook handler |
| `payWithPromptPay($amount, $webhooks)` | Quick PromptPay payment |
| `payWithRabbitLinePay($amount, $returnUri, $webhooks)` | Quick Rabbit LINE Pay payment |
| `payWithCard($tokenId, $amount, $currency, $returnUri)` | Quick credit card payment |
| `payWithTruemoneyQR($amount, $webhooks)` | Quick TrueMoney QR payment |
| `payWithTruemoneyJumpApp($amount, $returnUri, $webhooks)` | Quick TrueMoney App payment |
| `payWithMobileBanking($bankType, $amount, $returnUri, $options)` | Quick Mobile Banking payment |
| `payWithShopeepayQR($amount, $currency, $returnUri, $webhooks)` | Quick ShopeePay QR payment |
| `payWithShopeepayJumpApp($amount, $currency, $returnUri, $options)` | Quick ShopeePay App payment |
| `getCharge($id)` | Retrieve a charge |
| `getEvent($id)` | Retrieve an event |
| `getCustomer($id)` | Retrieve a customer |
| `getDefaultCurrency()` | Get the default currency |
| `isTestMode()` | Check if in test mode |
| `isLiveMode()` | Check if in live mode |

### Currency Helper

| Method | Description |
|--------|-------------|
| `Currency::isSupported($currency)` | Check if currency is supported |
| `Currency::toSmallestUnit($amount, $currency)` | Convert to smallest unit |
| `Currency::toMainUnit($amount, $currency)` | Convert to main unit |
| `Currency::format($amount, $currency)` | Format with symbol |
| `Currency::getSymbol($currency)` | Get currency symbol |
| `Currency::getMinimumAmount($currency)` | Get minimum in smallest unit |
| `Currency::getMaximumAmount($currency)` | Get maximum in smallest unit |
| `Currency::validateAmount($amount, $currency)` | Validate amount limits |

## License

MIT License. See [LICENSE](LICENSE).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

- [Omise Documentation](https://docs.omise.co/)
- [Omise Dashboard](https://dashboard.omise.co/)
- [GitHub Issues](https://github.com/yourdomain/omise-php/issues)
