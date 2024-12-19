<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum CurrencyCodeEnum: string
{
    case USD = 'USD';
    case CAD = 'CAD';
}
