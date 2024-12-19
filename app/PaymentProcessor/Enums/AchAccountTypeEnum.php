<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum AchAccountTypeEnum: string
{
    case PERSONAL_CHECKING = 'personal_checking';
    case PERSONAL_SAVINGS = 'personal_savings';
    case BUSINESS_CHECKING = 'business_checking';
    case BUSINESS_SAVINGS = 'business_savings';
    private const int CHECKING = 0;
    private const int SAVINGS = 0;
    private const int PERSONAL = 0;
    private const int BUSINESS = 1;

    /**
     * @return int
     */
    public function ddaAccountTypeId(): int
    {
        return match ($this) {
            self::PERSONAL_CHECKING,
            self::BUSINESS_CHECKING => self::CHECKING,
            self::PERSONAL_SAVINGS,
            self::BUSINESS_SAVINGS => self::SAVINGS
        };
    }

    /**
     * @return int
     */
    public function checkType(): int
    {
        return match ($this) {
            self::PERSONAL_CHECKING,
            self::PERSONAL_SAVINGS => self::PERSONAL,
            self::BUSINESS_CHECKING,
            self::BUSINESS_SAVINGS => self::BUSINESS
        };
    }
}
