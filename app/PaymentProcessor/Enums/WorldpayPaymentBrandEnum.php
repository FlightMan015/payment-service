<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum WorldpayPaymentBrandEnum: string
{
    case VISA = 'Visa';
    case MASTERCARD = 'Mastercard';
    case DISCOVER = 'Discover';
    case AMEX = 'Amex';
    case DINERS_CLUB = 'Diners Club';
    case JCB = 'JCB';
    case CARTE_BLANCHE = 'Carte Blanche';
    case OTHER = 'Other';
    case UNION_PAY = 'Union Pay';
}
