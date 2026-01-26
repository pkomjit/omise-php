<?php

declare(strict_types=1);

namespace Omise\PaymentMethods;

use Omise\Api\Charge;
use Omise\Api\Customer;
use Omise\Api\LinkedAccount;
use Omise\Currency;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Direct Debit payment method implementation.
 *
 * Direct Debit enables secure bank account linking for seamless, recurring payments.
 * Customers link their bank account once, then can make payments without re-authentication.
 *
 * Flow:
 * 1. Create a linked account with bank type and return_uri
 * 2. Redirect customer to registration_uri for bank authentication
 * 3. Create a customer and attach the linked account
 * 4. Charge the customer using the linked account
 *
 * Note: Direct Debit charges cannot be refunded.
 *
 * @see https://docs.omise.co/direct-debit
 */
class DirectDebit
{
    protected Charge $chargeApi;
    protected Customer $customerApi;
    protected LinkedAccount $linkedAccountApi;

    /**
     * Bank type constants.
     */
    public const string BANK_BAY = 'direct_debit_bay';    // Bank of Ayudhya
    public const string BANK_KBANK = 'direct_debit_kbank'; // Kasikorn Bank
    public const string BANK_KTB = 'direct_debit_ktb';     // Krungthai Bank
    public const string BANK_SCB = 'direct_debit_scb';     // Siam Commercial Bank

    /**
     * Bank display names.
     */
    private const array BANK_NAMES = [
        self::BANK_BAY => 'Bank of Ayudhya (Krungsri)',
        self::BANK_KBANK => 'Kasikorn Bank',
        self::BANK_KTB => 'Krungthai Bank',
        self::BANK_SCB => 'Siam Commercial Bank',
    ];

    /**
     * Bank short codes.
     */
    private const array BANK_CODES = [
        self::BANK_BAY => 'BAY',
        self::BANK_KBANK => 'KBANK',
        self::BANK_KTB => 'KTB',
        self::BANK_SCB => 'SCB',
    ];

    /**
     * Failure codes.
     */
    public const string FAILURE_PROCESSING = 'failed_processing';
    public const string FAILURE_INVALID_ACCOUNT = 'invalid_account';
    public const string FAILURE_REGISTRATION_REJECTED = 'registration_rejected';
    public const string FAILURE_INSUFFICIENT_FUND = 'insufficient_fund';
    public const string FAILURE_RATE_LIMIT = 'rate_limit_exceeded';

    /**
     * Limits in satang.
     */
    protected const int MIN_AMOUNT = 2000;      // 20 THB
    protected const int MAX_AMOUNT = 15000000;  // 150,000 THB

    public function __construct(
        Charge $chargeApi,
        Customer $customerApi,
        LinkedAccount $linkedAccountApi
    ) {
        $this->chargeApi = $chargeApi;
        $this->customerApi = $customerApi;
        $this->linkedAccountApi = $linkedAccountApi;
    }

    /**
     * Step 1: Create a linked account for bank authentication.
     *
     * @param string $bankType Bank type constant (e.g., BANK_KBANK)
     * @param string $returnUri URL to redirect after bank registration
     * @param string|null $citizenId Optional citizen ID for pre-validation
     * @return Response Contains registration_uri to redirect customer
     * @throws ApiException
     */
    public function createLinkedAccount(
        string $bankType,
        string $returnUri,
        ?string $citizenId = null
    ): Response {
        return $this->linkedAccountApi->createForBank($bankType, $returnUri, $citizenId);
    }

    /**
     * Get the registration URI to redirect customer for bank authentication.
     */
    public function getRegistrationUri(Response $linkedAccount): ?string
    {
        return $this->linkedAccountApi->getRegistrationUri($linkedAccount);
    }

