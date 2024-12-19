<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\SuspendedPaymentUpdatedEvent;
use App\Listeners\CollectSuspendedPaymentUpdatedMetricsListener;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\PaymentProcessor\Enums\PaymentResolutionEnum;
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

class CollectSuspendedPaymentUpdatedMetricsListenerTest extends UnitTestCase
{
    private CollectSuspendedPaymentUpdatedMetricsListener $listener;
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
        $this->listener = new CollectSuspendedPaymentUpdatedMetricsListener(influxClient: $this->mockInfluxClient);
    }

    /**
     * @param bool $withPaymentMethod
     * @param bool $withPayment
     *
     * @throws \Exception
     */
    private function setupEvent(bool $withPaymentMethod = true, bool $withPayment = true): SuspendedPaymentUpdatedEvent
    {
        $area = Area::factory()->make();
        $area->id = rand(1, 1_000_000);
        $account = Account::factory()->makeWithRelationships(relationships: [
            'area' => $area,
        ]);
        $paymentMethod = $withPaymentMethod ? PaymentMethod::factory()->makeWithRelationships(
            attributes: ['id' => Str::uuid()->toString()],
            relationships: ['account' => $account]
        ) : null;
        /** @var Payment|null $payment */
        $payment = $withPayment ? Payment::factory()->makeWithRelationships(
            attributes: ['id' => Str::uuid()->toString()],
            relationships: [
                'paymentMethod' => $paymentMethod,
                'account' => $account,
                'type' => PaymentType::factory()->make(),
            ]
        ) : null;

        return new SuspendedPaymentUpdatedEvent(
            account: $account,
            resolution: PaymentResolutionEnum::SUBMITTED,
            paymentMethod: $paymentMethod,
            payment: $payment
        );
    }

    #[Test]
    public function it_is_listening_to_the_payment_suspended_event(): void
    {
        Event::assertListening(
            expectedEvent: SuspendedPaymentUpdatedEvent::class,
            expectedListener: CollectSuspendedPaymentUpdatedMetricsListener::class
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
    public function it_logs_start_process_for_suspended_payment_being_updated_and_stores_the_metric_in_influxdb(bool $withPaymentMethod, bool $withPayment): void
    {
        $event = $this->setupEvent(withPaymentMethod: $withPaymentMethod, withPayment: $withPayment);

        Log::shouldReceive('info')->once()->with(
            'Collect Suspended Payment being updated (Terminated / Processed) Metrics Job Started',
            ['job_id' => $this->listener->job?->uuid()]
        );

        $expectedPoint = new Point('suspended-payment-resolutions');
        $expectedPoint
            ->addField('payment_id', $withPayment ? $event->payment->id : null)
            ->addField('office_id', $event->account->area->external_ref_id)
            ->addField('area_id', $event->account->area->id)
            ->addField('payment_method_id', $withPaymentMethod ? $event->paymentMethod->id : null)
            ->addField('customer_account_id', $event->account->id)
            ->addField('customer_external_reference_id', $event->account->external_ref_id)
            ->addField('payment_type_id', $withPayment ? $event->payment->type->id : null)
            ->addTag('office', $event->account->area->name)
            ->addTag('resolution', $event->resolution->value)
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
            'FAILED - Collect Suspended Payment being Updated Metrics',
            ['account_id' => $event->account->id, 'exception' => $exception]
        );

        $this->mockWriteApi->expects('close');
        $this->listener->failed($event, $exception);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset(
            $this->listener,
            $this->mockInfluxClient,
            $this->mockWriteApi
        );
    }
}
