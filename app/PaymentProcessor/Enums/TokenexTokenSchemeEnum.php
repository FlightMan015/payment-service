<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum TokenexTokenSchemeEnum: string
{
    case ANTOKEN4 = 'ANTOKEN4';
    case ANTOKEN512 = 'ANTOKEN512';
    case ASCII = 'ASCII';
    case PCI = 'PCI';
}
