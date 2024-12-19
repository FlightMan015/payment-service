<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Exceptions;

class OperationValidationException extends \InvalidArgumentException
{
    /**
     * @param array $errors
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(array $errors, int $code = 0, \Throwable|null $previous = null)
    {
        $message = implode(separator: "\n", array: $errors);

        parent::__construct(message: $message, code: $code, previous: $previous);
    }
}
