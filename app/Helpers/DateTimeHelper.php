<?php

declare(strict_types=1);

namespace App\Helpers;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class DateTimeHelper
{
    public const string GENERAL_DATETIME_FORMAT = 'Y-m-d H:i:s';
    public const string GENERAL_DATE_FORMAT = 'Y-m-d';

    /**
     * Convert given $value to desired format
     *
     * @param CarbonInterface|string $value Value from DB, from input, ....
     * @param string $format Desired returning format
     *
     * @return string
     */
    public static function convertToFormat(CarbonInterface|string $value, string $format): string
    {
        return Carbon::make($value)->format($format);
    }

    /**
     * Format input to datetime
     *
     * @param CarbonInterface|string $value
     *
     * @return string
     */
    public static function formatDateTime(CarbonInterface|string $value): string
    {
        return self::convertToFormat(value: $value, format: self::GENERAL_DATETIME_FORMAT);
    }

    /**
     * @param \DateTimeInterface|null $date1
     * @param \DateTimeInterface|null $date2
     *
     * @return bool
     */
    public static function isTwoDatesAreSameDay(\DateTimeInterface|null $date1, \DateTimeInterface|null $date2): bool
    {
        if (!is_null($date1) && !is_null($date2)) {
            return Carbon::instance($date1)->isSameDay(date: $date2);
        }

        return $date1 === $date2; // handle case where one of the days is null but not both
    }
}
