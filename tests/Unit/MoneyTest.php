<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_formats_cents_to_two_decimal_places_without_float(): void
    {
        $this->assertSame('0.00', Money::format(0));
        $this->assertSame('0.05', Money::format(5));
        $this->assertSame('0.99', Money::format(99));
        $this->assertSame('1.00', Money::format(100));
        $this->assertSame('10.50', Money::format(1050));
        $this->assertSame('125.00', Money::format(12500));
        $this->assertSame('-10.50', Money::format(-1050));
    }
}
