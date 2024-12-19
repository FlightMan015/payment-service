<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum WorldpayTransactionMethodEnum: int
{
    case DEFAULT = 0;
    case PAYMENT_ACCOUNT_CREATE = 7;
}
