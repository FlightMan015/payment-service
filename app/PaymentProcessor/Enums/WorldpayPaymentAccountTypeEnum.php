<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum WorldpayPaymentAccountTypeEnum: int
{
    case CreditCard = 0;
    case Checking = 1;
    case Savings = 2;
    case ACH = 3;
    case Other = 4;
}
