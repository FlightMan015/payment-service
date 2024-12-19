<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\MoneyHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class MoneyHelperTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('conversionDataProvider')]
    public function it_converts_amount_to_cents_correctly(float $amount, int $expectedCents): void
    {
        $result = MoneyHelper::convertToCents($amount);

        $this->assertSame($expectedCents, $result);
    }

    public static function conversionDataProvider(): iterable
    {
        yield 'positive amount' => [10.5, 1050];
        yield 'negative amount' => [-7.25, -725];
        yield 'zero amount' => [0.0, 0];
        yield 'large amount' => [123456.78, 12345678];
        yield 'amount' => [152.64, 15264];
        yield 'amount with bad precision should be correctly converted and rounded up' => [152.63999999999999, 15264];
        yield 'amount with bad precision should be correctly converted and rounded down' => [152.63000000000001, 15263];
    }

    #[Test]
    #[DataProvider('decimalConversionDataProvider')]
    public function it_converts_cents_to_decimal_correctly(int $amount, float $expectedDecimal): void
    {
        $result = MoneyHelper::convertToDecimal($amount);

        $this->assertSame($expectedDecimal, $result);
    }

    public static function decimalConversionDataProvider(): iterable
    {
        yield 'positive amount' => [1050, 10.5];
        yield 'negative amount' => [-725, -7.25];
        yield 'zero amount' => [0, 0.0];
        yield 'large amount' => [12345678, 123456.78];
        yield 'amount' => [15264, 152.64];
    }
}
