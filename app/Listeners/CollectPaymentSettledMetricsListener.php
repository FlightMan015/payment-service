<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentSettledEvent;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Enums\WorldpayResponseCodeEnum;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;

class CollectPaymentSettledMetricsListener implements ShouldQueue
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
     * @param PaymentSettledEvent $event
     *
     * @throws \Exception
     */
    public function handle(PaymentSettledEvent $event): void
    {
        Log::info(message: 'Collect Payment Settled Metrics Job Started', context: ['job_id' => $this->job?->uuid()]);

        $checkTransaction = $event->payment->transactionByTransactionType(TransactionTypeEnum::CHECK_STATUS);
        $point = new Point('payments-settled');
        $point
            ->addField('amount', $event->payment->getDecimalAmount())
            ->addField('customer_external_reference_id', $event->payment->account->external_ref_id)
            ->addField('customer_account_id', $event->payment->account_id)
            ->addField('payment_method_id', $event->payment->paymentMethod->id)
            ->addField('payment_method_type', $event->payment->paymentMethod->type->name)
            ->addField('payment_id', $event->payment->id)
            ->addField('area_id', $event->payment->account->area->id)
            ->addField('office_id', $event->payment->account->area->external_ref_id)
            ->addTag('gateway', PaymentGatewayEnum::from($event->payment->payment_gateway_id)->name)
            ->addTag('office', $event->payment->account->area->name)
            ->addTag('status', PaymentStatusEnum::from($event->payment->payment_status_id)->name);

        if(!is_null($checkTransaction)) {
            $point->addTag('gateway_response_code', WorldpayResponseCodeEnum::from((int) $checkTransaction->gateway_response_code)->name);
        }

        $point->time($event->timestamp);

        $this->writeApi->write($point);
        $this->writeApi->close();
    }

    /**
     * Handle a job failure.
     *
     * @param PaymentSettledEvent $event
     * @param \Throwable $exception
     */
    public function failed(PaymentSettledEvent $event, \Throwable $exception): void
    {
        Log::error(
            message: 'FAILED - Collect Payment Settled Metrics',
            context: [
                'settled_payment_id' => $event->payment->id,
                'account_id' => $event->payment->account_id,
                'exception' => $exception->getMessage()
            ]
        );
        $this->writeApi->close();
    }
}
