<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Operations\Validators;

use App\PaymentProcessor\Enums\OperationFields;

class CancelValidator extends AbstractValidator implements ValidatorInterface
{
    protected static array $fields = [
        OperationFields::AMOUNT,
        OperationFields::REFERENCE_ID,
        OperationFields::REFERENCE_TRANSACTION_ID
    ];
}
