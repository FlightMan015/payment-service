<?php

declare(strict_types=1);

namespace App\Api\DTO;

class CreatePaymentMethodResultDto
{
    /**
     * @param string $paymentMethodId
     */
    public function __construct(public readonly string $paymentMethodId)
    {
    }
}
