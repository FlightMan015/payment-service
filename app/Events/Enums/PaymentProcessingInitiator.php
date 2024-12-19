<?php

declare(strict_types=1);

namespace App\Events\Enums;

/**
 * Enum for which technical process initiated the processing of a payment
 */
enum PaymentProcessingInitiator: string
{
    case BATCH_PROCESSING = 'Batch Processing';
    case API_REQUEST = 'Api Request';
}
