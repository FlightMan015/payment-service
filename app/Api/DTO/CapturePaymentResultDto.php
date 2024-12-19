<?php

declare(strict_types=1);

namespace App\Api\DTO;

use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;

/**
 * @property bool $isSuccess
 * @property PaymentStatusEnum $status
 * @property string|null $transactionId
 * @property string|null $message
 */
class CapturePaymentResultDto
{
    /**
     * @param bool $isSuccess
     * @param PaymentStatusEnum $status
     * @param string|null $transactionId
     * @param string|null $message
     */
    public function __construct(
        public bool $isSuccess,
        public PaymentStatusEnum $status,
        public string|null $transactionId = null,
        public string|null $message = ''
    ) {
    }
}
