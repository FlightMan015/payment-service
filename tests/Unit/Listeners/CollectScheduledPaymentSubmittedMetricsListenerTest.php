<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Entities\Subscription;
use App\Events\ScheduledPaymentSubmittedEvent;
use App\Infrastructure\Interfaces\SubscriptionServiceInterface;
use App\Listeners\CollectScheduledPaymentSubmittedMetricsListener;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\ScheduledPayment;
use App\Models\ScheduledPaymentTrigger;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Stubs\CRM\SubscriptionResponses;
use Tests\Unit\UnitTestCase;

class CollectScheduledPaymentSubmittedMetricsListenerTest extends UnitTestCase
{
    private CollectScheduledPaymentSubmittedMetricsListener $listener;
    private InfluxClient|MockInterface $mockInfluxClient;
    private MockInterface|WriteApi $mockWriteApi;
    /** @var SubscriptionServiceInterface&MockObject $subscriptionService */
    private SubscriptionServiceInterface $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = $this->createMock(SubscriptionServiceInterface::class);
        $this->setupInfluxClient();
        $this->setupListener();

        Event::fake();
    }

    #[Test]
    public function it_is_listening_to_the_payment_scheduled_event(): void
    {
        Event::assertListening(
            expectedEvent: ScheduledPaymentSubmittedEvent::class,
            expectedListener: CollectScheduledPaymentSubmittedMetricsListener::class
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
            'Collect Scheduled Payment Submitted Metrics Job Started',
            ['job_id' => $this->listener->job?->uuid()]
        );

        $subscription = Subscription::fromObject(SubscriptionResponses::getSingle());
        $this->subscriptionService->method('getSubscription')->willReturn($subscription);

        $expectedPoint = new Point('scheduled-payments-submitted');
        $expectedPoint
            ->addField('amount', $event->payment->getDecimalAmount())
            ->addField('scheduled_payment_id', $event->payment->id)
            ->addField('payment_method_id', $event->payment->paymentMethod->id)
            ->addField('payment_method_type', $event->payment->paymentMethod->type->name)
            ->addField('customer_external_reference_id', $event->payment->account->external_ref_id)
            ->addField('customer_account_id', $event->payment->account_id)
            ->addField('office_id', $event->payment->account->area->external_ref_id)
            ->addField('area_id', $event->payment->account->area->id)
            ->addField('subscription_id', $subscription->id)
            ->addField('plan_id', $subscription->planId)
            ->addField('trigger_id', $event->payment->trigger_id)
            ->addField('payment_id', $event->payment->id)
            ->addTag('office', $event->payment->account->area->name)
            ->addTag('trigger', $event->payment->trigger->name)
            ->addTag('gateway', PaymentGatewayEnum::from($event->resultPayment->payment_gateway_id)->name)
            ->addTag('status', PaymentStatusEnum::from($event->resultPayment->payment_status_id)->name)
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
            'FAILED - Collect Scheduled Payment Submitted Metrics',
            [
                'payment_id' => $event->payment->id,
                'exception' => $exception
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
        $this->listener = new CollectScheduledPaymentSubmittedMetricsListener(
            influxClient: $this->mockInfluxClient,
            subscriptionService: $this->subscriptionService
        );
    }

    private function setupEvent(): ScheduledPaymentSubmittedEvent
    {
        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentType = PaymentType::factory()->make();
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: [
            'account' => $account,
            'type' => $paymentType,
        ]);
        $payment = Payment::factory()->makeWithRelationships(relationships: [
            'paymentMethod' => $paymentMethod,
            'account' => $account,
            'type' => $paymentType,
        ]);
        /** @var ScheduledPayment $scheduledPayment */
        $scheduledPayment = ScheduledPayment::factory()->makeWithRelationships(
            relationships: [
                'paymentMethod' => $paymentMethod,
                'account' => $account,
                'payment' => $payment,
                'trigger' => ScheduledPaymentTrigger::factory()->make([
                    'id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                    'name' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->name,
                ])
            ]
        );

        return new ScheduledPaymentSubmittedEvent(payment: $scheduledPayment, resultPayment: $payment);
    }
}
