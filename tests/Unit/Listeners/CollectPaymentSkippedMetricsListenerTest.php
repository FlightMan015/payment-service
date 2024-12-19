<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\Enums\PaymentProcessingInitiator;
use App\Events\PaymentSkippedEvent;
use App\Helpers\MoneyHelper;
use App\Listeners\CollectPaymentSkippedMetricsListener;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Ledger;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class CollectPaymentSkippedMetricsListenerTest extends UnitTestCase
{
    private CollectPaymentSkippedMetricsListener $listener;
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
        $this->listener = new CollectPaymentSkippedMetricsListener(influxClient: $this->mockInfluxClient);
    }

    /**
     * @param bool $withPaymentMethod
     * @param bool $withPayment
     *
     * @throws \Exception
     */
    private function setupEvent(bool $withPaymentMethod = true, bool $withPayment = true): PaymentSkippedEvent
    {
        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: [
            'area' => $area,
            'ledger' => Ledger::factory()->withoutRelationships()->make(),
        ]);
        $paymentType = PaymentType::factory()->make();
        $paymentMethod = $withPaymentMethod ? PaymentMethod::factory()->makeWithRelationships(
            attributes: ['id' => Str::uuid()->toString()],
            relationships: ['account' => $account, 'type' => $paymentType]
        ) : null;
        /** @var Payment|null $payment */
        $payment = $withPayment ? Payment::factory()->makeWithRelationships(
            attributes: ['id' => Str::uuid()->toString()],
            relationships: ['paymentMethod' => $paymentMethod, 'account' => $account, 'type' => $paymentType]
        ) : null;

        return new PaymentSkippedEvent(
            account: $account,
            reason: 'Reason to skip',
            paymentMethod: $paymentMethod,
            payment: $payment
        );
    }

    #[Test]
    public function it_is_listening_to_the_payment_processed_event(): void
    {
        Event::assertListening(
            expectedEvent: PaymentSkippedEvent::class,
            expectedListener: CollectPaymentSkippedMetricsListener::class
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
    #[DataProvider('eventStateProvider')]
    public function it_logs_start_process_action_stores_the_metric_in_influxdb(bool $withPaymentMethod, bool $withPayment): void
    {
        $event = $this->setupEvent(withPaymentMethod: $withPaymentMethod, withPayment: $withPayment);

        Log::shouldReceive('info')->once()->with(
            'Collect Payment Skipped Metrics Job Started',
            ['job_id' => $this->listener->job?->uuid()]
        );

        $expectedPoint = new Point('payments-skipped');
        $expectedPoint
            ->addField('amount', $event->account->ledger ? MoneyHelper::convertToDecimal($event->account->ledger->balance) : null)
            ->addField('customer_external_reference_id', $event->account->external_ref_id)
            ->addField('customer_account_id', $event->account->id)
            ->addField('office_id', $event->account->area->external_ref_id)
            ->addField('payment_method_id', $withPaymentMethod ? $event->paymentMethod->id : null)
            ->addField('payment_method_type', $withPaymentMethod ? $event->paymentMethod->type->name : null)
            ->addField('payment_type', $withPayment ? $event->payment->type->name : null)
            ->addField('payment_id', $withPayment ? $event->payment->id : null)
            ->addField('area_id', $event->account->area->id)
            ->addTag('office', $event->account->area->name)
            ->addTag('initiator', PaymentProcessingInitiator::BATCH_PROCESSING->value)
            ->addTag('reason', $event->reason)
            ->time($event->timestamp);

        $this->mockWriteApi->expects('write')->with($this->equalTo($expectedPoint));

        $this->mockWriteApi->expects('close');

        $this->listener->handle($event);
    }

    public static function eventStateProvider(): iterable
    {
        yield 'with payment method and payment' => ['withPaymentMethod' => true, 'withPayment' => true];
        yield 'without payment method and payment' => ['withPaymentMethod' => false, 'withPayment' => false];
        yield 'with payment method and without payment' => ['withPaymentMethod' => true, 'withPayment' => false];
        yield 'without payment method and with payment' => ['withPaymentMethod' => false, 'withPayment' => true];
    }

    #[Test]
    public function it_logs_an_error_when_an_exception_is_thrown(): void
    {
        $event = $this->setupEvent();
        $exception = new \Exception('Some Error');

        Log::shouldReceive('error')->once()->with(
            'FAILED - Collect Payment Skipped Metrics',
            ['account_id' => $event->account->id, 'exception' => $exception]
        );

        $this->mockWriteApi->expects('close');
        $this->listener->failed($event, $exception);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->listener, $this->mockInfluxClient, $this->mockWriteApi);
    }
}
