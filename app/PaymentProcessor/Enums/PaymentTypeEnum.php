<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum PaymentTypeEnum: int
{
    case CC = 1;
    case ACH = 2;
    case CASH = 3;
    case CHECK = 4;
    case COUPON = 5;
    case CREDIT_MEMO = 6;

    /**
     * @param string $name
     *
     * @return PaymentTypeEnum
     */
    public static function fromName(string $name): self
    {
        return match ($name) {
            self::CC->name => self::CC,
            self::ACH->name => self::ACH,
            self::CASH->name => self::CASH,
            self::CHECK->name => self::CHECK,
            self::COUPON->name => self::COUPON,
            self::CREDIT_MEMO->name => self::CREDIT_MEMO,
            default => throw new \ValueError(message: __('messages.enum.invalid_value', ['name' => $name, 'class' => self::class]))
        };
    }

    /**
     * Returns list of types that are credit card types
     *
     * @return PaymentTypeEnum[]
     */
    public static function creditCardTypes(): array
    {
        return array_filter(
            array: self::cases(),
            callback: static fn (PaymentTypeEnum $type) => $type->isCreditCardType()
        );
    }

    /**
     * Returns list of types that are electronic types
     *
     * @return PaymentTypeEnum[]
     */
    public static function electronicTypes(): array
    {
        return array_filter(
            array: self::cases(),
            callback: static fn (PaymentTypeEnum $type) => $type->isCreditCardType() || $type === PaymentTypeEnum::ACH
        );
    }

    /**
     * Returns list of names of types that are credit card types
     *
     * @return array
     */
    public static function creditCardNames(): array
    {
        return array_map(
            callback: static fn (PaymentTypeEnum $type) => $type->name,
            array: self::creditCardTypes()
        );
    }

    /**
     * Returns list of values of types that are credit card types
     *
     * @return array
     */
    public static function creditCardValues(): array
    {
        return array_map(
            callback: static fn (PaymentTypeEnum $type) => $type->value,
            array: self::creditCardTypes()
        );
    }

    /**
     * Identifies if type is Credit Card type
     *
     * @return bool
     */
    public function isCreditCardType(): bool
    {
        return match ($this) {
            self::CC => true,
            default => false
        };
    }

    /**
     * Returns list of types that are ledger only types
     *
     * @return PaymentTypeEnum[]
     */
    public static function ledgerOnlyTypes(): array
    {
        return array_filter(
            array: self::cases(),
            callback: static fn (PaymentTypeEnum $type) => $type->isLedgerOnlyType()
        );
    }

    /**
     * Returns list of names of types that are ledger only types
     *
     * @return array
     */
    public static function ledgerOnlyNames(): array
    {
        return array_map(
            callback: static fn (PaymentTypeEnum $type) => $type->name,
            array: self::ledgerOnlyTypes()
        );
    }

    /**
     * Returns list of values of types that are ledger only types
     *
     * @return array
     */
    public static function ledgerOnlyValues(): array
    {
        return array_map(
            callback: static fn (PaymentTypeEnum $type) => $type->value,
            array: self::ledgerOnlyTypes()
        );
    }

    /**
     * Identifies if type is Ledger Only type
     *
     * @return bool
     */
    public function isLedgerOnlyType(): bool
    {
        return match ($this) {
            self::CHECK, self::CASH => true,
            default => false
        };
    }
}
