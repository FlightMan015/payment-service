<?php

declare(strict_types=1);

namespace App\Entities\Enums;

enum SubscriptionInitialStatusEnum: int
{
    case NO_SHOW = 2;
    case COMPLETED = 1;
    case PENDING = 0;
    case CANCELLED = -1;
    case RESCHEDULED = -2;
}
