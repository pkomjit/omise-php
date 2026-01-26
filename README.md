# Omise PHP SDK

A PHP SDK for [Omise](https://www.omise.co/) payment gateway integration. Supports both standalone PHP and Laravel applications.

## Features

- PHP 8.3+ support
- Works with or without Laravel
- Multiple payment methods (Credit Card, PromptPay, Rabbit LINE Pay, and more)
- Multi-currency support (THB, USD, EUR, GBP, JPY, SGD, and more)
- Token, Customer, Charge, Source, and Event APIs
- 3D Secure authentication support
- Webhook signature verification
- Type-safe with full IDE support

## Supported Payment Methods

| Payment Method       | Type              | Country       | Currency      | Limits         | Status            |
|----------------------|-------------------|---------------|---------------|----------------|-------------------|
| **Credit Card**      | Card/3DS          | Multi-country | Multi         | Varies         | Ready             |
| **PromptPay**        | QR Code (Offline) | Thailand      | THB           | 20 - 150,000   | Ready             |
| **Rabbit LINE Pay**  | Redirect          | Thailand      | THB           | 20 - 150,000   | Ready             |
| **Direct Debit**     | Bank Link         | Thailand      | THB           | 20 - 150,000   | Ready             |
| **TrueMoney Wallet** | Redirect          | Thailand      | THB           | 20 - 30,000    | Under development |
| **Internet Banking** | Redirect          | Thailand      | THB           | Varies by bank | Under development |
| **Mobile Banking**   | App Redirect      | Thailand      | THB           | Varies by bank | Under development |
| **Alipay**           | Redirect          | China         | THB           | 20 - 150,000   | Under development |
| **GrabPay**          | App Redirect      | Thailand      | THB           | 20 - 150,000   | Under development |
| **ShopeePay**        | App Redirect      | Thailand      | THB           | 20 - 150,000   | Under development |

### Supported Currencies (Multi-Currency)

| Currency | Country     | Limits (min - max) |
|----------|-------------|-------------------|
| THB      | Thailand    | 20 - 150,000      |
| USD      | USA         | 1 - 50,000        |
| EUR      | Europe      | 1 - 50,000        |
| GBP      | UK          | 1 - 50,000        |
| JPY      | Japan       | 100 - 6,000,000   |
| SGD      | Singapore   | 1 - 20,000        |
| MYR      | Malaysia    | 1 - 30,000        |
| AUD      | Australia   | 1 - 50,000        |
| CAD      | Canada      | 1 - 50,000        |
| HKD      | Hong Kong   | 1 - 500,000       |

> **Note:** Multi-currency requires activation. Contact support@omise.co to enable.

### Supported Banks (Internet Banking)

| Bank                       | Code    | Type            |
|----------------------------|---------|-----------------|
| Bank of Ayudhya (Krungsri) | `bay`   | Internet/Mobile |
| Bangkok Bank               | `bbl`   | Internet/Mobile |
| Krungthai Bank             | `ktb`   | Internet/Mobile |
| Siam Commercial Bank       | `scb`   | Internet/Mobile |
| Kasikorn Bank              | `kbank` | Mobile only     |

### Supported Banks (Direct Debit)

| Bank                       | Code                 | Note                           |
|----------------------------|----------------------|--------------------------------|
| Bank of Ayudhya (Krungsri) | `direct_debit_bay`   |                                |
| Kasikorn Bank              | `direct_debit_kbank` |                                |
| Krungthai Bank             | `direct_debit_ktb`   | Requires bank contact to delete |
| Siam Commercial Bank       | `direct_debit_scb`   |                                |

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

// PromptPay - QR Code payment (100 THB)
$charge = $omise->payWithPromptPay(100.00);
$qrCodeUrl = $omise->promptPay()->getQrCodeUrl($charge);

// Rabbit LINE Pay - Redirect payment (100 THB)
$charge = $omise->payWithRabbitLinePay(100.00, 'https://your-site.com/callback');
$authorizeUrl = $omise->rabbitLinePay()->getAuthorizeUri($charge);
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

// PromptPay - QR Code payment
$charge = Omise::payWithPromptPay(100.00);
$qrCodeUrl = Omise::promptPay()->getQrCodeUrl($charge);

// Rabbit LINE Pay - Redirect payment
$charge = Omise::payWithRabbitLinePay(100.00, route('payment.callback'));
return redirect(Omise::rabbitLinePay()->getAuthorizeUri($charge));
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

- Minimum: 20 THB
- Maximum: 150,000 THB
- QR code expires after 24 hours by default

## Rabbit LINE Pay Usage

Rabbit LINE Pay is a mobile e-wallet service integrated with LINE Messenger. It uses a redirect flow where customers authorize payment in the LINE app.

```php
use Omise\Omise;

$omise = new Omise([
    'public_key' => 'pkey_...',
    'secret_key' => 'skey_...',
]);

// Simple payment (amount in THB, return_uri required)
$charge = $omise->rabbitLinePay()->pay(
    100.00,
    'https://your-site.com/payment/complete'
);

// Get the authorization URL and redirect customer
$authorizeUrl = $omise->rabbitLinePay()->getAuthorizeUri($charge);
header("Location: {$authorizeUrl}");
exit;

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

// Check refund eligibility (within 60 days)
if ($omise->rabbitLinePay()->canRefund($charge)) {
    $deadline = $omise->rabbitLinePay()->getRefundDeadline($charge);
}
```

### Laravel Usage

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

### Rabbit LINE Pay Limits

- Minimum: 20 THB
- Maximum: 150,000 THB
- Refunds supported within 60 days

### Failure Codes

| Code | Thai Message | English Message |
|------|--------------|-----------------|
| `failed_processing` | ระบบทำรายการไม่สำเร็จ | Payment processing failed |
| `insufficient_balance` | วงเงินคงเหลือไม่เพียงพอ | Insufficient balance |
| `payment_cancelled` | ผู้ซื้อยกเลิกการชำระเงิน | Payment was cancelled by customer |
| `timeout` | หมดเวลาในการชำระเงิน | Payment timed out |

## Credit Card Usage

Accept credit/debit card payments with support for 3D Secure authentication and multi-currency.

### Creating a Token

First, create a token from card details (typically done client-side with Omise.js):

```php
use Omise\Omise;

$omise = new Omise([
    'public_key' => 'pkey_...',
    'secret_key' => 'skey_...',
]);

// Create a card token (server-side - for testing only)
$token = $omise->tokens()->createFromCard(
    name: 'John Doe',
    number: '4242424242424242',
    expirationMonth: 12,
    expirationYear: 2025,
    securityCode: '123'
);

$tokenId = $token->getId(); // tokn_test_...
```

### Charging a Card

```php
// Simple charge with token
$charge = $omise->payWithCard(
    tokenId: $tokenId,
    amount: 100.00,         // 100 THB
    currency: 'THB',
    returnUri: 'https://your-site.com/3ds-callback'  // For 3D Secure
);

// Check if 3D Secure is required
if ($omise->creditCard()->requires3DSecure($charge)) {
    // Redirect customer to 3DS page
    $authorizeUrl = $omise->creditCard()->getAuthorizeUri($charge);
    header("Location: {$authorizeUrl}");
    exit;
}

// Check payment status
if ($omise->creditCard()->isSuccessful($charge)) {
    // Payment successful
}
```

### Multi-Currency Payments

```php
// Charge in USD (will settle in your funding currency)
$charge = $omise->creditCard()->pay(
    tokenId: $tokenId,
    amount: 50.00,       // 50 USD
    currency: 'USD',
    returnUri: 'https://your-site.com/callback'
);

// Check multi-currency details
if ($omise->creditCard()->isMultiCurrency($charge)) {
    $fundingAmount = $omise->creditCard()->getFundingAmount($charge);   // Amount in THB
    $fundingCurrency = $omise->creditCard()->getFundingCurrency($charge); // 'THB'
}
```

### Authorization and Capture

For two-step payments (authorize first, capture later):

```php
// Step 1: Authorize only (no capture)
$charge = $omise->creditCard()->authorize(
    tokenId: $tokenId,
    amount: 10000,     // 100 THB in satang
    currency: 'THB',
    returnUri: 'https://your-site.com/callback'
);

// Check if authorized
if ($omise->creditCard()->isAuthorized($charge)) {
    // Card is authorized, capture later
}

// Step 2: Capture the authorized charge
$charge = $omise->creditCard()->capture($charge->getId());

// Or capture partial amount
$charge = $omise->creditCard()->capture($charge->getId(), 5000); // Capture 50 THB

// Reverse (void) an uncaptured charge
$charge = $omise->creditCard()->reverse($charge->getId());
```

### Saved Cards (Customer)

Save cards for recurring payments:

```php
// Create a customer with a card
$customer = $omise->customers()->createWithCard(
    email: 'customer@example.com',
    tokenId: $tokenId,
    description: 'John Doe'
);

$customerId = $customer->getId(); // cust_test_...

// Charge customer's default card
$charge = $omise->creditCard()->chargeCustomer(
    customerId: $customerId,
    amount: 10000,
    currency: 'THB'
);

// Add another card to customer
$omise->customers()->addCard($customerId, $newTokenId);

// Charge a specific card
$cards = $omise->customers()->getCards($customer);
$cardId = $cards[0]['id'];

$charge = $omise->creditCard()->chargeCustomerCard(
    customerId: $customerId,
    cardId: $cardId,
    amount: 10000,
    currency: 'THB'
);
```

### Card Failure Codes

| Code | Thai Message | English Message |
|------|--------------|-----------------|
| `invalid_card` | บัตรไม่ถูกต้อง | Invalid card |
| `insufficient_fund` | วงเงินไม่เพียงพอ | Insufficient funds |
| `stolen_or_lost_card` | บัตรถูกแจ้งหาย | Card reported as stolen or lost |
| `failed_fraud_check` | ไม่ผ่านการตรวจสอบความปลอดภัย | Failed fraud check |
| `invalid_security_code` | รหัสความปลอดภัยไม่ถูกต้อง | Invalid security code |
| `payment_rejected` | การชำระเงินถูกปฏิเสธ | Payment rejected |

## Direct Debit Usage

Direct Debit enables secure bank account linking for seamless, recurring payments. Customers link their bank account once through their bank's authentication, then can make payments without re-authentication.

### Flow Overview

1. **Create a linked account** - Generate a registration URL for the customer's bank
2. **Customer authenticates** - Redirect customer to bank for account linking
3. **Create a customer** - Associate the linked account with a customer record
4. **Charge the customer** - Process payments using the linked account

### Step 1: Create a Linked Account

```php
use Omise\Omise;
use Omise\PaymentMethods\DirectDebit;

$omise = new Omise([
    'public_key' => 'pkey_...',
    'secret_key' => 'skey_...',
]);

// Create a linked account for Kasikorn Bank
$linkedAccount = $omise->directDebit()->createLinkedAccount(
    bankType: DirectDebit::BANK_KBANK,
    returnUri: 'https://your-site.com/direct-debit/callback'
);

// Get the registration URL and redirect customer
$registrationUrl = $omise->directDebit()->getRegistrationUri($linkedAccount);
header("Location: {$registrationUrl}");
exit;
```

### Step 2: Handle Callback and Create Customer

After the customer completes bank authentication, they're redirected back to your `return_uri`:

```php
// Get the linked account ID from the callback
$linkedAccountId = $_GET['linked_account']; // lacct_...

// Check if registration was successful
$linkedAccount = $omise->directDebit()->getLinkedAccount($linkedAccountId);

if ($omise->directDebit()->isLinkedAccountSuccessful($linkedAccount)) {
    // Create a customer with the linked account
    $customer = $omise->directDebit()->createCustomerWithLinkedAccount(
        linkedAccountId: $linkedAccountId,
        email: 'customer@example.com',
        description: 'John Doe'
    );

    // Store $customer->getId() for future charges
    $customerId = $customer->getId(); // cust_...
}

if ($omise->directDebit()->isLinkedAccountFailed($linkedAccount)) {
    $errorMessage = $omise->directDebit()->getFailureMessage($linkedAccount, 'th');
    // Handle error
}
```

### Step 3: Charge the Customer

```php
// Charge using the linked account (amount in THB)
$charge = $omise->directDebit()->pay(
    customerId: $customerId,
    linkedAccountId: $linkedAccountId,
    amount: 100.00,  // 100 THB
    options: [
        'description' => 'Monthly subscription',
        'metadata' => ['order_id' => 'ORD-123'],
    ]
);

// Or charge in satang for precise amounts
$charge = $omise->directDebit()->charge(
    customerId: $customerId,
    linkedAccountId: $linkedAccountId,
    amount: 10000,  // 100 THB in satang
);

// Check charge status
if ($omise->directDebit()->isSuccessful($charge)) {
    // Payment successful
}

if ($omise->directDebit()->isFailed($charge)) {
    $errorCode = $omise->directDebit()->getFailureCode($charge);
    $errorMessage = $omise->directDebit()->getFailureMessage($charge, 'th');
}
```

### Adding Linked Account to Existing Customer

```php
// Add a new linked account to an existing customer
$customer = $omise->directDebit()->addLinkedAccountToCustomer(
    customerId: $customerId,
    linkedAccountId: $newLinkedAccountId
);
```

### Deleting a Linked Account

```php
// Delete a linked account
$result = $omise->directDebit()->deleteLinkedAccount($linkedAccountId);

// Note: For Krungthai Bank (KTB), customers must also contact their bank separately
```

### Laravel Usage

```php
use Omise\Laravel\Facades\Omise;
use Omise\PaymentMethods\DirectDebit;

// In your controller
public function linkBank(Request $request)
{
    $linkedAccount = Omise::directDebit()->createLinkedAccount(
        bankType: $request->bank_type, // e.g., DirectDebit::BANK_KBANK
        returnUri: route('direct-debit.callback')
    );

    return redirect(Omise::directDebit()->getRegistrationUri($linkedAccount));
}

public function callback(Request $request)
{
    $linkedAccount = Omise::directDebit()->getLinkedAccount($request->linked_account);

    if (Omise::directDebit()->isLinkedAccountSuccessful($linkedAccount)) {
        // Create customer and store for future charges
        $customer = Omise::directDebit()->createCustomerWithLinkedAccount(
            linkedAccountId: $request->linked_account,
            email: auth()->user()->email
        );

        // Store customer ID and linked account ID in your database
        auth()->user()->update([
            'omise_customer_id' => $customer->getId(),
            'omise_linked_account_id' => $request->linked_account,
        ]);

        return redirect()->route('dashboard')->with('success', 'Bank account linked successfully');
    }

    return redirect()->route('settings')->with('error', 'Bank linking failed');
}

public function charge(Request $request)
{
    $user = auth()->user();

    $charge = Omise::directDebit()->pay(
        customerId: $user->omise_customer_id,
        linkedAccountId: $user->omise_linked_account_id,
        amount: $request->amount
    );

    if (Omise::directDebit()->isSuccessful($charge)) {
        return response()->json(['success' => true]);
    }

    return response()->json([
        'success' => false,
        'message' => Omise::directDebit()->getFailureMessage($charge)
    ], 400);
}
```

### Direct Debit Limits

- Minimum: 20 THB
- Maximum: 150,000 THB
- **Note:** Direct Debit charges cannot be refunded

### Direct Debit Failure Codes

| Code | Thai Message | English Message |
|------|--------------|-----------------|
| `failed_processing` | ระบบทำรายการไม่สำเร็จ | Payment processing failed |
| `invalid_account` | บัญชีไม่ถูกต้องหรือไม่พบ | Account information invalid or not found |
| `registration_rejected` | ธนาคารปฏิเสธการลงทะเบียน | Bank rejected registration |
| `insufficient_fund` | ยอดเงินไม่เพียงพอ | Insufficient funds or limit exceeded |
| `rate_limit_exceeded` | มีการทำรายการมากเกินไป กรุณารอสักครู่ | Too many requests, please try again later |

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
- `OMISE_WEBHOOK_SECRET` (optional)
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
| `webhooks()` | Get webhook handler |
| `payWithPromptPay($amount, $webhooks)` | Quick PromptPay payment |
| `payWithRabbitLinePay($amount, $returnUri, $webhooks)` | Quick Rabbit LINE Pay payment |
| `payWithCard($tokenId, $amount, $currency, $returnUri)` | Quick credit card payment |
| `getCharge($id)` | Retrieve a charge |
| `getEvent($id)` | Retrieve an event |
| `getCustomer($id)` | Retrieve a customer |
| `getDefaultCurrency()` | Get the default currency |
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

### Rabbit LINE Pay

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

### Credit Card

| Method | Description |
|--------|-------------|
| `createToken($name, $number, $month, $year, $cvv)` | Create a card token |
| `pay($tokenId, $amount, $currency, $returnUri)` | Charge a card |
| `chargeToken($tokenId, $amount, $currency, $options)` | Charge with options |
| `chargeCustomer($customerId, $amount, $currency)` | Charge customer's default card |
| `chargeCustomerCard($customerId, $cardId, $amount, $currency)` | Charge specific card |
| `authorize($tokenId, $amount, $currency)` | Authorize without capture |
| `capture($chargeId, $amount)` | Capture an authorized charge |
| `reverse($chargeId)` | Void an uncaptured charge |
| `requires3DSecure($charge)` | Check if 3DS is required |
| `getAuthorizeUri($charge)` | Get 3DS redirect URL |
| `isAuthorized($charge)` | Check if authorized |
| `isCapturable($charge)` | Check if can capture |
| `isPending($charge)` | Check if pending |
| `isSuccessful($charge)` | Check if successful |
| `isFailed($charge)` | Check if failed |
| `isRefundable($charge)` | Check if refundable |
| `getCardBrand($charge)` | Get card brand (Visa, etc.) |
| `getCardLastDigits($charge)` | Get last 4 digits |
| `getFundingAmount($charge)` | Get settlement amount |
| `getFundingCurrency($charge)` | Get settlement currency |
| `isMultiCurrency($charge)` | Check if multi-currency |
| `getFailureCode($charge)` | Get failure code |
| `getFailureMessage($charge, $locale)` | Get failure message |

### Direct Debit

| Method | Description |
|--------|-------------|
| `createLinkedAccount($bankType, $returnUri, $citizenId)` | Create a linked account for bank auth |
| `getRegistrationUri($linkedAccount)` | Get URL for bank registration |
| `getLinkedAccount($linkedAccountId)` | Retrieve a linked account |
| `deleteLinkedAccount($linkedAccountId)` | Delete a linked account |
| `createCustomerWithLinkedAccount($linkedAccountId, $email, $desc)` | Create customer with linked account |
| `addLinkedAccountToCustomer($customerId, $linkedAccountId)` | Add linked account to existing customer |
| `charge($customerId, $linkedAccountId, $amount, $options)` | Charge in satang |
| `pay($customerId, $linkedAccountId, $amount, $options)` | Charge in THB |
| `isLinkedAccountPending($linkedAccount)` | Check if registration pending |
| `isLinkedAccountSuccessful($linkedAccount)` | Check if registration successful |
| `isLinkedAccountFailed($linkedAccount)` | Check if registration failed |
| `isPending($charge)` | Check if charge pending |
| `isSuccessful($charge)` | Check if charge successful |
| `isFailed($charge)` | Check if charge failed |
| `getFailureCode($response)` | Get failure code |
| `getFailureMessage($response, $locale)` | Get failure message ('th' or 'en') |
| `getSupportedBanks()` | Get all supported banks |
| `getBankName($bankType)` | Get bank display name |
| `getBankCode($bankType)` | Get bank short code |
| `isSupportedBank($bankType)` | Check if bank is supported |
| `canRefund()` | Returns false (no refunds) |
| `getMinimumAmount()` | Get min amount in satang (2000) |
| `getMaximumAmount()` | Get max amount in satang (15000000) |
| `getMinimumThb()` | Get min amount in THB (20) |
| `getMaximumThb()` | Get max amount in THB (150000) |

### LinkedAccount API

| Method | Description |
|--------|-------------|
| `create($params)` | Create a linked account |
| `createForBank($bankType, $returnUri, $citizenId)` | Create with simplified params |
| `retrieve($linkedAccountId)` | Get a linked account |
| `all($params)` | List all linked accounts |
| `destroy($linkedAccountId)` | Delete a linked account |
| `getRegistrationUri($linkedAccount)` | Get registration URL |
| `getType($linkedAccount)` | Get bank type |
| `getStatus($linkedAccount)` | Get status |
| `isPending($linkedAccount)` | Check if pending |
| `isSuccessful($linkedAccount)` | Check if successful |
| `isFailed($linkedAccount)` | Check if failed |
| `isDeleted($linkedAccount)` | Check if deleted |
| `getBankName($type)` | Get bank display name |
| `getSupportedBanks()` | Get supported bank types |
| `isSupportedBank($type)` | Check if bank supported |

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

MIT License

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

- [Omise Documentation](https://docs.omise.co/)
- [Omise Dashboard](https://dashboard.omise.co/)
- [GitHub Issues](https://github.com/yourdomain/omise-php/issues)
