<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Payment;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use Carbon\Carbon;

class PaymentOperationValidationHelper
{
    public const int CAPTURE_OPERATION_TIMEOUT_IN_DAYS = 7;

    /**
     * @param Payment $payment
     * @param OperationEnum $operation
     *
     * @return bool
     */
    public static function isPaymentExpiredForOperation(Payment $payment, OperationEnum $operation): bool
    {
        $numOfDay = match ($operation) {
            OperationEnum::CAPTURE => self::CAPTURE_OPERATION_TIMEOUT_IN_DAYS,
            default => throw new \RuntimeException(
                message: __('messages.operation.not_supported')
            ),
        };

        return Carbon::parse($payment->processed_at)
            ->addDays(value: $numOfDay)
            ->lessThanOrEqualTo(Carbon::now());
    }

    /**
     * @param Payment $payment
     * @param OperationEnum $operation
     *
     * @return bool
     */
    public static function isValidPaymentStatusForOperation(Payment $payment, OperationEnum $operation): bool
    {
        return match ($operation) {
            OperationEnum::CAPTURE => $payment->isStatus(status: PaymentStatusEnum::AUTHORIZED),
            OperationEnum::CREDIT => $payment->isStatus(status: PaymentStatusEnum::CAPTURED),
            OperationEnum::CANCEL => $payment->isStatus(status: [
                PaymentStatusEnum::CAPTURED,
                PaymentStatusEnum::CAPTURING,
                PaymentStatusEnum::AUTHORIZING,
                PaymentStatusEnum::AUTHORIZED,
                PaymentStatusEnum::AUTH_CAPTURING
            ]),
            default => false,
        };
    }
}
