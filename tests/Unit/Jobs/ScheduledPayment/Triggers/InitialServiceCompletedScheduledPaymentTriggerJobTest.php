<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Jobs\ScheduledPayment\Triggers;

use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Repositories\Interface\ScheduledPaymentRepository;
use App\Entities\Enums\AccountStatusEnum;
use App\Entities\Enums\SubscriptionInitialStatusEnum;
use App\Entities\Subscription;
use App\Events\ScheduledPaymentCancelledEvent;
use App\Events\ScheduledPaymentSubmittedEvent;
use App\Infrastructure\Interfaces\SubscriptionServiceInterface;
use App\Jobs\ScheduledPayment\Triggers\InitialServiceCompletedScheduledPaymentTriggerJob;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\ScheduledPayment;
use App\Models\ScheduledPaymentTrigger;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use App\PaymentProcessor\PaymentProcessor;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Stubs\CRM\SubscriptionResponses;
use Tests\Unit\UnitTestCase;

class InitialServiceCompletedScheduledPaymentTriggerJobTest extends UnitTestCase
{
    use WorldPayCredentialsRepositoryMockingTrait;

    /** @var PaymentProcessor&MockInterface $paymentProcessor */
    private PaymentProcessor $paymentProcessor;
    private LoggerInterface $logger;
    /** @var PaymentRepository&MockObject $paymentRepository */
    private PaymentRepository $paymentRepository;
    /** @var ScheduledPaymentRepository&MockObject $scheduledPaymentRepository */
    private ScheduledPaymentRepository $scheduledPaymentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPaymentProcessor();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $this->scheduledPaymentRepository = $this->createMock(ScheduledPaymentRepository::class);

        $this->mockWorldPayCredentialsRepository();

