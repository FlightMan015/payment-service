<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums\Database;

enum TransactionTypeEnum: int
{
    case AUTH_CAPTURE = 1;
    case AUTHORIZE = 2;
    case CAPTURE = 3;
    case CANCEL = 4;
    case CHECK_STATUS = 5;
    case CREDIT = 6;
    case TOKENIZE = 7;
    case RETURN = 8;
}
