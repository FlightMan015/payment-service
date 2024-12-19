<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

class PaymentProcessingValidationException extends UnprocessableContentException
{
    /**
     * @param string $message
     * @param array $context
     */
    public function __construct(string $message, public readonly array $context = [])
    {
        parent::__construct(__('messages.payment.process_validation_error', ['message' => $message]));
    }
}
