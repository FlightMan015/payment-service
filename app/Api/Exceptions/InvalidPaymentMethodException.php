<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

class InvalidPaymentMethodException extends AbstractAPIException
{
    /**
     * Override parent construct
     *
     * @param string $paymentMethodId
     */
    public function __construct(string $paymentMethodId)
    {
        parent::__construct(message: __('messages.payment_method.invalid', ['id' => $paymentMethodId]));
    }
}
