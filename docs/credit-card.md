# Credit Card

Accept credit/debit card payments with support for 3D Secure authentication and multi-currency.

## Overview

| Property | Value |
|----------|-------|
| Type | Card / 3D Secure |
| Flow | `redirect` (for 3DS) |
| Country | Multi-country |
| Currency | Multi-currency |
| Minimum | Varies by currency |
| Maximum | Varies by currency |
| Refund | Supported |

## Supported Card Brands

| Brand | Constant |
|-------|----------|
| Visa | `CreditCard::BRAND_VISA` |
| MasterCard | `CreditCard::BRAND_MASTERCARD` |
| JCB | `CreditCard::BRAND_JCB` |
| American Express | `CreditCard::BRAND_AMEX` |

## Creating a Token

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

> **Note:** In production, tokens should be created client-side using Omise.js to avoid handling card data on your server.

## Charging a Card

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

## Multi-Currency Payments

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

## Authorization and Capture

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

## Saved Cards (Customer)

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

## Card Information

```php
// Get card details from charge
$brand = $omise->creditCard()->getCardBrand($charge);      // 'Visa'
$lastDigits = $omise->creditCard()->getCardLastDigits($charge); // '4242'
$cardInfo = $omise->creditCard()->getCardInfo($charge);    // Full card array
```

## Fees and Net Amount

```php
$netAmount = $omise->creditCard()->getNetAmount($charge);  // Amount after fees
$fee = $omise->creditCard()->getFee($charge);              // Fee amount
```

## Status Helpers

```php
// Pending (awaiting 3DS or processing)
if ($omise->creditCard()->isPending($charge)) { ... }

// Successful
if ($omise->creditCard()->isSuccessful($charge)) { ... }

// Failed
if ($omise->creditCard()->isFailed($charge)) { ... }

// Authorized but not captured
if ($omise->creditCard()->isAuthorized($charge)) { ... }

// Can be captured
if ($omise->creditCard()->isCapturable($charge)) { ... }

// Reversed/voided
if ($omise->creditCard()->isReversed($charge)) { ... }

// Can be refunded
if ($omise->creditCard()->isRefundable($charge)) { ... }
```

## Failure Codes

| Code | Thai Message | English Message |
|------|--------------|-----------------|
| `invalid_card` | บัตรไม่ถูกต้อง | Invalid card |
| `insufficient_fund` | วงเงินไม่เพียงพอ | Insufficient funds |
| `stolen_or_lost_card` | บัตรถูกแจ้งหาย | Card reported as stolen or lost |
| `failed_fraud_check` | ไม่ผ่านการตรวจสอบความปลอดภัย | Failed fraud check |
| `invalid_security_code` | รหัสความปลอดภัยไม่ถูกต้อง | Invalid security code |
| `payment_rejected` | การชำระเงินถูกปฏิเสธ | Payment rejected |

## API Reference

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
| `getCardBrand($charge)` | Get card brand |
| `getCardLastDigits($charge)` | Get last 4 digits |
| `getFundingAmount($charge)` | Get settlement amount |
| `getFundingCurrency($charge)` | Get settlement currency |
| `isMultiCurrency($charge)` | Check if multi-currency |
| `getFailureCode($charge)` | Get failure code |
| `getFailureMessage($charge, $locale)` | Get failure message |
| `validateAmount($amount, $currency)` | Validate amount limits |
| `getMinimumAmount($currency)` | Get minimum for currency |
| `getMaximumAmount($currency)` | Get maximum for currency |

## Notes

- Multi-currency requires activation. Contact support@omise.co to enable.
- 3D Secure is required for most card transactions
- Tokens can only be used once; use customers for recurring payments
