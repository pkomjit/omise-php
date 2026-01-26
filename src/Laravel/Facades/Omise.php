<?php

declare(strict_types=1);

namespace Omise\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Omise\Api\Charge;
use Omise\Api\Customer;
use Omise\Api\Event;
use Omise\Api\LinkedAccount;
use Omise\Api\Source;
use Omise\Api\Token;
use Omise\Config;
use Omise\Http\HttpClient;
use Omise\Http\Response;
use Omise\PaymentMethods\CreditCard;
use Omise\PaymentMethods\DirectDebit;
use Omise\PaymentMethods\PromptPay;
use Omise\PaymentMethods\RabbitLinePay;
use Omise\Webhook\WebhookHandler;

/**
 * Facade for the Omise SDK.
 *
 * @method static Charge charges()
 * @method static Source sources()
 * @method static Event events()
 * @method static Token tokens()
 * @method static Customer customers()
 * @method static LinkedAccount linkedAccounts()
 * @method static PromptPay promptPay()
 * @method static RabbitLinePay rabbitLinePay()
 * @method static CreditCard creditCard()
 * @method static DirectDebit directDebit()
 * @method static WebhookHandler webhooks()
 * @method static Response payWithPromptPay(float $amount, array $webhookEndpoints = [])
 * @method static Response payWithRabbitLinePay(float $amount, string $returnUri, array $webhookEndpoints = [])
 * @method static Response payWithCard(string $tokenId, float $amount, string $currency = 'THB', ?string $returnUri = null)
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
