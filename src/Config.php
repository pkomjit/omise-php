<?php

declare(strict_types=1);

namespace Omise;

use Omise\Exceptions\ConfigurationException;

/**
 * Configuration management for Omise SDK.
 */
class Config
{
    public const string API_VERSION = '2019-05-29';
    public const string API_URL_LIVE = 'https://api.omise.co';
    public const string API_URL_VAULT = 'https://vault.omise.co';

    public const string MODE_LIVE = 'live';
    public const string MODE_TEST = 'test';

    private string $publicKey;
    private string $secretKey;
    private string $apiUrl;
    private string $apiVersion;
    private string $mode;
    private ?string $webhookSecret;
    private int $timeout;
    private bool $sslVerify;
    private string $defaultCurrency;

    /**
     * Default configuration values.
     */
    private static array $defaults = [
        'api_url' => self::API_URL_LIVE,
        'api_version' => self::API_VERSION,
        'mode' => self::MODE_LIVE,
        'webhook_secret' => null,
        'timeout' => 30,
        'ssl_verify' => true,
        'default_currency' => 'THB',
    ];

    /**
     * @throws ConfigurationException
     */
    public function __construct(array $config = [])
    {
        $config = array_merge(self::$defaults, $config);

        $this->validateConfig($config);

        $this->publicKey = $config['public_key'];
        $this->secretKey = $config['secret_key'];
        $this->apiUrl = $config['api_url'];
        $this->apiVersion = $config['api_version'];
        $this->mode = $config['mode'];
        $this->webhookSecret = $config['webhook_secret'];
        $this->timeout = (int) $config['timeout'];
        $this->sslVerify = (bool) $config['ssl_verify'];
        $this->defaultCurrency = strtoupper($config['default_currency']);
    }

    /**
     * Validate configuration values.
     *
     * @throws ConfigurationException
     */
    private function validateConfig(array $config): void
    {
        if (empty($config['public_key'])) {
            throw ConfigurationException::missingKey('public_key');
        }

        if (empty($config['secret_key'])) {
            throw ConfigurationException::missingKey('secret_key');
        }

        // Validate public key format
        if (! str_starts_with($config['public_key'], 'pkey_')) {
            throw ConfigurationException::invalidApiKey('public');
        }

        // Validate secret key format
        if (! str_starts_with($config['secret_key'], 'skey_')) {
            throw ConfigurationException::invalidApiKey('secret');
        }

        // Validate mode
        if (! in_array($config['mode'], [self::MODE_LIVE, self::MODE_TEST], true)) {
            throw ConfigurationException::invalidValue('mode', 'Must be "live" or "test"');
        }
    }

    /**
     * Check if running in test mode.
     */
    public function isTestMode(): bool
    {
        return $this->mode === self::MODE_TEST
            || str_contains($this->publicKey, '_test_')
            || str_contains($this->secretKey, '_test_');
    }

    /**
     * Check if running in live mode.
     */
    public function isLiveMode(): bool
    {
        return ! $this->isTestMode();
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getVaultUrl(): string
    {
        return self::API_URL_VAULT;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getWebhookSecret(): ?string
    {
        return $this->webhookSecret;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function shouldVerifySsl(): bool
    {
        return $this->sslVerify;
    }

    public function getDefaultCurrency(): string
    {
        return $this->defaultCurrency;
    }

    /**
     * Create configuration from environment variables.
     * @throws ConfigurationException
     */
    public static function fromEnvironment(): self
    {
        return new self([
            'public_key' => getenv('OMISE_PUBLIC_KEY') ?: '',
            'secret_key' => getenv('OMISE_SECRET_KEY') ?: '',
            'webhook_secret' => getenv('OMISE_WEBHOOK_SECRET') ?: null,
            'mode' => getenv('OMISE_MODE') ?: self::MODE_LIVE,
            'default_currency' => getenv('OMISE_DEFAULT_CURRENCY') ?: 'THB',
        ]);
    }

    /**
     * Convert configuration to array.
     */
    public function toArray(): array
    {
        return [
            'public_key' => $this->publicKey,
            'secret_key' => $this->maskKey($this->secretKey),
            'api_url' => $this->apiUrl,
            'api_version' => $this->apiVersion,
            'mode' => $this->mode,
            'timeout' => $this->timeout,
            'ssl_verify' => $this->sslVerify,
            'default_currency' => $this->defaultCurrency,
        ];
    }

    /**
     * Mask sensitive key for logging/display.
     */
    private function maskKey(string $key): string
    {
        if (strlen($key) <= 12) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 8) . str_repeat('*', strlen($key) - 12) . substr($key, -4);
    }
}
