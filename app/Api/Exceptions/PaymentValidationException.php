<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

class PaymentValidationException extends AbstractAPIException
{
    public array $errors = [];

    /**
     * @param string $message
     * @param array $errors
     */
    public function __construct(string $message = '', array $errors = [])
    {
        parent::__construct(message: $message);

        $this->errors = array_map(static fn ($message) => ['detail' => $message], $errors);
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
