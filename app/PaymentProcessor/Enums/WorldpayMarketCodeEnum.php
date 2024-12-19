<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum WorldpayMarketCodeEnum: string
{
    case DEFAULT = '0';
    case AUTO_RENTAL = '1';
    case DIRECT_MARKETING = '2';
    case E_COMMERCE = '3';
    case FOOD_RESTAURANT = '4';
    case HOTEL_LODGING = '5';
    case PETROLEUM = '6';
    case RETAIL = '7';
    case QSR = '8';
    case GROCERY = '9';
}
