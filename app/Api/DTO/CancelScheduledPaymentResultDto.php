<?php

declare(strict_types=1);

namespace App\Api\DTO;

use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;

readonly class CancelScheduledPaymentResultDto
{
    /**
     * @param bool $isSuccess
     * @param ScheduledPaymentStatusEnum $status
     */
    public function __construct(
        public bool $isSuccess,
        public ScheduledPaymentStatusEnum $status,
    ) {
    }
}
