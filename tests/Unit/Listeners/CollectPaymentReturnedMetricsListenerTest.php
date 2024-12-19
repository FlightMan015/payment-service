<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\PaymentReturnedEvent;
use App\Listeners\CollectPaymentReturnedMetricsListener;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\WorldpayResponseCodeEnum;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Stubs\PaymentProcessor\WorldpayResponseStub;
use Tests\Unit\UnitTestCase;

class CollectPaymentReturnedMetricsListenerTest extends UnitTestCase
{
    private CollectPaymentReturnedMetricsListener $listener;
    private InfluxClient|MockInterface $mockInfluxClient;
    private MockInterface|WriteApi $mockWriteApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupInfluxClient();
        $this->setupListener();

        Event::fake();
    }

    #[Test]
    public function it_is_listening_to_the_payment_returned_event(): void
    {
        Event::assertListening(
            expectedEvent: PaymentReturnedEvent::class,
            expectedListener: CollectPaymentReturnedMetricsListener::class
        );
    }

    #[Test]
    public function it_is_listening_on_the_correct_queue(): void
    {
        $expectedQueue = config('queue.connections.sqs.queues.collect_metrics');

        $queue = $this->listener->viaQueue();

        $this->assertEquals($expectedQueue, $queue);
    }

    #[Test]
    public function it_logs_start_process_and_stores_metric_in_influx(): void
    {
        $event = $this->setupEvent();

        Log::shouldReceive('info')->once()->with(
            'Collect Payment Returned Metrics Job Started',
            ['job_id' => $this->listener->job?->uuid()]
        );

        $expectedPoint = new Point('payments-returned');
        $expectedPoint
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
            ->addTag('status', PaymentStatusEnum::from($event->payment->payment_status_id)->name)
            ->addTag('gateway_response_code', WorldpayResponseCodeEnum::from((int) $event->payment->transactions->first()->gateway_response_code)->name)
            ->addTag('reason', 'Returned')
            ->time($event->timestamp);

        $this->mockWriteApi->expects('write')->with($this->equalTo($expectedPoint));
        $this->mockWriteApi->expects('close');

        $this->listener->handle($event);
    }

    #[Test]
    public function it_logs_an_error_when_an_exception_is_thrown(): void
    {
        $event = $this->setupEvent();
        $exception = new \Exception('Some Error');

        Log::shouldReceive('error')->once()->with(
            'FAILED - Collect Payment Returned Metrics',
            [
                'returned_payment_id' => $event->payment->id,
                'account_id' => $event->payment->account_id,
                'exception' => $exception->getMessage(),
            ]
        );

        $this->mockWriteApi->expects('close');
        $this->listener->failed($event, $exception);
    }

    private function setupInfluxClient(): void
    {
        $this->mockInfluxClient = Mockery::mock(InfluxClient::class);
        $this->mockWriteApi = Mockery::mock(WriteApi::class);
    }

    private function setupListener(): void
    {
        $this->mockInfluxClient->expects('createWriteApi')->andReturns($this->mockWriteApi);
        $this->listener = new CollectPaymentReturnedMetricsListener(
            influxClient: $this->mockInfluxClient,
        );
    }

    private function setupEvent(): PaymentReturnedEvent
    {
        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentType = PaymentType::factory()->make();
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $account, 'type' => $paymentType]);
        $transaction = Transaction::factory()->withoutRelationships()->make(
            attributes: [
                'gateway_response_code' => (string) WorldpayResponseCodeEnum::TRANSACTION_STATUS_CODE_RETURNED->value,
                'raw_response_log' => json_encode([
                    0,
                    WorldpayResponseStub::statusSuccess(isReturned: true),
                ]),
            ]
        );
        $payment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_status_id' => PaymentStatusEnum::RETURNED->value,
                'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            ],
            relationships: [
                'paymentMethod' => $paymentMethod,
                'account' => $account,
                'transactions' => $transaction,
                'type' => $paymentType,
            ]
        );

        /** @var MockInterface&Payment $payment */
        $payment = Mockery::mock($payment);
        $payment->allows('transactionByTransactionType')->andReturns($transaction);

        return new PaymentReturnedEvent(payment: $payment);
    }
}
