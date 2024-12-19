<?php

declare(strict_types=1);

namespace App\Exceptions;

class PaymentTerminatedException extends AbstractPaymentProcessingException
{
    /**
     * @param array $context
     */
    public function __construct(array $context)
    {
        parent::__construct(
            message: __('messages.payment.batch_processing.previous_payment_already_terminated', [
                'id' => $context['terminated_payment_id']
            ]),
            context: $context
        );
    }
}
