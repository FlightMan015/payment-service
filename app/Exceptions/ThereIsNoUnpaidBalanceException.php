<?php

declare(strict_types=1);

namespace App\Exceptions;

class ThereIsNoUnpaidBalanceException extends AbstractPaymentProcessingException
{
    /**
     * @param string $accountId
     */
    public function __construct(string $accountId)
    {
        parent::__construct(
            message: __('messages.payment.batch_processing.no_unpaid_balance'),
            context: ['account_id' => $accountId]
        );
    }
}
