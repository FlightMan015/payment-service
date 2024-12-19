<?php

declare(strict_types=1);

namespace App\Exceptions;

class ScheduledPaymentDuplicateException extends AbstractUnprocessableScheduledPaymentException
{
    /**
     * @param string $duplicatePaymentId
     */
    public function __construct(string $duplicatePaymentId)
    {
        parent::__construct(message: __('messages.scheduled_payment.duplicate', ['id' => $duplicatePaymentId]));
    }
}
