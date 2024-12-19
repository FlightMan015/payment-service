<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Operations\Validators;

use App\PaymentProcessor\Enums\OperationFields;

class AuthorizeValidator extends AbstractValidator implements ValidatorInterface
{
    protected static array $fields = [
        OperationFields::AMOUNT,
        OperationFields::CHARGE_DESCRIPTION,
    ];

    protected static array $oneOfThese = [
        [
            OperationFields::TOKEN,
        ],
        [
            OperationFields::ACH_ACCOUNT_NUMBER,
            OperationFields::ACH_ROUTING_NUMBER,
        ],
        [
            OperationFields::ACH_TOKEN,
        ],
    ];
}
