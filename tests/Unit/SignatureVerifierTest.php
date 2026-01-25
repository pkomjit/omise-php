<?php

use Omise\Exceptions\WebhookException;
use Omise\Webhook\SignatureVerifier;

describe('SignatureVerifier', function () {
    describe('validation errors', function () {
        it('throws exception for missing secret', function () {
            $verifier = new SignatureVerifier('');
            $verifier->verify('payload', 'signature', (string) time());
        })->throws(WebhookException::class, 'secret');

        it('throws exception for missing signature', function () {
            $verifier = new SignatureVerifier('secret123');
            $verifier->verify('payload', '', (string) time());
        })->throws(WebhookException::class, 'Omise-Signature');

        it('throws exception for missing timestamp', function () {
            $verifier = new SignatureVerifier('secret123');
            $verifier->verify('payload', 'signature', '');
        })->throws(WebhookException::class, 'Timestamp');

        it('throws exception for expired timestamp', function () {
            $verifier = new SignatureVerifier('secret123', 60);
            $oldTimestamp = (string) (time() - 120); // 2 minutes ago

            $verifier->verify('payload', 'invalid_signature', $oldTimestamp);
        })->throws(WebhookException::class, 'tolerance');

        it('throws exception for invalid signature', function () {
            $verifier = new SignatureVerifier('secret123');
            $verifier->verify('payload', 'invalid_signature', (string) time());
        })->throws(WebhookException::class, 'verification failed');
    });

    describe('signature verification', function () {
        it('verifies valid signature', function () {
            $secret = 'test_secret';
            $payload = '{"test": "data"}';
            $timestamp = (string) time();

            // Create a valid signature
            $signedPayload = "{$timestamp}.{$payload}";
            $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

            $verifier = new SignatureVerifier($secret);
            $result = $verifier->verify($payload, $expectedSignature, $timestamp);

            expect($result)->toBeTrue();
        });

        it('handles multiple signatures during rotation', function () {
            $secret = 'test_secret';
            $payload = '{"test": "data"}';
            $timestamp = (string) time();

            $signedPayload = "{$timestamp}.{$payload}";
            $validSignature = hash_hmac('sha256', $signedPayload, $secret);

            // Multiple signatures (comma-separated, as during key rotation)
            $signatures = "invalid_signature_1,{$validSignature},invalid_signature_2";

            $verifier = new SignatureVerifier($secret);
            $result = $verifier->verify($payload, $signatures, $timestamp);

            expect($result)->toBeTrue();
        });
    });

    describe('configuration', function () {
        it('gets and sets tolerance', function () {
            $verifier = new SignatureVerifier('secret', 300);
            expect($verifier->getTolerance())->toBe(300);

            $verifier->setTolerance(600);
            expect($verifier->getTolerance())->toBe(600);
        });
    });
});
