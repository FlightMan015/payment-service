<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums\Database;

enum PaymentStatusEnum: int
{
    case AUTH_CAPTURING = 1;
    case CAPTURED = 2;
    case AUTHORIZING = 3;
    case AUTHORIZED = 4;
    case CAPTURING = 5;
    case CANCELLING = 6;
    case CANCELLED = 7;
    case CREDITING = 8;
    case CREDITED = 9;
    case DECLINED = 10;
    case SUSPENDED = 11;
    case TERMINATED = 12;
    case PROCESSED = 13;
    case RETURNED = 14;
    case SETTLED = 15;

    /**
     * @param string $name
     *
     * @return self
     */
    public static function fromName(string $name): self
    {
        foreach (self::cases() as $status) {
            if ($name === $status->name) {
                return $status;
            }
        }

        throw new \ValueError(message: __('messages.enum.invalid_value', ['name' => $name, 'class' => self::class]));
    }
}
