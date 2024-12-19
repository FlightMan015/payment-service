<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum WorldpayReversalTypeEnum: string
{
    case SYSTEM = '0';
    case FULL = '1';
    case PARTIAL = '2';
}
