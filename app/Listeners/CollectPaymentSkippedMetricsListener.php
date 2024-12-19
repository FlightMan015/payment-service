<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Enums\PaymentProcessingInitiator;
use App\Events\PaymentSkippedEvent;
use App\Helpers\MoneyHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;

class CollectPaymentSkippedMetricsListener implements ShouldQueue
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
     * @param PaymentSkippedEvent $event
     *
     * @throws \Exception
     */
    public function handle(PaymentSkippedEvent $event): void
    {
        Log::info(message: 'Collect Payment Skipped Metrics Job Started', context: ['job_id' => $this->job?->uuid()]);

        $point = new Point('payments-skipped');

        $point
            ->addField('amount', $event->account->ledger ? MoneyHelper::convertToDecimal($event->account->ledger->balance) : null)
            ->addField('customer_external_reference_id', $event->account->external_ref_id)
            ->addField('customer_account_id', $event->account->id)
            ->addField('office_id', $event->account->area->external_ref_id)
            ->addField('payment_method_id', $event->paymentMethod?->id)
            ->addField('payment_method_type', $event->paymentMethod?->type->name)
            ->addField('payment_type', $event->payment?->type->name)
            ->addField('payment_id', $event->payment?->id)
            ->addField('area_id', $event->account->area->id)
            ->addTag('office', $event->account->area->name)
            ->addTag('initiator', PaymentProcessingInitiator::BATCH_PROCESSING->value)
            ->addTag('reason', $event->reason)
            ->time($event->timestamp);

        $this->writeApi->write($point);

        $this->writeApi->close();
    }

    /**
     * Handle a job failure.
     *
     * @param PaymentSkippedEvent $event
     * @param \Throwable $exception
     */
    public function failed(PaymentSkippedEvent $event, \Throwable $exception): void
    {
        Log::error(
            message: 'FAILED - Collect Payment Skipped Metrics',
            context: [
                'account_id' => $event->account->id,
                'exception' => $exception
            ]
        );
        $this->writeApi->close();
    }
}
