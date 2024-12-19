<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

class PaymentMethodDoesNotBelongToAccountException extends \RuntimeException
{
    /**
     * @param string $paymentMethodId
     * @param string $accountId
     */
    public function __construct(string $paymentMethodId, string $accountId)
    {
        parent::__construct(
            message: __(
                key: 'messages.payment_method.does_not_belong_to_account',
                replace: ['paymentMethod' => $paymentMethodId, 'account' => $accountId]
            )
        );
    }
}
