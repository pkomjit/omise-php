<?php

declare(strict_types=1);

namespace Omise\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Omise\Api\Account;
use Omise\Api\Balance;
use Omise\Api\Capability;
use Omise\Api\Card;
use Omise\Api\Chain;
use Omise\Api\Charge;
use Omise\Api\Customer;
use Omise\Api\Dispute;
use Omise\Api\Document;
use Omise\Api\Event;
use Omise\Api\Forex;
use Omise\Api\Link;
use Omise\Api\LinkedAccount;
use Omise\Api\Occurrence;
use Omise\Api\Receipt;
use Omise\Api\Recipient;
use Omise\Api\Refund;
use Omise\Api\Schedule;
use Omise\Api\Search;
use Omise\Api\Source;
use Omise\Api\Token;
use Omise\Api\Transaction;
use Omise\Api\Transfer;
use Omise\Config;
use Omise\Http\HttpClient;
use Omise\Http\Response;
use Omise\PaymentMethods\CreditCard;
use Omise\PaymentMethods\DirectDebit;
use Omise\PaymentMethods\MobileBanking;
use Omise\PaymentMethods\PromptPay;
use Omise\PaymentMethods\RabbitLinePay;
use Omise\PaymentMethods\ShopeepayJumpApp;
use Omise\PaymentMethods\ShopeepayQR;
use Omise\PaymentMethods\TruemoneyJumpApp;
use Omise\PaymentMethods\TruemoneyQR;
use Omise\Webhook\WebhookHandler;

/**
 * Facade for the Omise SDK.
 *
 * Core APIs
 * @method static Account account()
 * @method static Balance balance()
 * @method static Capability capability()
 * @method static Card cards()
 * @method static Chain chains()
 * @method static Charge charges()
 * @method static Customer customers()
 * @method static Dispute disputes()
 * @method static Document documents()
 * @method static Event events()
 * @method static Forex forex()
 * @method static Link links()
 * @method static LinkedAccount linkedAccounts()
 * @method static Occurrence occurrences()
 * @method static Receipt receipts()
 * @method static Recipient recipients()
 * @method static Refund refunds()
 * @method static Schedule schedules()
 * @method static Search search()
 * @method static Source sources()
 * @method static Token tokens()
 * @method static Transaction transactions()
 * @method static Transfer transfers()
 *
 * Payment Methods
 * @method static PromptPay promptPay()
 * @method static RabbitLinePay rabbitLinePay()
 * @method static CreditCard creditCard()
 * @method static DirectDebit directDebit()
 * @method static TruemoneyQR truemoneyQR()
 * @method static TruemoneyJumpApp truemoneyJumpApp()
 * @method static MobileBanking mobileBanking()
 * @method static ShopeepayQR shopeepayQR()
 * @method static ShopeepayJumpApp shopeepayJumpApp()
 *
 * Webhooks
 * @method static WebhookHandler webhooks()
 *
 * Convenience Methods
 * @method static Response payWithPromptPay(float $amount, array $webhookEndpoints = [])
 * @method static Response payWithRabbitLinePay(float $amount, string $returnUri, array $webhookEndpoints = [])
 * @method static Response payWithCard(string $tokenId, float $amount, string $currency = 'THB', ?string $returnUri = null)
 * @method static Response payWithTruemoneyQR(float $amount, array $webhookEndpoints = [])
 * @method static Response payWithTruemoneyJumpApp(float $amount, string $returnUri, array $webhookEndpoints = [])
 * @method static Response payWithMobileBanking(string $bankType, float $amount, string $returnUri, array $options = [])
 * @method static Response payWithShopeepayQR(float $amount, string $currency, string $returnUri, array $webhookEndpoints = [])
 * @method static Response payWithShopeepayJumpApp(float $amount, string $currency, string $returnUri, array $options = [])
 * @method static Response getCharge(string $chargeId)
 * @method static Response getEvent(string $eventId)
 * @method static Response getCustomer(string $customerId)
 * @method static string getDefaultCurrency()
 * @method static Config getConfig()
 * @method static bool isTestMode()
 * @method static bool isLiveMode()
 * @method static HttpClient getHttpClient()
 *
 * @see \Omise\Omise
 */
class Omise extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Omise\Omise::class;
    }
}
