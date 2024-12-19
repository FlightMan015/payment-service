<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum InvoiceCurrencyCodeEnum: string
{
    case USD = 'USD';
    case CAD = 'CAD';
}
