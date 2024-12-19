<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\Enums\PaymentProcessingInitiator;
use App\Events\PaymentAttemptedEvent;
use App\Listeners\CollectPaymentMetricsListener;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\DeclineReason;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class CollectPaymentMetricsListenerTest extends UnitTestCase
{
    private CollectPaymentMetricsListener $listener;
    private InfluxClient|MockInterface $mockInfluxClient;
    private MockInterface|WriteApi $mockWriteApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupInfluxClient();
        $this->setupListener();

        Event::fake();
    }

    private function setupInfluxClient(): void
    {
        $this->mockInfluxClient = Mockery::mock(InfluxClient::class);
        $this->mockWriteApi = Mockery::mock(WriteApi::class);
    }

    private function setupListener(): void
    {
        $this->mockInfluxClient->expects('createWriteApi')->andReturns($this->mockWriteApi);
        $this->listener = new CollectPaymentMetricsListener(influxClient: $this->mockInfluxClient);
    }

    private function setupEvent(): PaymentAttemptedEvent
    {
        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentType = PaymentType::factory()->make();
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['id' => Str::uuid()->toString()],
            relationships: ['account' => $account, 'type' => $paymentType]
        );
        $transaction = Transaction::factory()->makeWithRelationships(
            attributes: ['transaction_type_id' => TransactionTypeEnum::AUTH_CAPTURE->value],
            relationships: ['declineReason' => DeclineReason::factory()->make(['name' => 'Test'])]
        );
        $payment = Payment::factory()->makeWithRelationships(
            attributes: ['id' => Str::uuid()->toString()],
            relationships: [
                'paymentMethod' => $paymentMethod,
                'account' => $account,
                'type' => $paymentType,
                'transactions' => [
                    $transaction,
                ]
            ]
        );

        /** @var Payment&MockInterface $payment */
        $payment = Mockery::mock($payment)->makePartial();
        $payment->shouldReceive('transactionForOperation')
            ->withArgs([OperationEnum::AUTH_CAPTURE])
            ->andReturn($transaction);

        return new PaymentAttemptedEvent(payment: $payment, initiated_by: PaymentProcessingInitiator::BATCH_PROCESSING, operation: OperationEnum::AUTH_CAPTURE);
    }

    #[Test]
    public function it_is_listening_to_the_payment_attempted_event(): void
    {
        Event::assertListening(
            expectedEvent: PaymentAttemptedEvent::class,
            expectedListener: CollectPaymentMetricsListener::class
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
    public function it_logs_start_process_action_stores_the_metric_in_influxdb(): void
    {
        $event = $this->setupEvent();

        Log::shouldReceive('info')->once()->with(
            'Collect Payment Metrics Job Started',
            ['job_id' => $this->listener->job?->uuid()]
        );

        $transaction = $event->payment->transactions->first();

        $expectedPaymentPoint = new Point('payments-made');
        $expectedPaymentPoint
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

        if (!is_null($transaction) && !is_null($transaction->declineReason)) {
            $expectedPaymentPoint->addTag('decline_reason', $transaction->declineReason->name);
        }

        $expectedTransactionPoint = new Point('transactions');
        $expectedTransactionPoint
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

        if (!is_null($transaction) && !is_null($transaction->declineReason)) {
            $expectedTransactionPoint->addTag('decline_reason', $transaction->declineReason->name);
        }

        $this->mockWriteApi->expects('write')->with($this->equalTo($expectedPaymentPoint));
        $this->mockWriteApi->expects('write')->with($this->equalTo($expectedTransactionPoint));

        $this->mockWriteApi->expects('close');

        $this->listener->handle($event);
    }

    #[Test]
    public function it_logs_an_error_when_an_exception_is_thrown(): void
    {
        $event = $this->setupEvent();

        Log::shouldReceive('error')->once();

        $this->mockWriteApi->expects('close');
        $this->listener->failed($event, new \Exception('Some Error'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->listener, $this->mockInfluxClient, $this->mockWriteApi);
    }
}
