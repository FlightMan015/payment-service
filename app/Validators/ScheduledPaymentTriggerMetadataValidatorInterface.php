<?php

declare(strict_types=1);

namespace App\Validators;

interface ScheduledPaymentTriggerMetadataValidatorInterface
{
    /**
     * @param array $metadata Metadata for processing the scheduled payment by the trigger (e.g. subscription_id, appointment_id, etc)
     *
     * Should throw ScheduledPaymentTriggerInvalidMetadataException if the metadata is invalid
     *
     * @return void
     */
    public function validate(array $metadata = []): void;
}
