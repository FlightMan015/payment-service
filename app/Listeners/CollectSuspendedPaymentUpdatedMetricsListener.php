<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SuspendedPaymentUpdatedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;

class CollectSuspendedPaymentUpdatedMetricsListener implements ShouldQueue
{
    use InteractsWithQueue;

    private readonly WriteApi $writeApi;

    /**
     * Create the event listener.
     *
     * @param InfluxClient $influxClient
     */
    public function __construct(private readonly InfluxClient $influxClient)
    {
        $this->writeApi = $this->influxClient->createWriteApi();
    }

    /**
     * @return string the name of the listener's queue
     */
    public function viaQueue(): string
    {
        return config(key: 'queue.connections.sqs.queues.collect_metrics');
    }

    /**
     * Handle the event.
     *
     * @param SuspendedPaymentUpdatedEvent $event
     *
     * @throws \Exception
     */
    public function handle(SuspendedPaymentUpdatedEvent $event): void
    {
        Log::info(message: 'Collect Suspended Payment being updated (Terminated / Processed) Metrics Job Started', context: ['job_id' => $this->job?->uuid()]);

        $point = new Point('suspended-payment-resolutions');

        $point
            ->addField('payment_id', $event->payment?->id)
            ->addField('office_id', $event->account->area->external_ref_id)
            ->addField('area_id', $event->account->area->id)
            ->addField('payment_method_id', $event->paymentMethod?->id)
            ->addField('customer_account_id', $event->account->id)
            ->addField('customer_external_reference_id', $event->account->external_ref_id)
            ->addField('payment_type_id', $event->payment?->type->id)
            ->addTag('office', $event->account->area->name)
            ->addTag('resolution', $event->resolution->value)
            ->time($event->timestamp);

        $this->writeApi->write($point);

        $this->writeApi->close();
    }

    /**
     * Handle a job failure.
     *
     * @param SuspendedPaymentUpdatedEvent $event
     * @param \Throwable $exception
     */
    public function failed(SuspendedPaymentUpdatedEvent $event, \Throwable $exception): void
    {
        Log::error(
            message: 'FAILED - Collect Suspended Payment being Updated Metrics',
            context: [
                'account_id' => $event->account->id,
                'exception' => $exception
            ]
        );
        $this->writeApi->close();
    }
}
