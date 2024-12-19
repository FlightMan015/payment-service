<?php

declare(strict_types=1);

namespace App\Factories;

use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use App\Validators\InitialServiceCompletedScheduledPaymentTriggerValidator;
use App\Validators\ScheduledPaymentTriggerMetadataValidatorInterface;
use Illuminate\Contracts\Container\BindingResolutionException;

class ScheduledPaymentTriggerMetadataValidatorFactory
{
    /**
     * @param ScheduledPaymentTriggerEnum $trigger
     *
     * @throws BindingResolutionException
     * @throws \Exception
     *
     * @return ScheduledPaymentTriggerMetadataValidatorInterface
     */
    public static function make(ScheduledPaymentTriggerEnum $trigger): ScheduledPaymentTriggerMetadataValidatorInterface
    {
        return match ($trigger) {
            ScheduledPaymentTriggerEnum::InitialServiceCompleted => app()->make(InitialServiceCompletedScheduledPaymentTriggerValidator::class),
            default => throw new \RuntimeException(__('messages.scheduled_payment.trigger_validator_not_implemented')),
        };
    }
}
