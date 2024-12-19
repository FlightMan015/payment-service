<?php

declare(strict_types=1);

namespace App\Helpers;

final class MoneyHelper
{
    /**
     * @param float $amount
     *
     * @return int
     */
    public static function convertToCents(float $amount): int
    {
        return (int)round($amount * 100);
    }

    /**
     * @param int $amount
     *
     * @return float
     */
    public static function convertToDecimal(int $amount): float
    {
        return $amount / 100;
    }
}
