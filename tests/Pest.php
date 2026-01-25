<?php

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain
| conditions. The "expect()" function gives you access to a set of "expectations"
| methods that you can use to assert different things. Of course, you may
| extend the Expectation API at any time.
|
*/

expect()->extend('toBeSuccessful', function () {
    return $this->toBeInstanceOf(\Omise\Http\Response::class)
        ->and($this->value->isSuccessful())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code
| specific to your project that you don't want to repeat in every file. Here
| you can also expose helpers as global functions to help you reduce the
| number of lines of code in your test files.
|
*/

function createTestConfig(array $overrides = []): array
{
    return array_merge([
        'public_key' => 'pkey_test_123456789',
        'secret_key' => 'skey_test_987654321',
    ], $overrides);
}
