<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum PaymentResolutionEnum: string
{
    case TERMINATED = 'terminated';

    case SUBMITTED = 'submitted';
}
