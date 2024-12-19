<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums\Database;

enum DeclineReasonEnum: int
{
    case DECLINED = 1;
    case EXPIRED = 2;
    case DUPLICATE = 3;
    case INVALID = 4;
    case FRAUD = 5;
    case INSUFFICIENT_FUNDS = 6;
    case ERROR = 7;
    case CONTACT_FINANCIAL_INSTITUTION = 8;
}
