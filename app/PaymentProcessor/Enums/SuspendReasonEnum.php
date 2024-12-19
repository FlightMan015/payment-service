<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum SuspendReasonEnum: int
{
    case DUPLICATED = 1;
}
