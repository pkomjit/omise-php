<?php

declare(strict_types=1);

namespace Omise\Laravel;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Omise\Api\Charge;
use Omise\Api\Event;
use Omise\Api\Source;
use Omise\Config;
use Omise\Http\HttpClient;
use Omise\Omise;
use Omise\PaymentMethods\PromptPay;
use Omise\Webhook\WebhookHandler;

/**
 * Laravel service provider for Omise SDK.
 */
class OmiseServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/omise.php',
            'omise'
        );

        // Register Config
        $this->app->singleton(Config::class, function ($app) {
            return new Config([
                'public_key' => config('omise.public_key'),
                'secret_key' => config('omise.secret_key'),
                'api_url' => config('omise.api_url', Config::API_URL_LIVE),
                'api_version' => config('omise.api_version', Config::API_VERSION),
                'mode' => config('omise.mode', Config::MODE_LIVE),
                'webhook_secret' => config('omise.webhook_secret'),
                'timeout' => config('omise.timeout', 30),
                'ssl_verify' => config('omise.ssl_verify', true),
                'default_currency' => config('omise.default_currency', 'THB'),
            ]);
        });

        // Register HttpClient
        $this->app->singleton(HttpClient::class, function ($app) {
            $logger = $app->bound('log') ? $app->make('log') : null;

            return new HttpClient($app->make(Config::class), $logger);
        });

        // Register main Omise class
        $this->app->singleton(Omise::class, function ($app) {
            $logger = $app->bound('log') ? $app->make('log') : null;

            return new Omise([
                'public_key' => config('omise.public_key'),
                'secret_key' => config('omise.secret_key'),
                'api_url' => config('omise.api_url', Config::API_URL_LIVE),
                'api_version' => config('omise.api_version', Config::API_VERSION),
                'mode' => config('omise.mode', Config::MODE_LIVE),
                'webhook_secret' => config('omise.webhook_secret'),
                'timeout' => config('omise.timeout', 30),
                'ssl_verify' => config('omise.ssl_verify', true),
                'default_currency' => config('omise.default_currency', 'THB'),
            ], $logger);
        });

        // Register alias
        $this->app->alias(Omise::class, 'omise');

        // Register API classes
        $this->app->singleton(Charge::class, function ($app) {
            return $app->make(Omise::class)->charges();
        });

        $this->app->singleton(Source::class, function ($app) {
            return $app->make(Omise::class)->sources();
        });

        $this->app->singleton(Event::class, function ($app) {
            return $app->make(Omise::class)->events();
        });

        // Register Payment Methods
        $this->app->singleton(PromptPay::class, function ($app) {
            return $app->make(Omise::class)->promptPay();
        });

        // Register Webhook Handler
        $this->app->singleton(WebhookHandler::class, function ($app) {
            return $app->make(Omise::class)->webhooks();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/omise.php' => config_path('omise.php'),
            ], 'omise-config');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            Omise::class,
            'omise',
            Config::class,
            HttpClient::class,
            Charge::class,
            Source::class,
            Event::class,
            PromptPay::class,
            WebhookHandler::class,
        ];
    }
}
