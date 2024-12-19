<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums\Database;

enum ScheduledPaymentStatusEnum: int
{
    case PENDING = 1;
    case CANCELLED = 2;
    case SUBMITTED = 3;
}
