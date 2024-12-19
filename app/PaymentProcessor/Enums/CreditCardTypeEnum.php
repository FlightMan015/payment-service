<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum CreditCardTypeEnum: string
{
    case VISA = 'VISA';
    case MASTERCARD = 'MASTERCARD';
    case AMEX = 'AMEX';
    case DISCOVER = 'DISCOVER';
    case OTHER = 'OTHER';

    /**
     * @param string $name
     *
     * @return CreditCardTypeEnum
     */
    public static function fromName(string $name): self
    {
        return match ($name) {
            self::VISA->name => self::VISA,
            self::MASTERCARD->name => self::MASTERCARD,
            self::AMEX->name => self::AMEX,
            self::DISCOVER->name => self::DISCOVER,
            self::OTHER->name => self::OTHER,
            default => throw new \ValueError(message: __('messages.enum.invalid_value', ['name' => $name, 'class' => self::class]))
        };
    }
}
