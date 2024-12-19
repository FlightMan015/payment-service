<?php

declare(strict_types=1);

namespace App\Exceptions;

class AutopayPaymentMethodNotFound extends AbstractPaymentProcessingException
{
    /**
     * @param string $accountId
     */
    public function __construct(string $accountId)
    {
        parent::__construct(
            message: __('messages.payment.batch_processing.autopay_method_not_found'),
            context: ['account_id' => $accountId]
        );
    }
}
