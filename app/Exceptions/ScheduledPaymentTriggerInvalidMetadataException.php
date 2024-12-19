<?php

declare(strict_types=1);

namespace App\Exceptions;

class ScheduledPaymentTriggerInvalidMetadataException extends AbstractUnprocessableScheduledPaymentException
{
    /**
     * @param string $message
     */
    public function __construct(string $message)
    {
        parent::__construct(message: __('messages.scheduled_payment.metadata_validation_error', ['message' => $message]));
    }
}
