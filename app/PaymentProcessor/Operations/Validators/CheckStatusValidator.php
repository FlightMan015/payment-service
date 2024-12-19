<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Operations\Validators;

use App\PaymentProcessor\Enums\OperationFields;

class CheckStatusValidator extends AbstractValidator implements ValidatorInterface
{
    protected static array $fields = [
        OperationFields::REFERENCE_TRANSACTION_ID,
        OperationFields::REFERENCE_ID
    ];
}
