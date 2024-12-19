<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum WorldpayResponseCodeEnum: int
{
    case APPROVED_SUCCESS = 0;
    case PARTIAL_APPROVAL = 5;
    case DCC_REQUESTED = 7;
    case NON_FINANCIAL_CARD = 8;
    case DECLINED = 20;
    case EXPIRED_CARD = 21;
    case DUPLICATE_APPROVED = 22;
    case DUPLICATE = 23;
    case PICK_UP_CARD = 24;
    case REFERRAL_CALL_ISSUER = 25;
    case BALANCE_NOT_AVAILABLE = 30;
    case NOT_DEFINED = 90;
    case INVALID_DATA = 101;
    case INVALID_ACCOUNT = 102;
    case INVALID_REQUEST = 103;
    case AUTHORIZATION_FAILED = 104;
    case NOT_AUTHORIZED = 105;
    case OUT_OF_BALANCE = 120;
    case COMMUNICATION_ERROR = 1001;
    case HOST_ERROR = 1002;
    case ERROR = 1009;

    case TRANSACTION_STATUS_CODE_RETURNED = 9;
    case TRANSACTION_STATUS_CODE_ERROR = 13;
    case TRANSACTION_STATUS_CODE_SETTLED = 15;

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return match ($this) {
            self::APPROVED_SUCCESS => true,
            default => false,
        };
    }
}
