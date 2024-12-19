<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

class PaymentMethodDoesNotBelongToAccountAssociatedWithSubscriptionException extends \RuntimeException
{
    /**
     * @param string $paymentMethodId
     * @param string $accountId
     * @param string $subscriptionId
     */
    public function __construct(string $paymentMethodId, string $accountId, string $subscriptionId)
    {
        parent::__construct(
            message: __(
                key: 'messages.payment_method.does_not_belong_to_account_associated_to_subscription',
                replace: [
                    'method' => $paymentMethodId,
                    'account' => $accountId,
                    'subscription' => $subscriptionId,
                ]
            )
        );
    }
}
