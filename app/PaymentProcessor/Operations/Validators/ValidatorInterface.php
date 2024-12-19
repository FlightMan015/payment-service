<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Operations\Validators;

interface ValidatorInterface
{
    /**
     * @return bool
     */
    public function validate(): bool;

    /**
     * @return array
     */
    public function getErrors(): array;
}
