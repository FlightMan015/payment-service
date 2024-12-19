<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AbstractScheduledPaymentEvent;
use App\Events\PaymentScheduledEvent;
use Illuminate\Support\Facades\Log;

class CollectPaymentScheduledMetricsListener extends AbstractScheduledPaymentListener
{
    /**
     * Handle the event.
     *
     * @param PaymentScheduledEvent $event
     *
     * @throws \Exception
     */
    public function handle(AbstractScheduledPaymentEvent $event): void
    {
        Log::info(message: 'Collect Payment Scheduled Metrics Job Started', context: ['job_id' => $this->job?->uuid()]);

        $point = $this->buildDatapoint(name: 'payments-scheduled', event: $event);

        $this->writeApi->write($point);
        $this->writeApi->close();
    }

    /** @inheritDoc */
    public function failed(AbstractScheduledPaymentEvent $event, \Throwable $exception): void
    {
        Log::error(
            message: 'FAILED - Collect Payment Scheduled Metrics',
            context: [
                'payment_id' => $event->payment->id,
                'exception' => $exception
            ]
        );
        $this->writeApi->close();
    }
}
