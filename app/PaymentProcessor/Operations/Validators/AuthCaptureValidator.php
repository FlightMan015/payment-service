<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Operations\Validators;

use App\PaymentProcessor\Enums\OperationFields;

class AuthCaptureValidator extends AbstractValidator implements ValidatorInterface
{
    protected static array $fields = [
        OperationFields::REFERENCE_ID,
        OperationFields::NAME_ON_ACCOUNT,
        OperationFields::AMOUNT,
        OperationFields::CHARGE_DESCRIPTION,
        OperationFields::ADDRESS_LINE_1,
        OperationFields::CITY,
        OperationFields::PROVINCE,
        OperationFields::POSTAL_CODE,
    ];

    protected static array $oneOfThese = [
        [
            OperationFields::TOKEN,
        ],
        [
            OperationFields::ACH_ACCOUNT_NUMBER,
            OperationFields::ACH_ROUTING_NUMBER
        ],
        [
            OperationFields::ACH_TOKEN,
        ],
    ];

    protected static array $formatCheckFields = [
        OperationFields::EMAIL_ADDRESS,
    ];
}
