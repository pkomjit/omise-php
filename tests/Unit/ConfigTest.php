<?php

declare(strict_types=1);

namespace Omise\Tests\Unit;

use Omise\Config;
use Omise\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function test_creates_config_with_valid_keys(): void
    {
        $config = new Config([
            'public_key' => 'pkey_test_123',
            'secret_key' => 'skey_test_456',
        ]);

        $this->assertEquals('pkey_test_123', $config->getPublicKey());
        $this->assertEquals('skey_test_456', $config->getSecretKey());
    }

    public function test_throws_exception_for_missing_public_key(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('public_key');

        new Config([
            'secret_key' => 'skey_test_456',
        ]);
    }

    public function test_throws_exception_for_missing_secret_key(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('secret_key');

        new Config([
            'public_key' => 'pkey_test_123',
        ]);
    }

    public function test_throws_exception_for_invalid_public_key_format(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('public');

        new Config([
            'public_key' => 'invalid_key',
            'secret_key' => 'skey_test_456',
        ]);
    }

    public function test_throws_exception_for_invalid_secret_key_format(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('secret');

        new Config([
            'public_key' => 'pkey_test_123',
            'secret_key' => 'invalid_key',
        ]);
    }

    public function test_detects_test_mode_from_keys(): void
    {
        $config = new Config([
            'public_key' => 'pkey_test_123',
            'secret_key' => 'skey_test_456',
        ]);

        $this->assertTrue($config->isTestMode());
        $this->assertFalse($config->isLiveMode());
    }

    public function test_detects_live_mode_from_keys(): void
    {
        $config = new Config([
            'public_key' => 'pkey_live_123',
            'secret_key' => 'skey_live_456',
        ]);

        $this->assertTrue($config->isLiveMode());
        $this->assertFalse($config->isTestMode());
    }

    public function test_uses_default_values(): void
    {
        $config = new Config([
            'public_key' => 'pkey_test_123',
            'secret_key' => 'skey_test_456',
        ]);

        $this->assertEquals(Config::API_URL_LIVE, $config->getApiUrl());
        $this->assertEquals(Config::API_VERSION, $config->getApiVersion());
        $this->assertEquals(30, $config->getTimeout());
        $this->assertTrue($config->shouldVerifySsl());
    }

    public function test_accepts_custom_values(): void
    {
        $config = new Config([
            'public_key' => 'pkey_test_123',
            'secret_key' => 'skey_test_456',
            'timeout' => 60,
            'ssl_verify' => false,
            'webhook_secret' => 'whsec_123',
        ]);

        $this->assertEquals(60, $config->getTimeout());
        $this->assertFalse($config->shouldVerifySsl());
        $this->assertEquals('whsec_123', $config->getWebhookSecret());
    }

    public function test_to_array_masks_secret_key(): void
    {
        $config = new Config([
            'public_key' => 'pkey_test_123',
            'secret_key' => 'skey_test_456789012345',
        ]);

        $array = $config->toArray();

        $this->assertStringContainsString('*', $array['secret_key']);
        $this->assertStringStartsWith('skey_tes', $array['secret_key']);
    }
}
