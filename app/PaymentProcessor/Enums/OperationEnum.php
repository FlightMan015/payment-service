<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Exceptions\InvalidOperationException;

enum OperationEnum: int
{
    case AUTH_CAPTURE = 1;
    case AUTHORIZE = 2;
    case CAPTURE = 3;
    case CANCEL = 4;
    case CHECK_STATUS = 5;
    case CREDIT = 6;
    case TOKENIZE = 7;

    /**
     * @param PaymentStatusEnum $status
     *
     * @return self[]|self operation that could be performed according to the given payment status
     */
    public static function forPaymentStatus(PaymentStatusEnum $status): array|self
    {
        return match ($status) {
            PaymentStatusEnum::AUTH_CAPTURING => [
                self::AUTH_CAPTURE,
                self::CHECK_STATUS,
            ],
            PaymentStatusEnum::CAPTURED,
            PaymentStatusEnum::CAPTURING => [
                self::CAPTURE,
                self::AUTH_CAPTURE,
                self::CHECK_STATUS,
            ],
            PaymentStatusEnum::AUTHORIZING,
            PaymentStatusEnum::AUTHORIZED => self::AUTHORIZE,
            PaymentStatusEnum::CANCELLING,
            PaymentStatusEnum::CANCELLED => self::CANCEL,
            PaymentStatusEnum::CREDITING,
            PaymentStatusEnum::CREDITED => self::CREDIT,
            PaymentStatusEnum::DECLINED => [
                self::AUTH_CAPTURE,
                self::AUTHORIZE,
                self::CAPTURE,
                self::CANCEL,
                self::CREDIT
            ],
            PaymentStatusEnum::SUSPENDED => throw new InvalidOperationException('Cannot perform any operation on suspended payment'),
            PaymentStatusEnum::TERMINATED => throw new InvalidOperationException('Cannot perform any operation on terminated payment'),
            PaymentStatusEnum::RETURNED => throw new InvalidOperationException('Cannot perform any operation on returned payment'),
            PaymentStatusEnum::SETTLED => throw new InvalidOperationException('Cannot perform any operation on settled payment'),
            PaymentStatusEnum::PROCESSED => self::AUTH_CAPTURE,
        };
    }
}
