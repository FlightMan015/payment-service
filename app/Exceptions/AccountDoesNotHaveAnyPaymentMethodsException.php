<?php

declare(strict_types=1);

namespace App\Exceptions;

class AccountDoesNotHaveAnyPaymentMethodsException extends AbstractPaymentProcessingException
{
    /**
     * @param string $accountId
     */
    public function __construct(string $accountId)
    {
        parent::__construct(
            message: __('messages.payment.batch_processing.account_does_not_have_payment_methods'),
            context: ['account_id' => $accountId]
        );
    }
}
