<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SuspendedPaymentProcessedEvent;
use App\Helpers\MoneyHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;

class CollectSuspendedPaymentProcessedMetricsListener implements ShouldQueue
{
    use InteractsWithQueue;

    private readonly WriteApi $writeApi;

    /**
     * Create the event listener.
     *
     * @param InfluxClient $influxClient
     */
    public function __construct(
        private readonly InfluxClient $influxClient,
    ) {
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
     * @param SuspendedPaymentProcessedEvent $event
     */
    public function handle(SuspendedPaymentProcessedEvent $event): void
    {
        Log::info(message: 'Collect Payment Processed Metrics Job Started', context: ['job_id' => $this->job?->uuid()]);

        $point = new Point('suspended-payments-processed');

        $point
            ->addField('total_amount', MoneyHelper::convertToDecimal($event->processedPayment->amount))
            ->addField('customer_external_reference_id', $event->account->external_ref_id)
            ->addField('customer_account_id', $event->account->id)
            ->addField('office_id', $event->account->area->external_ref_id)
            ->addField('area_id', $event->account->area->id)
            ->addField('payment_method_id', $event->paymentMethod?->id)
            ->addField('payment_type', $event->processedPayment->type->name)
            ->addField('payment_id', $event->processedPayment->id)
            ->addField('original_payment_id', $event->originalPayment->id)
            ->addField('original_payment_processing_date', $event->originalPayment->processed_at)
            ->addTag('office', $event->account->area->name)
            ->time($event->timestamp);

        $this->writeApi->write($point);

        $this->writeApi->close();
    }

    /**
     * Handle a job failure.
     *
     * @param SuspendedPaymentProcessedEvent $event
     * @param \Throwable $exception
     */
    public function failed(SuspendedPaymentProcessedEvent $event, \Throwable $exception): void
    {
        Log::error(
            message: 'FAILED - Collect Payment Processed Metrics',
            context: [
                'account_id' => $event->account->id,
                'payment_id' => $event->processedPayment->id,
                'original_payment_id' => $event->originalPayment->id,
                'payment_method' => $event->paymentMethod?->id,
                'payment_type' => $event->processedPayment->type->name,
                'exception' => $exception
            ]
        );
        $this->writeApi->close();
    }
}
