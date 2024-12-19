<?php

declare(strict_types=1);

namespace App\Exceptions;

class UnpaidInvoicesBalanceMismatchException extends AbstractPaymentProcessingException
{
    /**
     * @param array $context
     */
    public function __construct(array $context)
    {
        parent::__construct(
            message: __('messages.payment.batch_processing.invoices_unpaid_total_balance_mismatch_account_ledger'),
            context: $context
        );
    }
}