        Event::fake();
        Queue::fake();
    }

    #[Test]
    public function it_throws_exception_if_payment_trigger_is_not_corresponding_to_the_job(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(__('messages.scheduled_payment.invalid_trigger'));

        $job = new InitialServiceCompletedScheduledPaymentTriggerJob(
            payment: ScheduledPayment::factory()->withoutRelationships()->make(
                ['trigger_id' => ScheduledPaymentTriggerEnum::NextServiceCompleted->value]
            )
        );

        $this->handleJob($job);
    }

    #[Test]
    public function it_throws_exception_if_scheduled_payment_status_does_not_allow_processing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(__('messages.scheduled_payment.payment_has_invalid_status'));

        $scheduledPayment = ScheduledPayment::factory()->withoutRelationships()->make(attributes: [
            'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
            'status_id' => ScheduledPaymentStatusEnum::SUBMITTED->value,
        ]);
        $job = new InitialServiceCompletedScheduledPaymentTriggerJob(payment: $scheduledPayment);

        $this->handleJob($job);
    }

    #[Test]
    public function it_is_not_processing_payment_and_mark_it_as_cancelled_if_subscription_id_is_missing(): void
    {
        $scheduledPayment = ScheduledPayment::factory()->withoutRelationships()->make(attributes: [
            'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
            'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
            'metadata' => ['foo' => 'bar'],
        ]);
        $job = new InitialServiceCompletedScheduledPaymentTriggerJob(payment: $scheduledPayment);

        $this->handleJob($job);

        Event::assertDispatched(ScheduledPaymentCancelledEvent::class);
        $this->paymentProcessor->shouldNotHaveReceived('sale');
    }

    #[Test]
    public function it_does_not_process_payment_if_the_subscription_is_not_in_completed_status(): void
    {
        $subscription = SubscriptionResponses::getSingle();
        $subscription->initial_status_id = SubscriptionInitialStatusEnum::NO_SHOW->value;

        /** @var ScheduledPayment $scheduledPayment */
        $scheduledPayment = ScheduledPayment::factory()->makeWithRelationships(attributes: [
            'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
            'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
            'metadata' => ['subscription_id' => $subscription->id],
            'payment_id' => null,
        ], relationships: [
            'account' => Account::factory()->withoutRelationships()->make(),
            'paymentMethod' => PaymentMethod::factory()->withoutRelationships()->make(),
            'trigger' => ScheduledPaymentTrigger::factory()->make([
                'id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'name' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->name,
            ])
        ]);

        $this->mockSubscriptionService(subscription: $subscription);

        $job = new InitialServiceCompletedScheduledPaymentTriggerJob(payment: $scheduledPayment);

        $this->handleJob($job);

        $this->paymentProcessor->shouldNotHaveReceived('sale');
    }

    #[Test]
    #[DataProvider('accountInvalidStateProvider')]
    public function it_is_not_processing_payment_and_mark_it_as_cancelled_if_account_is_not_in_valid_state_and_dispatches_event(
        Account $account
    ): void {
        $subscription = SubscriptionResponses::getSingle();
        $subscription->initial_status_id = SubscriptionInitialStatusEnum::COMPLETED->value;

        /** @var ScheduledPayment $scheduledPayment */
        $scheduledPayment = ScheduledPayment::factory()->makeWithRelationships(
            attributes: [
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
                'metadata' => ['subscription_id' => $subscription->id],
            ],
            relationships: ['account' => $account, 'paymentMethod' => PaymentMethod::factory()->withoutRelationships()->make()]
        );

        $this->mockSubscriptionService(subscription: $subscription);
        $this->scheduledPaymentRepository
            ->expects($this->once())
            ->method('update')
            ->with($scheduledPayment, ['status_id' => ScheduledPaymentStatusEnum::CANCELLED->value])
            ->willReturn($scheduledPayment);

        Log::expects('warning')->with(__('messages.scheduled_payment.inactive_account'), ['account_id' => $account->id]);

        DB::shouldReceive('transaction');

        $job = new InitialServiceCompletedScheduledPaymentTriggerJob(payment: $scheduledPayment);

        $this->handleJob($job);

        Event::assertDispatched(ScheduledPaymentCancelledEvent::class);
        $this->paymentProcessor->shouldNotHaveReceived('sale');
    }

    #[Test]
    public function it_is_not_processing_payment_and_mark_it_as_cancelled_if_subscription_is_not_active_and_dispatches_event(): void
    {
        $subscription = SubscriptionResponses::getSingle();
        $subscription->is_active = false;

        /** @var ScheduledPayment $scheduledPayment */
        $scheduledPayment = ScheduledPayment::factory()->makeWithRelationships(
            attributes: [
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
                'metadata' => ['subscription_id' => $subscription->id],
            ],
            relationships: [
                'account' => Account::factory()->withoutRelationships()->make(attributes: ['is_active' => true, 'status' => AccountStatusEnum::ACTIVE->value]),
                'paymentMethod' => PaymentMethod::factory()->withoutRelationships()->make()
            ]
        );

        $this->mockSubscriptionService(subscription: $subscription);
        $this->scheduledPaymentRepository
            ->expects($this->once())
            ->method('update')
            ->with($scheduledPayment, ['status_id' => ScheduledPaymentStatusEnum::CANCELLED->value])
            ->willReturn($scheduledPayment);

        Log::expects('warning')->with(__('messages.scheduled_payment.inactive_subscription'), ['subscription_id' => $subscription->id]);

        $job = new InitialServiceCompletedScheduledPaymentTriggerJob(payment: $scheduledPayment);

        $this->handleJob($job);

        Event::assertDispatched(ScheduledPaymentCancelledEvent::class);
        $this->paymentProcessor->shouldNotHaveReceived('sale');
    }

    #[Test]
    public function it_is_not_processing_payment_and_mark_it_as_cancelled_if_subscription_was_not_retrieved_and_dispatches_event(): void
    {
        $subscriptionId = Str::uuid()->toString();
        /** @var ScheduledPayment $scheduledPayment */
        $scheduledPayment = ScheduledPayment::factory()->makeWithRelationships(
            attributes: [
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
                'metadata' => ['subscription_id' => $subscriptionId],
            ],
            relationships: [
                'account' => Account::factory()->withoutRelationships()->make(attributes: ['is_active' => true, 'status' => AccountStatusEnum::ACTIVE->value]),
                'paymentMethod' => PaymentMethod::factory()->withoutRelationships()->make()
            ]
        );

        $clientException = new ClientException(
            message: 'Test exception ClientException',
            request: new Request('post', 'crm.goaptive.com'),
            response: new Response(status: 401)
        );
        $this->mockSubscriptionService(
            throwable: $clientException
        );
        $this->scheduledPaymentRepository
            ->expects($this->once())
            ->method('update')
            ->with($scheduledPayment, ['status_id' => ScheduledPaymentStatusEnum::CANCELLED->value])
            ->willReturn($scheduledPayment);

        Log::expects('error')->with('Error loading subscription', ['exception' => $clientException->getMessage(), 'trace' => $clientException->getTraceAsString()]);
        Log::expects('warning')->with(__('messages.scheduled_payment.invalid_metadata_payment_cancelled'), ['payment_id' => $scheduledPayment->id]);

        $job = new InitialServiceCompletedScheduledPaymentTriggerJob(payment: $scheduledPayment);

        $this->handleJob($job);

        Event::assertDispatched(ScheduledPaymentCancelledEvent::class);
        $this->paymentProcessor->shouldNotHaveReceived('sale');
    }

    #[Test]
    public function it_is_not_processing_payment_and_mark_it_as_cancelled_if_payment_method_is_deleted_and_dispatches_event(): void
    {
        $subscription = SubscriptionResponses::getSingle();
        $subscription->initial_status_id = SubscriptionInitialStatusEnum::COMPLETED->value;

        $paymentMethod = PaymentMethod::factory()->withoutRelationships()->make(['deleted_at' => now()]);

        /** @var ScheduledPayment $scheduledPayment */
        $scheduledPayment = ScheduledPayment::factory()->makeWithRelationships(
            attributes: [
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
                'metadata' => ['subscription_id' => $subscription->id],
            ],
            relationships: [
                'account' => Account::factory()->withoutRelationships()->make(attributes: ['is_active' => true, 'status' => AccountStatusEnum::ACTIVE->value]),
                'paymentMethod' => $paymentMethod
            ]
        );

        $this->mockSubscriptionService(subscription: $subscription);
        $this->scheduledPaymentRepository
            ->expects($this->once())
            ->method('update')
            ->with($scheduledPayment, ['status_id' => ScheduledPaymentStatusEnum::CANCELLED->value])
            ->willReturn($scheduledPayment);

        Log::expects('warning')->with(__('messages.scheduled_payment.payment_method_soft_deleted'), ['payment_method_id' => $paymentMethod->id]);

        $job = new InitialServiceCompletedScheduledPaymentTriggerJob(payment: $scheduledPayment);

        $this->handleJob($job);

        Event::assertDispatched(ScheduledPaymentCancelledEvent::class);
        $this->paymentProcessor->shouldNotHaveReceived('sale');
    }

    #[Test]
    #[DataProvider('paymentMethodProvider')]
    public function it_is_processing_payment_and_marking_scheduled_payment_as_submitted_in_successful_scenario_and_dispatches_event(
        PaymentMethod $paymentMethod
    ): void {
        $subscription = SubscriptionResponses::getSingle();
        $subscription->initial_status_id = SubscriptionInitialStatusEnum::COMPLETED->value;

        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(
            attributes: ['is_active' => true, 'status' => AccountStatusEnum::ACTIVE->value],
            relationships: ['area' => $area]
        );
        $paymentMethod->setRelation('account', $account);

        /** @var ScheduledPayment $scheduledPayment */
        $scheduledPayment = ScheduledPayment::factory()->makeWithRelationships(
            attributes: [
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
                'metadata' => ['subscription_id' => $subscription->id],
            ],
            relationships: ['paymentMethod' => $paymentMethod, 'account' => $account]
        );

        $this->mockSubscriptionService(subscription: $subscription);

        $this->paymentProcessor->expects('sale')->andReturns(true);

        $payment = Payment::factory()->withoutRelationships()->make();
        $this->paymentRepository->expects($this->once())->method('create')->willReturn($payment);
        $this->paymentRepository->expects($this->once())->method('update')->willReturn($payment);

        $this->scheduledPaymentRepository
            ->expects($this->once())
            ->method('update')
            ->with($scheduledPayment, ['status_id' => ScheduledPaymentStatusEnum::SUBMITTED->value, 'payment_id' => $payment->id])
            ->willReturn($scheduledPayment);

        DB::shouldReceive('transaction')->andReturnUsing(static fn ($callback) => $callback());

        $job = new InitialServiceCompletedScheduledPaymentTriggerJob(payment: $scheduledPayment);

        $this->handleJob($job);

        Event::assertDispatched(ScheduledPaymentSubmittedEvent::class);
    }

    public static function paymentMethodProvider(): iterable
    {
        yield 'CC' => ['paymentMethod' => static fn () => PaymentMethod::factory()->cc()->withoutRelationships()->make()];
        yield 'ACH with token' => ['paymentMethod' => static fn () => PaymentMethod::factory()->ach()->withoutRelationships()->make(['ach_token' => Str::random(10)])];
        yield 'ACH with routing and account number' => ['paymentMethod' => static fn () => PaymentMethod::factory()->ach()->withoutRelationships()->make()];
    }

    public static function accountInvalidStateProvider(): iterable
    {
        yield 'Account is not active' => [
            'account' => static fn () => Account::factory()->makeWithRelationships(
                attributes: ['is_active' => false],
                relationships: ['area' => Area::factory()->make()]
            )
        ];

        yield 'Account is soft deleted' => [
            'account' => static fn () => Account::factory()->makeWithRelationships(
                attributes: ['deleted_at' => now()],
                relationships: ['area' => Area::factory()->make()]
            )
        ];

        yield 'Account is not in active status' => [
            'account' => static fn () => Account::factory()->makeWithRelationships(
                attributes: ['status' => AccountStatusEnum::INACTIVE->value],
                relationships: ['area' => Area::factory()->make()]
            )
        ];
    }

    private function handleJob(InitialServiceCompletedScheduledPaymentTriggerJob $job): void
    {
        $job->handle(
            paymentProcessor: $this->paymentProcessor,
            logger: $this->logger,
            paymentRepository: $this->paymentRepository,
            scheduledPaymentRepository: $this->scheduledPaymentRepository
        );
    }

    private function mockPaymentProcessor(): void
    {
        $this->paymentProcessor = \Mockery::mock(PaymentProcessor::class);
        $this->paymentProcessor->allows('setLogger')->byDefault();
        $this->paymentProcessor->allows('setGateway')->andReturnNull()->byDefault();
        $this->paymentProcessor->allows('getResponseData')->andReturns('someResponse')->byDefault();
        $this->paymentProcessor->allows('getError')->andReturns('someError')->byDefault();
        $this->paymentProcessor->allows('populate')->byDefault();
    }

    private function mockSubscriptionService(object|null $subscription = null, \Throwable|null $throwable = null): void
    {
        $subscriptionService = $this->createMock(SubscriptionServiceInterface::class);

        if ($subscription) {
            $subscriptionService->method('getSubscription')->willReturn(
                value: Subscription::fromObject(
                    subscription: $subscription
                )
            );
        } else {
            $subscriptionService->method('getSubscription')->willThrowException($throwable);
        }

        $this->app->instance(SubscriptionServiceInterface::class, $subscriptionService);
    }
}
