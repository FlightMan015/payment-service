<?php

declare(strict_types=1);

namespace App\Exceptions;

class InvalidPreferredBillingDateException extends AbstractPaymentProcessingException
{
    /**
     * @param int $preferredDay
     */
    public function __construct(int $preferredDay)
    {
        parent::__construct(
            message: __('messages.payment.batch_processing.invalid_preferred_billing_date'),
            context: ['preferred_day' => $preferredDay]
        );
    }
}
