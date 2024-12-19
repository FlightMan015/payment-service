<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum InvoiceStatusEnum: string
{
    case PAID = 'paid';

    case UNPAID = 'unpaid';
}
