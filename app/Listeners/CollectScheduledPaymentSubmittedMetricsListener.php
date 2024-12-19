<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AbstractScheduledPaymentEvent;
use App\Events\ScheduledPaymentSubmittedEvent;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use Illuminate\Support\Facades\Log;

class CollectScheduledPaymentSubmittedMetricsListener extends AbstractScheduledPaymentListener
{
    /**
     * Handle the event.
     *
     * @param ScheduledPaymentSubmittedEvent $event
     *
     * @throws \Exception
     */
    public function handle(AbstractScheduledPaymentEvent $event): void
    {
        Log::info(message: 'Collect Scheduled Payment Submitted Metrics Job Started', context: ['job_id' => $this->job?->uuid()]);

        $point = $this->buildDatapoint(name: 'scheduled-payments-submitted', event: $event);
        $point->addField('payment_id', $event->payment->id)
            ->addTag('gateway', PaymentGatewayEnum::from($event->resultPayment->payment_gateway_id)->name)
            ->addTag('status', PaymentStatusEnum::from($event->resultPayment->payment_status_id)->name);

        $this->writeApi->write($point);
        $this->writeApi->close();
    }

    /** @inheritDoc */
    public function failed(AbstractScheduledPaymentEvent $event, \Throwable $exception): void
    {
        Log::error(
            message: 'FAILED - Collect Scheduled Payment Submitted Metrics',
            context: [
                'payment_id' => $event->payment->id,
                'exception' => $exception
            ]
        );
        $this->writeApi->close();
    }
}
