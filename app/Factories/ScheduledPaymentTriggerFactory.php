<?php

declare(strict_types=1);

namespace App\Factories;

use App\Jobs\ScheduledPayment\Triggers\AbstractScheduledPaymentTriggerJob;
use App\Jobs\ScheduledPayment\Triggers\InitialServiceCompletedScheduledPaymentTriggerJob;
use App\Models\ScheduledPayment;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;

class ScheduledPaymentTriggerFactory
{
    /**
     * @param ScheduledPayment $payment
     *
     * @throws \Exception
     *
     * @return AbstractScheduledPaymentTriggerJob
     */
    public static function make(ScheduledPayment $payment): AbstractScheduledPaymentTriggerJob
    {
        $jobClass = match ($payment->payment_trigger) {
            ScheduledPaymentTriggerEnum::InitialServiceCompleted => InitialServiceCompletedScheduledPaymentTriggerJob::class,
            default => throw new \Exception(__('messages.scheduled_payment.trigger_not_implemented')),
        };

        return new $jobClass($payment);
    }
}
