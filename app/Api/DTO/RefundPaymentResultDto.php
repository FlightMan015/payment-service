<?php

declare(strict_types=1);

namespace App\Api\DTO;

use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;

readonly class RefundPaymentResultDto
{
    /**
     * @param bool $isSuccess
     * @param PaymentStatusEnum $status
     * @param string $refundPaymentId
     * @param string|null $transactionId
     * @param string|null $errorMessage
     */
    public function __construct(
        public bool $isSuccess,
        public PaymentStatusEnum $status,
        public string $refundPaymentId,
        public string|null $transactionId = null,
        public string|null $errorMessage = null,
    ) {
    }
}