    /**
     * Step 2: Create or update a customer with a linked account.
     *
     * @param string $linkedAccountId Linked account ID
     * @param string|null $email Customer email
     * @param string|null $description Customer description
     * @throws ApiException
     */
    public function createCustomerWithLinkedAccount(
        string $linkedAccountId,
        ?string $email = null,
        ?string $description = null
    ): Response {
        $params = [
            'linked_account' => $linkedAccountId,
        ];

        if ($email !== null) {
            $params['email'] = $email;
        }

        if ($description !== null) {
            $params['description'] = $description;
        }

        return $this->customerApi->create($params);
    }

    /**
     * Add a linked account to an existing customer.
     *
     * @param string $customerId Customer ID
     * @param string $linkedAccountId Linked account ID
     * @throws ApiException
     */
    public function addLinkedAccountToCustomer(
        string $customerId,
        string $linkedAccountId
    ): Response {
        return $this->customerApi->update($customerId, [
            'linked_account' => $linkedAccountId,
        ]);
    }

    /**
     * Step 3: Charge a customer using a linked account.
     *
     * @param string $customerId Customer ID
     * @param string $linkedAccountId Linked account ID
     * @param int $amount Amount in satang (smallest currency unit)
     * @param array $options Additional options:
     *   - description: Charge description
     *   - metadata: Custom metadata
     *   - webhook_endpoints: Webhook URLs
     * @throws ApiException
     */
    public function charge(
        string $customerId,
        string $linkedAccountId,
        int $amount,
        array $options = []
    ): Response {
        $this->validateAmount($amount);

        $params = array_merge($options, [
            'amount' => $amount,
            'currency' => 'THB',
            'customer' => $customerId,
            'linked_account' => $linkedAccountId,
        ]);

        return $this->chargeApi->create($params);
    }

    /**
     * Charge with amount in THB (main unit).
     *
     * @param string $customerId Customer ID
     * @param string $linkedAccountId Linked account ID
     * @param float $amount Amount in THB (will be converted to satang)
     * @param array $options Additional options
     * @throws ApiException
     */
    public function pay(
        string $customerId,
        string $linkedAccountId,
        float $amount,
        array $options = []
    ): Response {
        $amountInSatang = Currency::toSmallestUnit($amount, 'THB');

        return $this->charge($customerId, $linkedAccountId, $amountInSatang, $options);
    }

    /**
     * Delete a linked account.
     *
     * Note: For Krungthai Bank, customers must also contact their bank separately.
     *
     * @param string $linkedAccountId Linked account ID
     * @throws ApiException
     */
    public function deleteLinkedAccount(string $linkedAccountId): Response
    {
        return $this->linkedAccountApi->destroy($linkedAccountId);
    }

    /**
     * Retrieve a linked account by ID.
     *
     * @throws ApiException
     */
    public function getLinkedAccount(string $linkedAccountId): Response
    {
        return $this->linkedAccountApi->retrieve($linkedAccountId);
    }

    /**
     * Check if the linked account registration is pending.
     */
    public function isLinkedAccountPending(Response $linkedAccount): bool
    {
        return $this->linkedAccountApi->isPending($linkedAccount);
    }

    /**
     * Check if the linked account registration is successful.
     */
    public function isLinkedAccountSuccessful(Response $linkedAccount): bool
    {
        return $this->linkedAccountApi->isSuccessful($linkedAccount);
    }

    /**
     * Check if the linked account registration has failed.
     */
    public function isLinkedAccountFailed(Response $linkedAccount): bool
    {
        return $this->linkedAccountApi->isFailed($linkedAccount);
    }

    /**
     * Check if the charge is pending.
     */
    public function isPending(Response $charge): bool
    {
        return $charge->get('status') === 'pending';
    }

    /**
     * Check if the charge is successful.
     */
    public function isSuccessful(Response $charge): bool
    {
        return $charge->get('status') === 'successful';
    }

    /**
     * Check if the charge has failed.
     */
    public function isFailed(Response $charge): bool
    {
        return $charge->get('status') === 'failed';
    }

    /**
     * Get the failure code from a failed charge or linked account.
     */
    public function getFailureCode(Response $response): ?string
    {
        return $response->get('failure_code');
    }

