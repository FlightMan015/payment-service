<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Entities\Subscription;
use App\Events\PaymentScheduledEvent;
use App\Infrastructure\Interfaces\SubscriptionServiceInterface;
use App\Listeners\CollectPaymentScheduledMetricsListener;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\ScheduledPayment;
use App\Models\ScheduledPaymentTrigger;
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

class CollectPaymentScheduledMetricsListenerTest extends UnitTestCase
{
    private CollectPaymentScheduledMetricsListener $listener;
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
            expectedEvent: PaymentScheduledEvent::class,
            expectedListener: CollectPaymentScheduledMetricsListener::class
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
            'Collect Payment Scheduled Metrics Job Started',
            ['job_id' => $this->listener->job?->uuid()]
        );

        $subscription = Subscription::fromObject(SubscriptionResponses::getSingle());
        $this->subscriptionService->method('getSubscription')->willReturn($subscription);

        $expectedPoint = new Point('payments-scheduled');
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
            ->addTag('office', $event->payment->account->area->name)
            ->addTag('trigger', $event->payment->trigger->name)
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
            'FAILED - Collect Payment Scheduled Metrics',
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
        $this->listener = new CollectPaymentScheduledMetricsListener(
            influxClient: $this->mockInfluxClient,
            subscriptionService: $this->subscriptionService
        );
    }

    private function setupEvent(): PaymentScheduledEvent
    {
        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentType = PaymentType::factory()->make();
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $account, 'type' => $paymentType]);
        /** @var ScheduledPayment $scheduledPayment */
        $scheduledPayment = ScheduledPayment::factory()->makeWithRelationships(
            attributes: [
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'payment_id' => null,
            ],
            relationships: [
                'paymentMethod' => $paymentMethod,
                'account' => $account,
                'trigger' => ScheduledPaymentTrigger::factory()->make()
            ]
        );

        return new PaymentScheduledEvent(payment: $scheduledPayment);
    }
}
