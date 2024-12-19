<?php

declare(strict_types=1);

namespace App\Exceptions;

class DailyPaymentAttemptsExceededException extends AbstractPaymentProcessingException
{
    /**
     * @param int $maxPaymentAttempts
     */
    public function __construct(int $maxPaymentAttempts)
    {
        parent::__construct(
            message: __('messages.payment.batch_processing.daily_payment_attempts_exceeded'),
            context: ['max_payment_attempts' => $maxPaymentAttempts]
        );
    }
}
