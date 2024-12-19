<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Models\PaymentMethod;

final class ValidatePaymentMethodCommand
{
    /**
     * @param PaymentMethod $paymentMethod
     */
    public function __construct(public readonly PaymentMethod $paymentMethod)
    {
    }
}
