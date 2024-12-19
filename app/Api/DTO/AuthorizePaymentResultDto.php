<?php

declare(strict_types=1);

namespace App\Api\DTO;

use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;

/**
 * @property PaymentStatusEnum $status
 * @property string|null $paymentId
 * @property string|null $transactionId
 * @property string|null $message
 */
class AuthorizePaymentResultDto
{
    /**
     * @param PaymentStatusEnum $status
     * @param string|null $paymentId
     * @param string|null $transactionId
     * @param string|null $message
     */
    public function __construct(
        public PaymentStatusEnum $status,
        public string|null $paymentId = null,
        public string|null $transactionId = null,
        public string|null $message = null
    ) {
    }
}
