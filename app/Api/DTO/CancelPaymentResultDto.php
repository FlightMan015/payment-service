<?php

declare(strict_types=1);

namespace App\Api\DTO;

use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;

readonly class CancelPaymentResultDto
{
    /**
     * @param bool $isSuccess
     * @param PaymentStatusEnum $status
     * @param string|null $transactionId
     */
    public function __construct(
        public bool $isSuccess,
        public PaymentStatusEnum $status,
        public string|null $transactionId = null
    ) {
    }
}
