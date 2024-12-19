<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\SuspendedPaymentProcessedEvent;
use App\Helpers\MoneyHelper;
use App\Listeners\CollectSuspendedPaymentProcessedMetricsListener;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use Carbon\Carbon;
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

class CollectSuspendedPaymentProcessedMetricsListenerTest extends UnitTestCase
{
    private CollectSuspendedPaymentProcessedMetricsListener $listener;
    private InfluxClient|MockInterface $mockInfluxClient;
    private MockInterface|WriteApi $mockWriteApi;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(now());

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
        $this->listener = new CollectSuspendedPaymentProcessedMetricsListener(
            influxClient: $this->mockInfluxClient
        );
    }

    /**
     * @param bool $withPaymentMethod
     *
     * @return SuspendedPaymentProcessedEvent
     */
    private function setupEvent(bool $withPaymentMethod = true): SuspendedPaymentProcessedEvent
    {
        $area = Area::factory()->make();
        $area->id = rand(1, 1_000_000);
        $account = Account::factory()->makeWithRelationships(relationships: [
            'area' => $area,
        ]);
        $paymentType = PaymentType::factory()->make();
        $paymentMethod = $withPaymentMethod ? PaymentMethod::factory()->makeWithRelationships(
            attributes: ['id' => Str::uuid()->toString()],
            relationships: ['account' => $account, 'type' => $paymentType]
        ) : null;
        $payment = Payment::factory()->makeWithRelationships(
            attributes: ['id' => Str::uuid()->toString()],
            relationships: [
                'paymentMethod' => $paymentMethod,
                'account' => $account,
                'originalPayment' => Payment::factory()->withoutRelationships()->make(),
                'type' => $paymentType,
            ]
        );

        return new SuspendedPaymentProcessedEvent(
            account: $account,
            paymentMethod: $paymentMethod,
            processedPayment: $payment,
            originalPayment: $payment
        );
    }

    #[Test]
    public function it_is_listening_to_the_suspended_payment_processed_event(): void
    {
        Event::assertListening(
            expectedEvent: SuspendedPaymentProcessedEvent::class,
            expectedListener: CollectSuspendedPaymentProcessedMetricsListener::class
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
    public function it_logs_start_process_for_suspended_payment_being_processed_and_stores_the_metric_in_influxdb(bool $withPaymentMethod): void
    {
        $event = $this->setupEvent(withPaymentMethod: $withPaymentMethod);

        Log::shouldReceive('info')->once()->with(
            'Collect Payment Processed Metrics Job Started',
            ['job_id' => $this->listener->job?->uuid()]
        );

        $expectedPoint = new Point('suspended-payments-processed');
        $expectedPoint
            ->addField('total_amount', MoneyHelper::convertToDecimal($event->processedPayment->amount))
            ->addField('customer_external_reference_id', $event->account->external_ref_id)
            ->addField('customer_account_id', $event->account->id)
            ->addField('office_id', $event->account->area->external_ref_id)
            ->addField('area_id', $event->account->area->id)
            ->addField('payment_method_id', $withPaymentMethod ? $event->paymentMethod->id : null)
            ->addField('payment_type', $event->processedPayment->type->name)
            ->addField('payment_id', $event->processedPayment->id)
            ->addField('original_payment_id', $event->originalPayment->id)
            ->addField('original_payment_processing_date', $event->originalPayment->processed_at)
            ->addTag('office', $event->account->area->name)
            ->time($event->timestamp);

        $this->mockWriteApi->expects('write')->with($this->equalTo($expectedPoint));

        $this->mockWriteApi->expects('close');

        $this->listener->handle($event);
    }

    public static function eventStateProvider(): iterable
    {
        yield 'with payment method' => ['withPaymentMethod' => true];
        yield 'without payment method' => ['withPaymentMethod' => false];
    }

    #[Test]
    #[DataProvider('eventStateProvider')]
    public function it_logs_an_error_when_an_exception_is_thrown(bool $withPaymentMethod): void
    {
        $event = $this->setupEvent(withPaymentMethod: $withPaymentMethod);
        $exception = new \Exception('Some Error');

        Log::shouldReceive('error')->once()->with(
            'FAILED - Collect Payment Processed Metrics',
            [
                'account_id' => $event->account->id,
                'payment_id' => $event->processedPayment->id,
                'original_payment_id' => $event->originalPayment->id,
                'payment_method' => $withPaymentMethod ? $event->paymentMethod->id : null,
                'payment_type' => $event->processedPayment->type->name,
                'exception' => $exception
            ]
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
            $this->mockWriteApi,
        );
    }
}
