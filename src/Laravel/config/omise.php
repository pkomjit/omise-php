<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Omise API Keys
    |--------------------------------------------------------------------------
    |
    | Your Omise API keys. You can find these in your Omise dashboard.
    | Use test keys (pkey_test_*, skey_test_*) for development.
    |
    */

    'public_key' => env('OMISE_PUBLIC_KEY', ''),
    'secret_key' => env('OMISE_SECRET_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | API URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the Omise API. You should not need to change this
    | unless you are using a proxy or custom endpoint.
    |
    */

    'api_url' => env('OMISE_API_URL', 'https://api.omise.co'),

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | The Omise API version to use. See the Omise documentation for
    | available versions and their differences.
    |
    */

    'api_version' => env('OMISE_API_VERSION', '2019-05-29'),

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | The mode to run in: 'live' or 'test'. This is automatically detected
    | based on your API keys, but you can override it here.
    |
    */

    'mode' => env('OMISE_MODE', 'live'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Your webhook signing secret. This is used to verify that webhook
    | requests are actually from Omise. Find this in your Omise dashboard.
    |
    */

    'webhook_secret' => env('OMISE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout for API requests in seconds.
    |
    */

    'timeout' => env('OMISE_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    |
    | Whether to verify SSL certificates. Set to false for local development
    | with self-signed certificates (not recommended for production).
    |
    */

    'ssl_verify' => env('OMISE_SSL_VERIFY', true),

    /*
    |--------------------------------------------------------------------------
    | Default Webhook Endpoints
    |--------------------------------------------------------------------------
    |
    | Default webhook endpoints to include with charges. Leave empty to use
    | the webhooks configured in your Omise dashboard.
    |
    */

    'webhook_endpoints' => [
        // env('APP_URL') . '/api/webhooks/omise',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency to use for charges. Supports multi-currency if
    | enabled on your Omise account (THB, USD, EUR, GBP, JPY, SGD, etc.)
    |
    */

    'default_currency' => env('OMISE_DEFAULT_CURRENCY', 'THB'),

    /*
    |--------------------------------------------------------------------------
    | PromptPay Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for PromptPay payments.
    |
    */

    'promptpay' => [
        // Default expiration time in hours (max 24)
        'expiration_hours' => env('OMISE_PROMPTPAY_EXPIRATION', 24),
    ],
];
