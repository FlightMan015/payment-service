<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums\Database;

enum ScheduledPaymentTriggerEnum: int
{
    case InitialServiceCompleted = 1;
    case NextServiceCompleted = 2; // this is a trigger that doesn't actually exist yet, and just needed for testing purposes
}
