<?php

declare(strict_types=1);

namespace Omise\Tests\Unit;

use Omise\Exceptions\WebhookException;
use Omise\Webhook\SignatureVerifier;
use PHPUnit\Framework\TestCase;

class SignatureVerifierTest extends TestCase
{
    public function test_throws_exception_for_missing_secret(): void
    {
        $this->expectException(WebhookException::class);
        $this->expectExceptionMessage('secret');

        $verifier = new SignatureVerifier('');
        $verifier->verify('payload', 'signature', (string) time());
    }

    public function test_throws_exception_for_missing_signature(): void
    {
        $this->expectException(WebhookException::class);
        $this->expectExceptionMessage('Omise-Signature');

        $verifier = new SignatureVerifier('secret123');
        $verifier->verify('payload', '', (string) time());
    }

    public function test_throws_exception_for_missing_timestamp(): void
    {
        $this->expectException(WebhookException::class);
        $this->expectExceptionMessage('Timestamp');

        $verifier = new SignatureVerifier('secret123');
        $verifier->verify('payload', 'signature', '');
    }

    public function test_throws_exception_for_expired_timestamp(): void
    {
        $this->expectException(WebhookException::class);
        $this->expectExceptionMessage('tolerance');

        $verifier = new SignatureVerifier('secret123', 60);
        $oldTimestamp = (string) (time() - 120); // 2 minutes ago

        $verifier->verify('payload', 'invalid_signature', $oldTimestamp);
    }

    public function test_throws_exception_for_invalid_signature(): void
    {
        $this->expectException(WebhookException::class);
        $this->expectExceptionMessage('verification failed');

        $verifier = new SignatureVerifier('secret123');
        $verifier->verify('payload', 'invalid_signature', (string) time());
    }

    public function test_verifies_valid_signature(): void
    {
        $secret = 'test_secret';
        $payload = '{"test": "data"}';
        $timestamp = (string) time();

        // Create a valid signature
        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        $verifier = new SignatureVerifier($secret);
        $result = $verifier->verify($payload, $expectedSignature, $timestamp);

        $this->assertTrue($result);
    }

    public function test_handles_multiple_signatures_during_rotation(): void
    {
        $secret = 'test_secret';
        $payload = '{"test": "data"}';
        $timestamp = (string) time();

        $signedPayload = "{$timestamp}.{$payload}";
        $validSignature = hash_hmac('sha256', $signedPayload, $secret);

        // Multiple signatures (comma-separated, as during key rotation)
        $signatures = "invalid_signature_1,{$validSignature},invalid_signature_2";

        $verifier = new SignatureVerifier($secret);
        $result = $verifier->verify($payload, $signatures, $timestamp);

        $this->assertTrue($result);
    }

    public function test_set_tolerance(): void
    {
        $verifier = new SignatureVerifier('secret', 300);
        $this->assertEquals(300, $verifier->getTolerance());

        $verifier->setTolerance(600);
        $this->assertEquals(600, $verifier->getTolerance());
    }
}
