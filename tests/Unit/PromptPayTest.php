<?php

declare(strict_types=1);

namespace Omise\Tests\Unit;

use Omise\PaymentMethods\PromptPay;
use PHPUnit\Framework\TestCase;

class PromptPayTest extends TestCase
{
    public function test_converts_thb_to_satang(): void
    {
        $this->assertEquals(10000, PromptPay::toSatang(100.00));
        $this->assertEquals(2000, PromptPay::toSatang(20.00));
        $this->assertEquals(15000000, PromptPay::toSatang(150000.00));
        $this->assertEquals(9999, PromptPay::toSatang(99.99));
    }

    public function test_converts_satang_to_thb(): void
    {
        $this->assertEquals(100.00, PromptPay::toThb(10000));
        $this->assertEquals(20.00, PromptPay::toThb(2000));
        $this->assertEquals(150000.00, PromptPay::toThb(15000000));
        $this->assertEquals(99.99, PromptPay::toThb(9999));
    }
}
