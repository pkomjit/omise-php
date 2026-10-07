<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Omise\Exceptions\WebhookException;
use Omise\Http\Response;
use Omise\Omise;
use Omise\PaymentMethods\AbstractPaymentMethod;

function securityTestOmise(array $overrides = []): Omise
{
    return new Omise(array_merge([
        'public_key' => 'pkey_test_123',
        'secret_key' => 'skey_test_456',
        'mode' => 'test',
    ], $overrides));
}

function qrCharge(string $downloadUri): Response
{
    return new Response([
        'object' => 'charge',
        'source' => [
            'scannable_code' => [
                'image' => ['download_uri' => $downloadUri],
            ],
        ],
    ]);
}

describe('Security', function () {
    describe('resource ID validation', function () {
        it('rejects IDs that would change the request path', function (string $id) {
            securityTestOmise()->charges()->retrieve($id);
        })->with([
            'path traversal' => ['../customers/cust_test_1'],
            'query string' => ['chrg_test_1?expand=true'],
            'fragment' => ['chrg_test_1#x'],
            'encoded slash' => ['chrg_test_1%2F..'],
            'empty' => [''],
        ])->throws(InvalidArgumentException::class);

        it('rejects unsafe IDs in nested endpoints', function () {
            securityTestOmise()->refunds()->retrieveForCharge('chrg_test_1', '../../customers');
        })->throws(InvalidArgumentException::class);

        it('sends valid requests with the Omise-Version header', function () {
            $history = [];
            $stack = HandlerStack::create(new MockHandler([
                new GuzzleResponse(200, [], json_encode(['object' => 'charge', 'id' => 'chrg_test_1'])),
            ]));
            $stack->push(Middleware::history($history));

            $omise = securityTestOmise(['api_version' => '2019-05-29']);
            $omise->getHttpClient()->setGuzzleClient(new Client(['handler' => $stack]));

            $omise->charges()->retrieve('chrg_test_1');

            $request = $history[0]['request'];
            expect($request->getUri()->getPath())->toBe('/charges/chrg_test_1')
                ->and($request->getHeaderLine('Omise-Version'))->toBe('2019-05-29');
        });
    });

    describe('webhook verification', function () {
        it('rejects webhooks when no webhook secret is configured', function () {
            $payload = json_encode(['key' => 'charge.complete', 'data' => ['status' => 'successful']]);

            securityTestOmise()->webhooks()->handle($payload, 'forged', (string) time());
        })->throws(WebhookException::class, 'secret');

        it('always configures a verifier', function () {
            expect(securityTestOmise()->webhooks()->hasVerifier())->toBeTrue();
        });
    });

    describe('QR code download', function () {
        it('only trusts https URLs on omise.co', function (string $url, bool $trusted) {
            expect(AbstractPaymentMethod::isOmiseDownloadUrl($url))->toBe($trusted);
        })->with([
            ['https://api.omise.co/charges/chrg_1/documents/docu_1/downloads/ABC', true],
            ['https://omise.co/qr.svg', true],
            ['http://api.omise.co/qr.svg', false],
            ['file:///etc/passwd', false],
            ['php://filter/resource=/etc/passwd', false],
            ['https://api.omise.co.evil.test/qr.svg', false],
            ['https://evilomise.co/qr.svg', false],
            ['https://api.omise.co@evil.test/qr.svg', false],
            ['https://169.254.169.254/latest/meta-data', false],
            ['/etc/passwd', false],
        ]);

        it('does not read local files from a crafted PromptPay charge', function () {
            expect(securityTestOmise()->promptPay()->getQrCodeContent(qrCharge('file:///etc/passwd')))->toBeNull();
        });

        it('does not read local files from a crafted TrueMoney QR charge', function () {
            $truemoneyQR = securityTestOmise()->truemoneyQR();
            $charge = qrCharge('file:///etc/passwd');

            expect($truemoneyQR->getQrCodeBase64($charge))->toBeNull()
                ->and($truemoneyQR->getQrCodeDataUri($charge))->toBeNull();
        });
    });
});
