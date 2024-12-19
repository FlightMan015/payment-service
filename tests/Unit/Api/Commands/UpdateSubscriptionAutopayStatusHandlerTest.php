<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\UpdateSubscriptionAutopayStatusCommand;
use App\Api\Commands\UpdateSubscriptionAutopayStatusHandler;
use App\Api\Exceptions\PaymentMethodDoesNotBelongToAccountAssociatedWithSubscriptionException;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\CRM\SubscriptionRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\Customer\Subscription;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class UpdateSubscriptionAutopayStatusHandlerTest extends UnitTestCase
{
    /** @var MockObject&PaymentMethodRepository $paymentMethodRepository */
    private PaymentMethodRepository $paymentMethodRepository;
    /** @var MockObject&SubscriptionRepository $subscriptionRepository */
    private SubscriptionRepository $subscriptionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);
        $this->subscriptionRepository = $this->createMock(originalClassName: SubscriptionRepository::class);
    }

    #[Test]
    public function it_updates_autopay_status_when_passing_autopay_payment_method_id(): void
    {
        $subscription = Subscription::factory()->withoutRelationships()->make();
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['subscription' => $subscription]);
        $this->paymentMethodRepository
            ->method('find')
            ->willReturn($paymentMethod);
        $this->subscriptionRepository
            ->method('find')
            ->willReturn($subscription);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('setAutoPayPaymentMethod')
            ->with($subscription, $paymentMethod);
        $command = new UpdateSubscriptionAutopayStatusCommand(
            subscriptionId: $subscription->id,
            autopayPaymentMethodId: $paymentMethod->id
        );

        Log::shouldReceive('withContext')->once();

        $this->handler()->handle(command: $command);
    }

    #[Test]
    public function it_updates_autopay_status_when_do_not_pass_autopay_payment_method_id(): void
    {
        $subscription = Subscription::factory()->withoutRelationships()->make();

        $this->subscriptionRepository
            ->method('find')
            ->willReturn($subscription);
        $this->subscriptionRepository
            ->expects($this->once())
            ->method('setAutoPayPaymentMethod')
            ->with($subscription, null);
        Log::shouldReceive('withContext')->once();

        $command = new UpdateSubscriptionAutopayStatusCommand(
            subscriptionId: $subscription->id,
            autopayPaymentMethodId: null
        );

        $this->handler()->handle(command: $command);
    }

    #[Test]
    public function it_throws_exception_when_subscription_cannot_be_found_in_database(): void
    {
        $this->subscriptionRepository
            ->method('find')
            ->willThrowException(exception: new ResourceNotFoundException(message: 'Subscription was not found'));
        Log::shouldReceive('withContext')->once();

        $command = new UpdateSubscriptionAutopayStatusCommand(
            subscriptionId: Str::uuid()->toString(),
            autopayPaymentMethodId: null
        );

        $this->expectException(exception: ResourceNotFoundException::class);
        $this->expectExceptionMessage(message: 'Subscription was not found');

        $this->handler()->handle(command: $command);
    }

    #[Test]
    public function it_throws_exception_when_payment_method_does_not_belong_to_account_associated_to_subscription(): void
    {
        $account = Account::factory()->withoutRelationships()->make();
        $subscription = Subscription::factory()->makeWithRelationships(
            relationships: ['account' => $account]
        );
        $anotherSubscription = Subscription::factory()->makeWithRelationships(
            relationships: ['account' => $account]
        );
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['subscription' => $anotherSubscription]);
        $this->paymentMethodRepository
            ->method('find')
            ->willReturn($paymentMethod);
        $this->subscriptionRepository
            ->method('find')
            ->willReturn($subscription);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('setAutoPayPaymentMethod')
            ->with($subscription, $paymentMethod)
            ->willThrowException(new PaymentMethodDoesNotBelongToAccountAssociatedWithSubscriptionException(
                paymentMethodId: $paymentMethod->id,
                accountId: $subscription->account_id,
                subscriptionId: $subscription->id,
            ));
        Log::shouldReceive('withContext')->once();

        $command = new UpdateSubscriptionAutopayStatusCommand(
            subscriptionId: $subscription->id,
            autopayPaymentMethodId: $paymentMethod->id
        );

        $this->expectException(PaymentValidationException::class);

        $this->handler()->handle(command: $command);
    }

    private function handler(): UpdateSubscriptionAutopayStatusHandler
    {
        return new UpdateSubscriptionAutopayStatusHandler(
            paymentMethodRepository: $this->paymentMethodRepository,
            subscriptionRepository: $this->subscriptionRepository
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->paymentMethodRepository, $this->subscriptionRepository);
    }
}
