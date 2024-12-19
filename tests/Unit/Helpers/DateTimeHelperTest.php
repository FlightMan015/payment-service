<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\DateTimeHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class DateTimeHelperTest extends UnitTestCase
{
    #[Test]
    public function format_date_time_return_with_format_yyyy_mm_dd_hh_ii_ss(): void
    {
        $value = '2023-02-15 12:01:24';

        $actual = DateTimeHelper::formatDateTime(value: $value);

        $this->assertEquals($value, $actual);
    }

    #[Test]
    #[DataProvider('convertFormatProvider')]
    public function convert_to_format_return_with_given_format(string $value, string $format, string $expected): void
    {
        $actual = DateTimeHelper::convertToFormat(value: $value, format: $format);

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    #[DataProvider('dateSameDayDataProvider')]
    public function is_two_dates_same_day(\DateTimeInterface|null $date1, \DateTimeInterface|null $date2, bool $expectedResult): void
    {
        $result = DateTimeHelper::isTwoDatesAreSameDay($date1, $date2);

        $this->assertSame($expectedResult, $result);
    }

    public static function convertFormatProvider(): \Iterator
    {
        yield 'only time' => [
            'value' => '2023-02-15 12:01:24',
            'format' => 'H:i:s',
            'expected' => '12:01:24',
        ];
        yield 'date time' => [
            'value' => '2023-02-15 12:01:24',
            'format' => 'H:i:s d/m/Y',
            'expected' => '12:01:24 15/02/2023',
        ];
        yield 'date' => [
            'value' => '2023-02-15 12:01:24',
            'format' => 'm/d/Y',
            'expected' => '02/15/2023',
        ];
    }

    public static function dateSameDayDataProvider(): iterable
    {
        yield 'same day' => [new \DateTime('2024-02-01 12:34:56'), new \DateTime('2024-02-01 12:34:56'), true];
        yield 'different days' => [new \DateTime('2024-02-01 12:34:56'), new \DateTime('2024-02-02 00:00:00'), false];
        yield 'first date is null' => [null, new \DateTime('2024-02-01 12:34:56'), false];
        yield 'second date is null' => [new \DateTime('2024-02-01 12:34:56'), null, false];
        yield 'both dates are null' => [null, null, true];
    }
}
