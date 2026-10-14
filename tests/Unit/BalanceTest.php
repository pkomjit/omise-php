<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Omise\Api\Balance;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;
use Omise\Omise;

/**
 * An Omise instance whose HTTP calls are answered by $responses in order.
 * Sent requests are appended to $history.
 */
function createBalanceOmise(array $responses, array &$history = []): Omise
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    $omise = new Omise(createTestConfig());
    $omise->getHttpClient()->setGuzzleClient(new Client([
        'base_uri' => 'https://api.omise.co',
        'http_errors' => false,
        'handler' => $stack,
    ]));

    return $omise;
}

function balanceJson(array $overrides = []): Psr7Response
{
    return new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode(array_merge([
        'object' => 'balance',
        'livemode' => false,
        'location' => '/balance',
        'currency' => 'thb',
        'total' => 150000,
        'transferable' => 100000,
        'reserve' => 30000,
        'on_hold' => 20000,
        'created_at' => '2019-12-31T12:59:59Z',
    ], $overrides)));
}

function sampleBalance(array $overrides = []): Response
{
    return new Response(json_decode((string) balanceJson($overrides)->getBody(), true));
}

describe('Balance', function () {
    describe('retrieving', function () {
        it('sends GET /balance with the secret key', function () {
            $history = [];
            $omise = createBalanceOmise([balanceJson()], $history);

            $balance = $omise->balance()->get();

            expect($balance->getObject())->toBe('balance')
                ->and($history)->toHaveCount(1);

            $request = $history[0]['request'];
            expect($request->getMethod())->toBe('GET')
                ->and($request->getUri()->getPath())->toBe('/balance')
                ->and($request->getHeaderLine('Authorization'))->toBe('Basic ' . base64_encode('skey_test_987654321:'));
        });

        it('is available through the getBalance() shortcut', function () {
            $history = [];
            $omise = createBalanceOmise([balanceJson(['total' => 42])], $history);

            expect($omise->getBalance()->get('total'))->toBe(42)
                ->and($history[0]['request']->getUri()->getPath())->toBe('/balance');
        });

        it('throws ApiException when Omise returns an error', function () {
            $omise = createBalanceOmise([
                new Psr7Response(401, [], json_encode([
                    'object' => 'error',
                    'code' => 'authentication_failure',
                    'message' => 'authentication failed',
                ])),
            ]);

            $omise->balance()->get();
        })->throws(ApiException::class);
    });

    describe('reading fields', function () {
        it('reads the documented amounts in the smallest currency unit', function () {
            $api = createBalanceOmise([])->balance();
            $balance = sampleBalance();

            expect($api->isBalance($balance))->toBeTrue()
                ->and($api->getTotal($balance))->toBe(150000)
                ->and($api->getTransferable($balance))->toBe(100000)
                ->and($api->getReserve($balance))->toBe(30000)
                ->and($api->getOnHold($balance))->toBe(20000);
        });

        it('upper-cases the currency and reads livemode and location', function () {
            $api = createBalanceOmise([])->balance();

            expect($api->getCurrency(sampleBalance()))->toBe('THB')
                ->and($api->isLiveMode(sampleBalance()))->toBeFalse()
                ->and($api->isLiveMode(sampleBalance(['livemode' => true])))->toBeTrue()
                ->and($api->getLocation(sampleBalance()))->toBe('/balance');
        });

        it('parses created_at and at as dates', function () {
            $api = createBalanceOmise([])->balance();
            $balance = sampleBalance(['at' => '2020-01-01T05:00:00Z']);

            expect($api->getCreatedAt($balance)?->format(DATE_ATOM))->toBe('2019-12-31T12:59:59+00:00')
                ->and($api->getAt($balance)?->format(DATE_ATOM))->toBe('2020-01-01T05:00:00+00:00');
        });

        it('returns safe defaults for missing or malformed fields', function () {
            $api = createBalanceOmise([])->balance();
            $balance = new Response(['object' => 'balance', 'created_at' => 'not a date']);

            expect($api->getTotal($balance))->toBe(0)
                ->and($api->getOnHold($balance))->toBe(0)
                ->and($api->getCurrency($balance))->toBe('THB')
                ->and($api->getAt($balance))->toBeNull()
                ->and($api->getCreatedAt($balance))->toBeNull()
                ->and($api->isBalance(new Response(['object' => 'charge'])))->toBeFalse();
        });

        it('summarises the documented fields', function () {
            $api = createBalanceOmise([])->balance();

            expect($api->summary(sampleBalance()))->toBe([
                'currency' => 'THB',
                'total' => 150000,
                'transferable' => 100000,
                'reserve' => 30000,
                'on_hold' => 20000,
                'livemode' => false,
            ]);
        });
    });

    describe('canTransfer', function () {
        it('compares against the transferable amount, not the total', function () {
            $api = createBalanceOmise([])->balance();
            $balance = sampleBalance();

            expect($api->canTransfer($balance, 100000))->toBeTrue()
                ->and($api->canTransfer($balance, 100001))->toBeFalse();
        });
    });

    it('identifies balance objects with a constant', function () {
        expect(Balance::OBJECT)->toBe('balance');
    });
});
