<?php

use Omise\Config;
use Omise\Exceptions\ConfigurationException;

describe('Config', function () {
    it('creates config with valid keys', function () {
        $config = new Config([
            'public_key' => 'pkey_test_123',
            'secret_key' => 'skey_test_456',
        ]);

        expect($config->getPublicKey())->toBe('pkey_test_123')
            ->and($config->getSecretKey())->toBe('skey_test_456');
    });

    it('throws exception for missing public key', function () {
        new Config([
            'secret_key' => 'skey_test_456',
        ]);
    })->throws(ConfigurationException::class, 'public_key');

    it('throws exception for missing secret key', function () {
        new Config([
            'public_key' => 'pkey_test_123',
        ]);
    })->throws(ConfigurationException::class, 'secret_key');

    it('throws exception for invalid public key format', function () {
        new Config([
            'public_key' => 'invalid_key',
            'secret_key' => 'skey_test_456',
        ]);
    })->throws(ConfigurationException::class, 'public');

    it('throws exception for invalid secret key format', function () {
        new Config([
            'public_key' => 'pkey_test_123',
            'secret_key' => 'invalid_key',
        ]);
    })->throws(ConfigurationException::class, 'secret');

    it('detects test mode from keys', function () {
        $config = new Config([
            'public_key' => 'pkey_test_123',
            'secret_key' => 'skey_test_456',
        ]);

        expect($config->isTestMode())->toBeTrue()
            ->and($config->isLiveMode())->toBeFalse();
    });

    it('detects live mode from keys', function () {
        $config = new Config([
            'public_key' => 'pkey_live_123',
            'secret_key' => 'skey_live_456',
        ]);

        expect($config->isLiveMode())->toBeTrue()
            ->and($config->isTestMode())->toBeFalse();
    });

    it('uses default values', function () {
        $config = new Config([
            'public_key' => 'pkey_test_123',
            'secret_key' => 'skey_test_456',
        ]);

        expect($config->getApiUrl())->toBe(Config::API_URL_LIVE)
            ->and($config->getApiVersion())->toBe(Config::API_VERSION)
            ->and($config->getTimeout())->toBe(30)
            ->and($config->shouldVerifySsl())->toBeTrue();
    });

    it('accepts custom values', function () {
        $config = new Config([
            'public_key' => 'pkey_test_123',
            'secret_key' => 'skey_test_456',
            'timeout' => 60,
            'ssl_verify' => false,
            'webhook_secret' => 'whsec_123',
        ]);

        expect($config->getTimeout())->toBe(60)
            ->and($config->shouldVerifySsl())->toBeFalse()
            ->and($config->getWebhookSecret())->toBe('whsec_123');
    });

    it('masks secret key in toArray output', function () {
        $config = new Config([
            'public_key' => 'pkey_test_123',
            'secret_key' => 'skey_test_456789012345',
        ]);

        $array = $config->toArray();

        expect($array['secret_key'])->toContain('*')
            ->and($array['secret_key'])->toStartWith('skey_tes');
    });
});
