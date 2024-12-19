<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentAttemptedEvent;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;

class CollectPaymentMetricsListener implements ShouldQueue
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
     * @param PaymentAttemptedEvent $event
     *
     * @throws \Exception
     */
    public function handle(PaymentAttemptedEvent $event): void
    {
        Log::info(message: 'Collect Payment Metrics Job Started', context: ['job_id' => $this->job?->uuid()]);

        $transaction = $event->payment->transactionForOperation($event->operation);
        $declineReason = $transaction?->declineReason;

        $paymentPoint = new Point('payments-made');
        $paymentPoint
            ->addField('amount', $event->payment->getDecimalAmount())
            ->addField('payment_id', $event->payment->id)
            ->addField('payment_method_id', $event->payment->paymentMethod->id)
            ->addField('payment_method_type', $event->payment->paymentMethod->type->name)
            ->addField('payment_type', $event->payment->type->name)
            ->addField('customer_external_reference_id', $event->payment->account->external_ref_id)
            ->addField('customer_account_id', $event->payment->account_id)
            ->addField('office_id', $event->payment->account->area->external_ref_id)
            ->addField('area_id', $event->payment->account->area->id)
            ->addTag('office', $event->payment->account->area->name)
            ->addTag('gateway', PaymentGatewayEnum::from($event->payment->payment_gateway_id)->name)
            ->addTag('status', PaymentStatusEnum::from($event->payment->payment_status_id)->name)
            ->addTag('initiator', $event->initiated_by->value)
            ->time($event->timestamp);

        $transactionPoint = new Point('transactions');
        $transactionPoint
            ->addField('amount', $event->payment->getDecimalAmount())
            ->addField('payment_id', $event->payment->id)
            ->addField('transaction_id', $transaction?->id)
            ->addField('transaction_type', $event->operation->name)
            ->addField('customer_external_reference_id', $event->payment->account->external_ref_id)
            ->addField('customer_account_id', $event->payment->account_id)
            ->addField('office_id', $event->payment->account->area->external_ref_id)
            ->addField('area_id', $event->payment->account->area->id)
            ->addTag('office', $event->payment->account->area->name)
            ->addTag('gateway', PaymentGatewayEnum::from($event->payment->payment_gateway_id)->name)
            ->addTag('initiator', $event->initiated_by->value)
            ->time($event->timestamp);

        if (!is_null($declineReason)) {
            $paymentPoint->addTag('decline_reason', $declineReason->name);
            $transactionPoint->addTag('decline_reason', $declineReason->name);
        }

        $this->writeApi->write($paymentPoint);
        $this->writeApi->write($transactionPoint);

        $this->writeApi->close();
    }

    /**
     * Handle a job failure.
     *
     * @param PaymentAttemptedEvent $event
     * @param \Throwable $exception
     */
    public function failed(PaymentAttemptedEvent $event, \Throwable $exception): void
    {
        Log::error(
            message: 'FAILED - Collect Payment Metrics',
            context: [
                'payment_id' => $event->payment->id,
                'exception' => $exception
            ]
        );
        $this->writeApi->close();
    }
}