    /**
     * Get a human-readable failure message.
     *
     * @param Response $response The charge or linked account response
     * @param string $locale Locale for message ('en' or 'th')
     */
    public function getFailureMessage(Response $response, string $locale = 'th'): ?string
    {
        $code = $this->getFailureCode($response);

        if ($locale === 'th') {
            return match ($code) {
                self::FAILURE_PROCESSING => 'ระบบทำรายการไม่สำเร็จ',
                self::FAILURE_INVALID_ACCOUNT => 'บัญชีไม่ถูกต้องหรือไม่พบ',
                self::FAILURE_REGISTRATION_REJECTED => 'ธนาคารปฏิเสธการลงทะเบียน',
                self::FAILURE_INSUFFICIENT_FUND => 'ยอดเงินไม่เพียงพอ',
                self::FAILURE_RATE_LIMIT => 'มีการทำรายการมากเกินไป กรุณารอสักครู่',
                default => $response->get('failure_message'),
            };
        }

        return match ($code) {
            self::FAILURE_PROCESSING => 'Payment processing failed',
            self::FAILURE_INVALID_ACCOUNT => 'Account information invalid or not found',
            self::FAILURE_REGISTRATION_REJECTED => 'Bank rejected registration',
            self::FAILURE_INSUFFICIENT_FUND => 'Insufficient funds or limit exceeded',
            self::FAILURE_RATE_LIMIT => 'Too many requests, please try again later',
            default => $response->get('failure_message'),
        };
    }

    /**
     * Get all supported banks.
     *
     * @return array<string, string> Bank type => Bank name
     */
    public function getSupportedBanks(): array
    {
        return self::BANK_NAMES;
    }

    /**
     * Get bank name for a type.
     */
    public function getBankName(string $bankType): string
    {
        return self::BANK_NAMES[$bankType] ?? $bankType;
    }

    /**
     * Get bank short code for a type.
     */
    public function getBankCode(string $bankType): string
    {
        return self::BANK_CODES[$bankType] ?? $bankType;
    }

    /**
     * Check if a bank type is supported.
     */
    public function isSupportedBank(string $bankType): bool
    {
        return isset(self::BANK_NAMES[$bankType]);
    }

    /**
     * Get minimum amount in satang.
     */
    public function getMinimumAmount(): int
    {
        return self::MIN_AMOUNT;
    }

    /**
     * Get maximum amount in satang.
     */
    public function getMaximumAmount(): int
    {
        return self::MAX_AMOUNT;
    }

    /**
     * Get minimum amount in THB.
     */
    public function getMinimumThb(): float
    {
        return Currency::toMainUnit(self::MIN_AMOUNT, 'THB');
    }

    /**
     * Get maximum amount in THB.
     */
    public function getMaximumThb(): float
    {
        return Currency::toMainUnit(self::MAX_AMOUNT, 'THB');
    }

    /**
     * Validate amount is within limits.
     */
    public function validateAmount(int $amount): bool
    {
        if ($amount < self::MIN_AMOUNT) {
            throw new \InvalidArgumentException(
                "Amount must be at least " . self::MIN_AMOUNT . " satang (20 THB)"
            );
        }

        if ($amount > self::MAX_AMOUNT) {
            throw new \InvalidArgumentException(
                "Amount must not exceed " . self::MAX_AMOUNT . " satang (150,000 THB)"
            );
        }

        return true;
    }

    /**
     * Validate amount in THB.
     */
    public function validateThbAmount(float $amount): bool
    {
        $satang = Currency::toSmallestUnit($amount, 'THB');

        return $this->validateAmount($satang);
    }

    /**
     * Convert THB to satang.
     */
    public static function toSatang(float $thb): int
    {
        return Currency::toSmallestUnit($thb, 'THB');
    }

    /**
     * Convert satang to THB.
     */
    public static function toThb(int $satang): float
    {
        return Currency::toMainUnit($satang, 'THB');
    }

    /**
     * Note: Direct Debit charges cannot be refunded.
     */
    public function canRefund(): bool
    {
        return false;
    }
}
