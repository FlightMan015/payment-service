<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AbstractScheduledPaymentEvent;
use App\Events\ScheduledPaymentCancelledEvent;
use Illuminate\Support\Facades\Log;

class CollectScheduledPaymentCancelledMetricsListener extends AbstractScheduledPaymentListener
{
    /**
     * Handle the event.
     *
     * @param ScheduledPaymentCancelledEvent $event
     *
     * @throws \Exception
     */
    public function handle(AbstractScheduledPaymentEvent $event): void
    {
        Log::info(message: 'Collect Scheduled Payment Cancelled Metrics Job Started', context: ['job_id' => $this->job?->uuid()]);

        $point = $this->buildDatapoint(name: 'scheduled-payments-cancelled', event: $event);

        $this->writeApi->write($point);
        $this->writeApi->close();
    }

    /** @inheritDoc */
    public function failed(AbstractScheduledPaymentEvent $event, \Throwable $exception): void
    {
        Log::error(
            message: 'FAILED - Collect Scheduled Payment Cancelled Metrics',
            context: [
                'payment_id' => $event->payment->id,
                'exception' => $exception
            ]
        );
        $this->writeApi->close();
    }
}
