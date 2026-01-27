# Direct Debit

Direct Debit enables secure bank account linking for seamless, recurring payments. Customers link their bank account once through their bank's authentication, then can make payments without re-authentication.

## Overview

| Property | Value |
|----------|-------|
| Type | Bank Link |
| Flow | Redirect (for registration) |
| Country | Thailand |
| Currency | THB |
| Minimum | 20 THB (2,000 satang) |
| Maximum | 150,000 THB (15,000,000 satang) |
| Refund | **Not supported** |

## Supported Banks

| Bank | Constant | Short Code | Note |
|------|----------|------------|------|
| Bank of Ayudhya (Krungsri) | `DirectDebit::BANK_BAY` | BAY | |
| Kasikorn Bank | `DirectDebit::BANK_KBANK` | KBANK | |
| Krungthai Bank | `DirectDebit::BANK_KTB` | KTB | Requires bank contact to delete |
| Siam Commercial Bank | `DirectDebit::BANK_SCB` | SCB | |

## How It Works

1. **Create a linked account** - Generate a registration URL for the customer's bank
2. **Customer authenticates** - Redirect customer to bank for account linking
3. **Create a customer** - Associate the linked account with a customer record
4. **Charge the customer** - Process payments using the linked account

## Step 1: Create a Linked Account

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

## Step 2: Handle Callback and Create Customer

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

## Step 3: Charge the Customer

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

## Adding Linked Account to Existing Customer

```php
// Add a new linked account to an existing customer
$customer = $omise->directDebit()->addLinkedAccountToCustomer(
    customerId: $customerId,
    linkedAccountId: $newLinkedAccountId
);
```

## Deleting a Linked Account

```php
// Delete a linked account
$result = $omise->directDebit()->deleteLinkedAccount($linkedAccountId);

// Note: For Krungthai Bank (KTB), customers must also contact their bank separately
```

## Laravel Usage

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

## Status Helpers

### Linked Account Status

```php
// Pending - waiting for customer to complete bank auth
if ($omise->directDebit()->isLinkedAccountPending($linkedAccount)) { ... }

// Successful - bank account linked
if ($omise->directDebit()->isLinkedAccountSuccessful($linkedAccount)) { ... }

// Failed - registration rejected or error
if ($omise->directDebit()->isLinkedAccountFailed($linkedAccount)) { ... }
```

### Charge Status

```php
// Pending
if ($omise->directDebit()->isPending($charge)) { ... }

// Successful
if ($omise->directDebit()->isSuccessful($charge)) { ... }

// Failed
if ($omise->directDebit()->isFailed($charge)) { ... }
```

## Bank Information

```php
// Get all supported banks
$banks = $omise->directDebit()->getSupportedBanks();
// Returns: ['direct_debit_bay' => 'Bank of Ayudhya (Krungsri)', ...]

// Get bank name
$name = $omise->directDebit()->getBankName(DirectDebit::BANK_KBANK);
// Returns: 'Kasikorn Bank'

// Get bank short code
$code = $omise->directDebit()->getBankCode(DirectDebit::BANK_KBANK);
// Returns: 'KBANK'

// Check if bank is supported
$isSupported = $omise->directDebit()->isSupportedBank('direct_debit_kbank');
// Returns: true
```

## Failure Codes

| Code | Thai Message | English Message |
|------|--------------|-----------------|
| `failed_processing` | ระบบทำรายการไม่สำเร็จ | Payment processing failed |
| `invalid_account` | บัญชีไม่ถูกต้องหรือไม่พบ | Account information invalid or not found |
| `registration_rejected` | ธนาคารปฏิเสธการลงทะเบียน | Bank rejected registration |
| `insufficient_fund` | ยอดเงินไม่เพียงพอ | Insufficient funds or limit exceeded |
| `rate_limit_exceeded` | มีการทำรายการมากเกินไป กรุณารอสักครู่ | Too many requests, please try again later |

## API Reference

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
| `toSatang($thb)` | Convert THB to satang |
| `toThb($satang)` | Convert satang to THB |

## Notes

- **Direct Debit charges cannot be refunded**
- Bank account linking is a one-time process per customer/bank
- For Krungthai Bank (KTB), customers must also contact their bank to delete linked accounts
- Linked accounts can be attached to customer records for easier management
