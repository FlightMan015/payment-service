<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\RefundPaymentFailedEvent;
use App\Helpers\MoneyHelper;
use App\PaymentProcessor\Enums\OperationEnum;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;

class CollectRefundPaymentFailedMetricsListener implements ShouldQueue
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
     * @param RefundPaymentFailedEvent $event
     *
     * @throws \Exception
     */
    public function handle(RefundPaymentFailedEvent $event): void
    {
        Log::info(message: 'Collect Refund Payment Failed Metrics Job Started', context: ['job_id' => $this->job?->uuid()]);

        $transaction = $event->refund->transactionForOperation(operation: OperationEnum::CREDIT);
        $failReason = $transaction?->declineReason;

        $point = new Point('refund-payments-failed');

        $point
            ->addField('original_amount', MoneyHelper::convertToDecimal($event->refund->originalPayment->amount))
            ->addField('refund_amount', MoneyHelper::convertToDecimal($event->refund->amount))
            ->addField('customer_external_reference_id', $event->refund->account->external_ref_id)
            ->addField('customer_account_id', $event->refund->account_id)
            ->addField('payment_method_id', $event->refund->paymentMethod?->id)
            ->addField('payment_method_type', $event->refund->paymentMethod?->type->name)
            ->addField('refund_payment_id', $event->refund->id)
            ->addField('original_payment_id', $event->refund->originalPayment->id)
            ->addField('area_id', $event->refund->account->area->id)
            ->addField('office_id', $event->refund->account->area->external_ref_id)
            ->addTag('office', $event->refund->account->area->name)
            ->time($event->timestamp);

        if (!is_null($failReason)) {
            $point->addTag('fail_reason', $failReason->name);
        }

        $this->writeApi->write($point);

        $this->writeApi->close();
    }

    /**
     * Handle a job failure.
     *
     * @param RefundPaymentFailedEvent $event
     * @param \Throwable $exception
     */
    public function failed(RefundPaymentFailedEvent $event, \Throwable $exception): void
    {
        Log::error(
            message: 'FAILED - Collect Refund Payment Failed Metrics',
            context: [
                'refund_id' => $event->refund->id,
                'account_id' => $event->refund->account_id,
                'exception' => $exception
            ]
        );
        $this->writeApi->close();
    }
}
