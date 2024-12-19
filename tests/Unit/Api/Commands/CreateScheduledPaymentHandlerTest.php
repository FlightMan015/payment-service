<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\CreateScheduledPaymentCommand;
use App\Api\Commands\CreateScheduledPaymentHandler;
use App\Api\Repositories\Interface\ScheduledPaymentRepository;
use App\Entities\Subscription;
use App\Events\PaymentScheduledEvent;
use App\Exceptions\ScheduledPaymentDuplicateException;
use App\Exceptions\ScheduledPaymentTriggerInvalidMetadataException;
use App\Infrastructure\Interfaces\SubscriptionServiceInterface;
use App\Models\CRM\Customer\Account;
use App\Models\PaymentMethod;
use App\Models\ScheduledPayment;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Stubs\CRM\SubscriptionResponses;
use Tests\Unit\UnitTestCase;

class CreateScheduledPaymentHandlerTest extends UnitTestCase
{
    /** @var MockObject&ScheduledPaymentRepository $scheduledPaymentRepository */
    private ScheduledPaymentRepository $scheduledPaymentRepository;
    /** @var MockObject&SubscriptionServiceInterface $subscriptionService */
    private SubscriptionServiceInterface $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPaymentMethodRepositoryMock();
        $this->createSubscriptionServiceRepositoryMock();
        Event::fake();
    }

    #[Test]
    public function it_returns_scheduled_payment_id_in_the_end_of_the_process_and_dispatches_event(): void
    {
        $data = [
            'account_id' => Str::uuid()->toString(),
            'amount' => 100,
            'method_id' => Str::uuid()->toString(),
            'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
            'metadata' => ['subscription_id' => Str::uuid()->toString()],
        ];
        $command = new CreateScheduledPaymentCommand(
            accountId: $data['account_id'],
            amount: $data['amount'],
            paymentMethodId: $data['method_id'],
            trigger: ScheduledPaymentTriggerEnum::from($data['trigger_id']),
            metadata: $data['metadata'],
        );
        $account = Account::factory()->withoutRelationships()->make();

        $expectedId = Str::uuid()->toString();
        $this->mockScheduledPaymentRepositoryExpectedResult(expectedId: $expectedId, account: $account);
        $this->mockSubscriptionServiceWithExpectedResult(
            subscription: Subscription::fromObject(SubscriptionResponses::getSingle())
        );

        $this->assertInfoLogProcessingScheduledPayment($command);
        $this->assertInfoLogProcessedScheduledPayment($expectedId);

        $id = $this->handler()->handle(createScheduledPaymentCommand: $command);

        Event::assertDispatched(PaymentScheduledEvent::class);

        $this->assertSame($expectedId, $id);
    }

    #[Test]
    #[DataProvider('metadataMissingRequiredPropertiesProvider')]
    public function it_throws_exception_if_metadata_does_not_contain_necessary_information(
        array $metadata,
        ScheduledPaymentTriggerEnum $trigger,
        string $expectedMessage
    ): void {
        $this->expectException(exception: ScheduledPaymentTriggerInvalidMetadataException::class);
        $this->expectExceptionMessage(message: __('messages.scheduled_payment.metadata_validation_error', ['message' => $expectedMessage]));

        $command = new CreateScheduledPaymentCommand(
            accountId: Str::uuid()->toString(),
            amount: 100,
            paymentMethodId: Str::uuid()->toString(),
            trigger: $trigger,
            metadata: $metadata,
        );

        $this->handler()->handle(createScheduledPaymentCommand: $command);
    }

    #[Test]
    public function it_throws_exception_if_subscription_service_throws_exception(): void
    {
        $this->expectException(exception: ScheduledPaymentTriggerInvalidMetadataException::class);
        $this->expectExceptionMessage(message: __('messages.scheduled_payment.metadata_validation_error', ['message' => 'Subscription was not found']));

        $command = new CreateScheduledPaymentCommand(
            accountId: Str::uuid()->toString(),
            amount: 100,
            paymentMethodId: Str::uuid()->toString(),
            trigger: ScheduledPaymentTriggerEnum::InitialServiceCompleted,
            metadata: ['subscription_id' => Str::uuid()->toString()],
        );

        $this->mockSubscriptionServiceWithExpectedResult(throwable: new \Exception('Subscription service error'));

        $this->handler()->handle(createScheduledPaymentCommand: $command);
    }

    #[Test]
    public function test_it_throws_exception_if_duplicated_payment_was_found(): void
    {
        $duplicatedId = Str::uuid()->toString();

        $this->expectException(exception: ScheduledPaymentDuplicateException::class);
        $this->expectExceptionMessage(message: __('messages.scheduled_payment.duplicate', ['id' => $duplicatedId]));

        $command = new CreateScheduledPaymentCommand(
            accountId: Str::uuid()->toString(),
            amount: 100,
            paymentMethodId: Str::uuid()->toString(),
            trigger: ScheduledPaymentTriggerEnum::InitialServiceCompleted,
            metadata: ['subscription_id' => Str::uuid()->toString()],
        );

        $this->scheduledPaymentRepository->method('findDuplicate')->willReturn(ScheduledPayment::factory()->withoutRelationships()->make(attributes: ['id' => $duplicatedId]));
        $this->mockSubscriptionServiceWithExpectedResult(
            subscription: Subscription::fromObject(SubscriptionResponses::getSingle())
        );

        $this->handler()->handle(createScheduledPaymentCommand: $command);
    }

    private function assertInfoLogProcessingScheduledPayment(CreateScheduledPaymentCommand $command): void
    {
        Log::shouldReceive('info')
            ->with(
                __('messages.scheduled_payment.create.creating', ['payment_method_id' => $command->paymentMethodId]),
                $command->toArray()
            )
            ->once();
    }

    private function assertInfoLogProcessedScheduledPayment(string $expectedId): void
    {
        Log::shouldReceive('info')
            ->with(
                __('messages.scheduled_payment.create.created', ['id' => $expectedId])
            )
            ->once();
    }

    private function createPaymentMethodRepositoryMock(): void
    {
        $this->scheduledPaymentRepository = $this->createMock(originalClassName: ScheduledPaymentRepository::class);
    }

    private function createSubscriptionServiceRepositoryMock(): void
    {
        $this->subscriptionService = $this->createMock(originalClassName: SubscriptionServiceInterface::class);
    }

    private function handler(): CreateScheduledPaymentHandler
    {
        return new CreateScheduledPaymentHandler(scheduledPaymentRepository: $this->scheduledPaymentRepository);
    }

    private function mockScheduledPaymentRepositoryExpectedResult(
        string $expectedId,
        Account|null $account = null
    ): void {
        $paymentMethod = PaymentMethod::factory()->ach()->makeWithRelationships(attributes: [
            'id' => $expectedId,
        ], relationships: ['account' => $account]);

        $scheduledPayment = ScheduledPayment::factory()->makeWithRelationships(attributes: [
            'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
            'id' => $expectedId,
        ], relationships: ['account' => $account, 'paymentMethod' => $paymentMethod]);
        $this->scheduledPaymentRepository->method('create')->willReturn(value: $scheduledPayment);
    }

    private function mockSubscriptionServiceWithExpectedResult(
        Subscription|null $subscription = null,
        \Throwable|null $throwable = null
    ): void {
        if ($subscription) {
            $this->subscriptionService->method('getSubscription')->willReturn($subscription);
        } elseif ($throwable) {
            $this->subscriptionService->method('getSubscription')->willThrowException(exception: $throwable);
        }

        $this->app->instance(abstract: SubscriptionServiceInterface::class, instance: $this->subscriptionService);
    }

    /**
     * @return iterable
     */
    public static function metadataMissingRequiredPropertiesProvider(): iterable
    {
        yield 'missing subscription_id for initial service completed trigger' => [
            'metadata' => [],
            'trigger' => ScheduledPaymentTriggerEnum::InitialServiceCompleted,
            'expectedMessage' => static fn () => __('messages.scheduled_payment.metadata_missing_information', ['property' => 'subscription_id']),
        ];
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->scheduledPaymentRepository);
    }
}
