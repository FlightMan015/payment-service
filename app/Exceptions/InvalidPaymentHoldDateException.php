<?php

declare(strict_types=1);

namespace App\Exceptions;

class InvalidPaymentHoldDateException extends AbstractPaymentProcessingException
{
    /**
     * @param \DateTimeInterface $paymentHoldDate
     */
    public function __construct(\DateTimeInterface $paymentHoldDate)
    {
        parent::__construct(
            message: __('messages.payment.batch_processing.invalid_payment_hold_date'),
            context: ['payment_hold_date' => $paymentHoldDate]
        );
    }
}
