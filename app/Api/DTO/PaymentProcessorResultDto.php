<?php

declare(strict_types=1);

namespace App\Api\DTO;

/**
 * @property bool $isSuccess
 * @property string|null $transactionId
 * @property string|null $message
 */
class PaymentProcessorResultDto
{
    /**
     * @param bool $isSuccess
     * @param string|null $transactionId
     * @param string|null $message
     */
    public function __construct(
        public bool $isSuccess,
        public string|null $transactionId = null,
        public string|null $message = ''
    ) {
    }
}
